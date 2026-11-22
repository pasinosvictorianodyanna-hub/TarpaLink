<?php

namespace App\Http\Controllers\Console;

use App\Models\QuantityDiscount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shop-level discount bands.
 *
 * A band applies at or above its quantity, to the whole job. The checkout and
 * the price matrix both read these rows, so an edit here changes the next quote
 * and leaves jobs already priced alone.
 */
class QuantityDiscountController extends ConsoleController
{
    public function index(): View
    {
        $this->requireManager();

        $bands = $this->shop()->quantityDiscounts()->orderBy('min_qty')->get();

        return $this->page(
            'discounts',
            [
                'title' => 'Quantity discounts',
                'sub' => $this->plural($bands->count(), 'band').' · applied to the whole job',
            ],
            'console.discounts.index',
            ['bands' => $bands],
            [['label' => 'New band', 'url' => route('console.discounts.create'), 'style' => 'primary']],
        );
    }

    public function create(): View
    {
        $this->requireManager();

        return $this->page(
            'discounts',
            ['title' => 'New discount band', 'sub' => 'When does the price drop, and by how much'],
            'console.discounts.form',
            ['band' => new QuantityDiscount],
            [['label' => 'Cancel', 'url' => route('console.discounts')]],
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireManager();

        $this->shop()->quantityDiscounts()->create($this->validated($request));

        return redirect()->route('console.discounts')->with('status', 'Discount band added.');
    }

    public function edit(QuantityDiscount $discount): View
    {
        $this->requireManager();
        abort_unless($discount->shop_id === $this->shop()->id, 404);

        return $this->page(
            'discounts',
            ['title' => 'Edit band', 'sub' => $discount->percent.'% off from '.$discount->min_qty.' units'],
            'console.discounts.form',
            ['band' => $discount],
            [['label' => 'All bands', 'url' => route('console.discounts')]],
        );
    }

    public function update(Request $request, QuantityDiscount $discount): RedirectResponse
    {
        $this->requireManager();
        abort_unless($discount->shop_id === $this->shop()->id, 404);

        $discount->update($this->validated($request));

        return redirect()->route('console.discounts')->with('status', 'Discount band saved.');
    }

    public function destroy(QuantityDiscount $discount): RedirectResponse
    {
        $this->requireManager();
        abort_unless($discount->shop_id === $this->shop()->id, 404);

        $discount->delete();

        return redirect()->route('console.discounts')->with('status', 'Discount band removed.');
    }

    /**
     * @return array{min_qty: int, percent: int}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'min_qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'percent' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        return ['min_qty' => (int) $data['min_qty'], 'percent' => (int) $data['percent']];
    }
}
