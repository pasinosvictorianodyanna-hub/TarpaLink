<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PayMongo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * The only thing in the system that may mark a payment as settled.
 *
 * Three attempts with backoff. If the intent cannot be matched after the last
 * attempt the payment is marked failed and `failed_at` is set, which is what the
 * admin dashboard surfaces — a job must never be left silently stuck in pending.
 */
class ProcessPayMongoWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> seconds between attempts */
    public array $backoff = [10, 60, 300];

    public function __construct(public array $event)
    {
    }

    public function handle(PayMongo $paymongo): void
    {
        $intentId = $paymongo->intentIdFrom($this->event);

        $payment = $intentId
            ? Payment::where('intent_id', $intentId)->first()
            : null;

        if (! $payment) {
            throw new RuntimeException('No payment matches intent '.($intentId ?? '(none)'));
        }

        $payment->increment('attempts');

        // Idempotent: a replayed webhook returns here and changes nothing.
        if ($payment->status === 'paid') {
            return;
        }

        if (! $paymongo->isPaidEvent($this->event)) {
            return;
        }

        $rate = $payment->job?->shop?->commission_rate ?? 0;

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'commission' => (int) round($payment->amount * $rate / 10000),
            'last_error' => null,
        ]);
    }

    /** Called by the queue after the final failed attempt. */
    public function failed(?Throwable $exception): void
    {
        $intentId = app(PayMongo::class)->intentIdFrom($this->event);

        Payment::where('intent_id', $intentId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'last_error' => mb_substr($exception?->getMessage() ?? 'Unknown webhook failure', 0, 500),
        ]);
    }
}
