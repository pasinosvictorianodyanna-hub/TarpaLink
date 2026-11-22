<?php

namespace App\Http\Controllers\Console;

use App\Models\Service;
use App\Services\PriceCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Throwable;

/**
 * What the shop charges, and what the customer therefore sees.
 *
 * The live preview sits beside the matrix for a reason: a rate card is easy to
 * read and hard to sanity-check. Showing the exact breakdown the price engine
 * returns means an owner can see a wrong tier before a customer does.
 *
 * The preview is a plain GET form, so it works with no JavaScript and a reload
 * is cheap — the number is computed by the same PriceCalculator the checkout
 * calls, never re-derived here.
 */
class PriceMatrixController extends ConsoleController
{
    public function index(Request $request): View
    {
        // What the shop charges is the owner's business. The sidebar hides the
        // link from staff, and this is the rule behind the courtesy.
        $this->requireManager();

        $shop = $this->shop();

        $services = $shop->services()
            ->with(['tiers', 'options'])
            ->orderBy('sort')
            ->get();

        $active = $services->firstWhere('id', (int) $request->query('service')) ?? $services->first();

        $width = max(1, (int) $request->query('width', 36));
        $height = max(1, (int) $request->query('height', 48));
        $qty = max(1, (int) $request->query('qty', 3));

        $quote = null;
        $error = null;

        if ($active !== null) {
            try {
                $quote = (new PriceCalculator)->quote($shop, [
                    'service_id' => $active->id,
                    'width_in' => $width,
                    'height_in' => $height,
                    'qty' => $qty,
                    'options' => [],
                ]);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->page(
            'prices',
            [
                'title' => 'Price matrix',
                'sub' => $this->plural($services->count(), 'service').' · what customers are quoted',
            ],
            'console.price-matrix',
            [
                'services' => $services,
                'active' => $active,
                'quote' => $quote,
                'error' => $error,
                'width' => $width,
                'height' => $height,
                'qty' => $qty,
            ],
            [['label' => 'Edit services', 'url' => route('console.services')]],
        );
    }
}
