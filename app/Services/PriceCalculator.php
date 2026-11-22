<?php

namespace App\Services;

use App\Exceptions\ServiceUnavailable;
use App\Models\Shop;

/**
 * The single source of truth for what a job costs.
 *
 * Both the Vue order builder and the Filament price matrix call this class.
 * Nothing else may compute a price. See docs/CONTRACT.md.
 */
class PriceCalculator
{
    /**
     * Quote a job against a shop's own price matrix.
     *
     * @param  array{service_id:int, width_in:float, height_in:float, qty:int, options?:array}  $spec
     * @return array{lines:array, subtotal:int, discount:int, total:int, currency:string, matrix_version:int, tier:string}
     */
    public function quote(Shop $shop, array $spec): array
    {
        $service = $shop->services()
            ->with(['tiers', 'options'])
            ->where('active', true)
            ->find($spec['service_id']);

        if (! $service) {
            throw new ServiceUnavailable('That service is not offered by this shop.');
        }

        $width = (float) $spec['width_in'];
        $height = (float) $spec['height_in'];
        $qty = max(1, (int) $spec['qty']);

        $tier = $service->tierFor($width, $height);

        if (! $tier) {
            throw new ServiceUnavailable(sprintf(
                'This shop does not print %s×%s in. Try a smaller size.',
                rtrim(rtrim(number_format($width, 2), '0'), '.'),
                rtrim(rtrim(number_format($height, 2), '0'), '.'),
            ));
        }

        $lines = [];

        // Base line — area services price per square foot, piece services per unit.
        $areaSqft = ($width * $height) / 144;

        $perPiece = $service->unit === 'sqft'
            ? (int) round($tier->unit_price * $areaSqft)
            : (int) $tier->unit_price;

        $lines[] = [
            'label' => $service->name.' '.$tier->label,
            'qty' => $qty,
            'unit_price' => $perPiece,
            'amount' => $perPiece * $qty,
        ];

        // Finishing options chosen by the customer.
        foreach ($spec['options'] ?? [] as $choice) {
            $option = $service->options->firstWhere('id', (int) ($choice['id'] ?? 0));

            if (! $option) {
                continue;
            }

            $optionQty = max(0, (float) ($choice['qty'] ?? 1));

            if ($optionQty === 0.0) {
                continue;
            }

            $lines[] = [
                'label' => $option->name.' ('.rtrim(rtrim(number_format($optionQty, 2), '0'), '.').' '.$option->unit.')',
                'qty' => $optionQty,
                'unit_price' => (int) $option->price,
                'amount' => (int) round($option->price * $optionQty),
            ];
        }

        $subtotal = array_sum(array_column($lines, 'amount'));

        $percent = (int) ($shop->quantityDiscounts()
            ->where('min_qty', '<=', $qty)
            ->orderByDesc('min_qty')
            ->value('percent') ?? 0);

        // The Filament form caps a discount at 90%, but the form is not the rule:
        // a seeded or imported row must not be able to price a job below zero.
        // Clamp here, where the price is decided, so FR-33 holds for every caller.
        $discount = $percent > 0
            ? min($subtotal, (int) round($subtotal * $percent / 100))
            : 0;

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => max(0, $subtotal - $discount),
            'currency' => 'PHP',
            'matrix_version' => $service->matrixVersion(),
            'tier' => $tier->label,
        ];
    }
}
