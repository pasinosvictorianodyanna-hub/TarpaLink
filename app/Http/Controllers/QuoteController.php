<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceUnavailable;
use App\Models\Shop;
use App\Services\PriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __invoke(Request $request, PriceCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'shop' => ['required', 'string'],
            'service_id' => ['required', 'integer'],
            'width_in' => ['required', 'numeric', 'min:1', 'max:1000'],
            'height_in' => ['required', 'numeric', 'min:1', 'max:1000'],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'options' => ['array'],
            'options.*.id' => ['integer'],
            'options.*.qty' => ['numeric', 'min:0'],
        ]);

        $shop = Shop::active()->where('slug', $data['shop'])->firstOrFail();

        try {
            $quote = $calculator->quote($shop, [
                'service_id' => $data['service_id'],
                'width_in' => $data['width_in'],
                'height_in' => $data['height_in'],
                'qty' => $data['qty'],
                'options' => $data['options'] ?? [],
            ]);
        } catch (ServiceUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($quote);
    }
}
