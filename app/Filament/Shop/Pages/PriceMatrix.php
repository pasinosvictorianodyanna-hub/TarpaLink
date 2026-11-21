<?php

namespace App\Filament\Shop\Pages;

use App\Filament\Shop\Concerns\ScopesToShop;
use App\Models\Service;
use App\Services\PriceCalculator;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;
use UnitEnum;

/**
 * What the shop charges, and what the customer therefore sees.
 *
 * The prototype puts a live preview beside the matrix for a reason: a rate card
 * is easy to read and hard to sanity-check. Showing the exact breakdown the
 * price engine returns means an owner can see a wrong tier before a customer
 * does.
 *
 * Editing stays in the Services resource — that already has the forms, the
 * validation and the tenancy checks, and rebuilding them here would be a second
 * place for the two to disagree.
 */
class PriceMatrix extends Page
{
    use ScopesToShop;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Work';

    protected static ?string $navigationLabel = 'Price matrix';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Price matrix';

    protected string $view = 'filament.shop.pages.price-matrix';

    public ?int $serviceId = null;

    /** The sample spec the preview prices. */
    public int $width = 36;

    public int $height = 48;

    public int $qty = 3;

    public function mount(): void
    {
        $this->serviceId = $this->services()->first()?->id;
    }

    /** @return Collection<int, Service> */
    public function services(): Collection
    {
        return $this->shop()?->services()
            ->with(['tiers', 'options'])
            ->orderBy('sort')
            ->get() ?? collect();
    }

    public function active(): ?Service
    {
        $services = $this->services();

        return $services->firstWhere('id', $this->serviceId) ?? $services->first();
    }

    public function selectService(int $id): void
    {
        $this->serviceId = $id;
    }

    /**
     * The real quote for the sample spec — not a re-implementation.
     *
     * Anything that recomputed the price here would drift from the checkout
     * within a week, and the drift would be invisible until a customer saw a
     * number the shop did not expect.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $service = $this->active();
        $shop = $this->shop();

        if (! $service || ! $shop) {
            return ['error' => 'This shop has no services yet.'];
        }

        try {
            return (new PriceCalculator)->quote($shop, [
                'service_id' => $service->id,
                'width_in' => $this->width,
                'height_in' => $this->height,
                'qty' => $this->qty,
                'options' => [],
            ]);
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
