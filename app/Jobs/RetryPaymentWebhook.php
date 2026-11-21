<?php

namespace App\Jobs;

use App\Models\PaymentWebhook;
use App\Services\PayMongoWebhookHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Re-processes a callback that arrived before its payment row existed.
 *
 * Backoff is 1 minute, then 5. After three attempts the row stays failed and the
 * admin dashboard raises it — a payment that never settles must be visible, not
 * silently stuck in pending.
 */
class RetryPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public PaymentWebhook $webhook) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(PayMongoWebhookHandler $handler): void
    {
        $handler->retry($this->webhook);
    }

    public function failed(Throwable $e): void
    {
        $this->webhook->update([
            'status' => 'failed',
            'error' => mb_substr($e->getMessage(), 0, 480),
        ]);
    }
}
