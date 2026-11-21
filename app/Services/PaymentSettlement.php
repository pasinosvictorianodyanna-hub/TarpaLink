<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * The only place a payment becomes "paid".
 *
 * The rate is read at capture time, not at checkout time: a shop that upgraded
 * last week should be charged the rate it is on when the money actually lands.
 */
class PaymentSettlement
{
    public function __construct(private PayoutLedger $ledger) {}

    public function markPaid(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment->loadMissing('job.shop');

            // Replays are a no-op. The unique index on payouts.payment_id is the
            // real guard; this just avoids rewriting a settled row.
            if ($payment->status === 'paid' && $payment->payout()->exists()) {
                return $payment;
            }

            $shop = $payment->job->shop;
            $rate = (int) $shop->commission_rate;

            $payment->update([
                'status' => 'paid',
                'paid_at' => $payment->paid_at ?? now(),
                'commission' => (int) round($payment->amount * $rate / 10000),
            ]);

            $this->ledger->record($payment->fresh(['job.shop']), $rate);

            return $payment;
        });
    }

    public function markFailed(Payment $payment): void
    {
        if ($payment->status === 'paid') {
            return;
        }

        $payment->update(['status' => 'failed']);
    }
}
