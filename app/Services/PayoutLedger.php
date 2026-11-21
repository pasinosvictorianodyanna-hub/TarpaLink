<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Payout;
use App\Models\Shop;

/**
 * Writes the commission split for captured payments.
 *
 * The ledger is append-only in spirit: a row is created once, when money lands,
 * and never recalculated. `payouts.payment_id` is unique, so a replayed webhook
 * hits the constraint instead of crediting twice.
 */
class PayoutLedger
{
    /**
     * Record (or return) the payout row for a captured payment.
     *
     * $rateBasisPoints is the rate that was actually applied when the payment was
     * captured. Callers that have just computed commission should pass it; a
     * backfill can leave it null and take the shop's current rate.
     */
    public function record(Payment $payment, ?int $rateBasisPoints = null): Payout
    {
        $payment->loadMissing('job.shop');

        /** @var Shop $shop */
        $shop = $payment->job->shop;

        $gross = (int) $payment->amount;
        $commission = (int) $payment->commission;
        $rate = $rateBasisPoints ?? (int) $shop->commission_rate;

        return Payout::firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'shop_id' => $shop->id,
                'gross' => $gross,
                'commission' => $commission,
                'net' => $gross - $commission,
                'commission_rate' => $rate,
                'status' => 'pending',
                'payout_date' => null,
            ],
        );
    }

    /**
     * Create ledger rows for a shop's paid payments that do not have one yet.
     *
     * Used by the seeder and by the reports screen, so a payment captured before
     * the ledger existed still shows up in the payout history.
     */
    public function backfillForShop(Shop $shop): int
    {
        $created = 0;

        $payments = $shop->payments()
            ->where('payments.status', 'paid')
            ->whereDoesntHave('payout')
            ->with('job.shop')
            ->get();

        foreach ($payments as $payment) {
            $this->record($payment);
            $created++;
        }

        return $created;
    }
}
