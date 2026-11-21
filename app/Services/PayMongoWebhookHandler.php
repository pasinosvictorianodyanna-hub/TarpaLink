<?php

namespace App\Services;

use App\Jobs\RetryPaymentWebhook;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Turns a PayMongo callback into a settled payment.
 *
 * Idempotency has two layers: the event_id unique index stops a replayed
 * delivery from being processed twice, and payouts.payment_id stops the same
 * intent from being credited twice even if the provider sends a *different*
 * event id for the same money.
 */
class PayMongoWebhookHandler
{
    public function __construct(private PaymentSettlement $settlement) {}

    /** Handle a fresh delivery. Never throws — failures are recorded and retried. */
    public function handle(array $payload, string $rawBody): PaymentWebhook
    {
        $eventId = (string) (data_get($payload, 'data.id') ?: 'sha1:'.sha1($rawBody));
        $type = (string) data_get($payload, 'data.attributes.type', 'unknown');
        $intentId = $this->intentId($payload);

        $log = PaymentWebhook::firstOrCreate(
            ['event_id' => $eventId],
            [
                'provider' => 'paymongo',
                'intent_id' => $intentId ?? 'unknown',
                'type' => $type,
                'payload' => $payload,
                'status' => 'processed',
                'attempts' => 1,
            ],
        );

        if (! $log->wasRecentlyCreated) {
            return $log;
        }

        try {
            $this->process($log, $type, $intentId);
        } catch (Throwable $e) {
            $this->recordFailure($log, $e);
            $this->scheduleRetry($log);
        }

        return $log;
    }

    /** A retry from the queue. Throws so the queue can back off and try again. */
    public function retry(PaymentWebhook $log): void
    {
        $log->increment('attempts');

        try {
            $this->process($log, $log->type, $log->intent_id === 'unknown' ? null : $log->intent_id);
        } catch (Throwable $e) {
            $this->recordFailure($log, $e);

            throw $e;
        }
    }

    private function process(PaymentWebhook $log, string $type, ?string $intentId): void
    {
        if ($intentId === null || $intentId === '') {
            throw new RuntimeException('Callback carried no payment intent id.');
        }

        $payment = Payment::where('intent_id', $intentId)->first();

        if (! $payment) {
            // Usually means the callback beat the checkout transaction. Worth a retry.
            throw new RuntimeException("No payment row for intent [{$intentId}] yet.");
        }

        if (str_contains($type, 'failed')) {
            $this->settlement->markFailed($payment);
            $log->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);

            return;
        }

        if (! str_contains($type, 'paid')) {
            $log->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        $this->settlement->markPaid($payment);

        $log->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
    }

    private function recordFailure(PaymentWebhook $log, Throwable $e): void
    {
        Log::warning('PayMongo webhook failed', [
            'webhook_id' => $log->id,
            'intent_id' => $log->intent_id,
            'attempts' => $log->attempts,
            'error' => $e->getMessage(),
        ]);

        $log->update([
            'status' => 'failed',
            'error' => Str::limit($e->getMessage(), 480),
        ]);
    }

    private function scheduleRetry(PaymentWebhook $log): void
    {
        if ($log->attempts >= 3) {
            return;
        }

        // 1 min, then 5 min. The third failure is terminal and shows up on the
        // admin dashboard rather than retrying forever.
        $delay = $log->attempts <= 1 ? 60 : 300;

        try {
            RetryPaymentWebhook::dispatch($log)->delay(now()->addSeconds($delay));
        } catch (Throwable $e) {
            // A synchronous queue (or a broken one) must not turn "we recorded
            // the failure" into a 500 for the provider.
            Log::warning('Could not enqueue PayMongo webhook retry', [
                'webhook_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Pull the intent id out of the shapes PayMongo uses for the events we care about. */
    private function intentId(array $payload): ?string
    {
        $resource = data_get($payload, 'data.attributes.data', []);

        return data_get($resource, 'attributes.payment_intent_id')
            ?? data_get($resource, 'attributes.payment_intent.id')
            ?? (data_get($resource, 'type') === 'payment_intent' ? data_get($resource, 'id') : null)
            ?? data_get($payload, 'data.attributes.payment_intent_id')
            ?? (data_get($payload, 'data.attributes.type') === 'payment_intent' ? data_get($payload, 'data.id') : null);
    }
}
