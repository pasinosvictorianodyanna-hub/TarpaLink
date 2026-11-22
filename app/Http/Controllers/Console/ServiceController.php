<?php

namespace App\Http\Controllers\Console;

use App\Models\Service;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The shop's own catalogue.
 *
 * A service is only quotable once it has tiers, so the tiers and the add-ons are
 * edited on the same screen as the service itself rather than behind a second
 * navigation step. Every query is scoped to the shop at the query level, so a
 * shop can never see — or route to — another shop's services.
 */
class ServiceController extends ConsoleController
{
    public function index(): View
    {
        $this->requireManager();

        $services = $this->shop()
            ->services()
            ->withCount(['tiers', 'options'])
            ->orderBy('sort')
            ->get();

        return $this->page(
            'services',
            [
                'title' => 'Services',
                'sub' => $this->plural($services->count(), 'service').' in the catalogue',
            ],
            'console.services.index',
            ['services' => $services],
            [['label' => 'New service', 'url' => route('console.services.create'), 'style' => 'primary']],
        );
    }

    public function create(): View
    {
        $this->requireManager();

        return $this->page(
            'services',
            ['title' => 'New service', 'sub' => 'What the shop sells, and what it charges'],
            'console.services.form',
            ['service' => new Service(['unit' => 'sqft', 'sort' => 0, 'active' => true]), 'tiers' => collect(), 'options' => collect()],
            [['label' => 'Cancel', 'url' => route('console.services')]],
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireManager();

        $service = $this->shop()->services()->create($this->validated($request));

        $this->syncRows($service, $request);

        return redirect()
            ->route('console.services.edit', $service)
            ->with('status', $service->name.' created.');
    }

    public function edit(Service $service): View
    {
        $this->requireManager();
        abort_unless($service->shop_id === $this->shop()->id, 404);

        return $this->page(
            'services',
            ['title' => $service->name, 'sub' => 'Service, size tiers and add-ons'],
            'console.services.form',
            [
                'service' => $service,
                'tiers' => $service->tiers()->orderBy('sort')->get(),
                'options' => $service->options()->orderBy('sort')->get(),
            ],
            [['label' => 'All services', 'url' => route('console.services')]],
        );
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $this->requireManager();
        abort_unless($service->shop_id === $this->shop()->id, 404);

        $service->update($this->validated($request, $service));

        $this->syncRows($service, $request);

        return redirect()
            ->route('console.services.edit', $service)
            ->with('status', $service->name.' saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('services', 'slug')
                    ->where('shop_id', $this->shop()->id)
                    ->ignore($service?->id),
            ],
            'unit' => ['required', 'in:sqft,pc'],
            'blurb' => ['nullable', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'active' => ['nullable', 'boolean'],
        ], [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
            'slug.unique' => 'Another service in this shop already uses that slug.',
        ]);

        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['active'] = $request->boolean('active');

        return $data;
    }

    /**
     * Replace the tiers and add-ons with what the form submitted.
     *
     * Rows carry their id, so an edit updates in place and a new row inserts.
     * Rows that disappeared from the form are deleted — a tier the owner removed
     * must stop being quotable immediately.
     */
    private function syncRows(Service $service, Request $request): void
    {
        $this->sync(
            $service->tiers(),
            (array) $request->input('tiers', []),
            fn (array $row): array => [
                'label' => trim((string) ($row['label'] ?? '')),
                'min_width' => (float) ($row['min_width'] ?? 0),
                'max_width' => filled($row['max_width'] ?? null) ? (float) $row['max_width'] : null,
                'unit_price' => Money::toCentavos($row['unit_price'] ?? 0),
                'sort' => (int) ($row['sort'] ?? 0),
            ],
            fn (array $row): bool => trim((string) ($row['label'] ?? '')) !== '',
        );

        $this->sync(
            $service->options(),
            (array) $request->input('options', []),
            fn (array $row): array => [
                'name' => trim((string) ($row['name'] ?? '')),
                'unit' => in_array(($row['unit'] ?? 'pc'), ['sqft', 'ft', 'pc'], true) ? $row['unit'] : 'pc',
                'price' => Money::toCentavos($row['price'] ?? 0),
                'sort' => (int) ($row['sort'] ?? 0),
            ],
            fn (array $row): bool => trim((string) ($row['name'] ?? '')) !== '',
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\HasMany<*>  $relation
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array<string, mixed>  $attributes
     * @param  callable(array<string, mixed>): bool  $keep
     */
    private function sync($relation, array $rows, callable $attributes, callable $keep): void
    {
        /*
         * A fresh query per operation, not the relation itself.
         *
         * `Relation::__call` forwards to the underlying builder and *mutates*
         * it, so `$relation->whereKey($id)->first()` leaves `id = 1` on the
         * relation for the rest of the method — and the cleanup delete below
         * then runs `id = 1 AND id not in (1)`, matches nothing, and silently
         * keeps every tier the owner removed. Rebuilding the constraint each
         * time is the only version that deletes what it says it deletes.
         */
        $base = fn () => $relation->getRelated()
            ->newQuery()
            ->where($relation->getForeignKeyName(), $relation->getParentKey());

        $kept = [];

        foreach ($rows as $row) {
            // A half-filled row is a row the owner started and abandoned; it is
            // skipped rather than rejected, so a stray blank does not block a save.
            if (! $keep($row)) {
                continue;
            }

            $id = $row['id'] ?? null;

            if ($id !== null && $id !== '') {
                $model = $base()->whereKey($id)->first();

                if ($model !== null) {
                    $model->update($attributes($row));
                    $kept[] = $model->getKey();

                    continue;
                }
            }

            $kept[] = $relation->create($attributes($row))->getKey();
        }

        $base()->whereNotIn('id', $kept ?: [0])->delete();
    }
}
