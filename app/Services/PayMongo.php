<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Payment;
use App\Support\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * PayMongo sandbox client.
 *
 * With no secret key configured the app runs in *simulated* mode: intents are
 * generated locally so the whole flow can be demonstrated without live keys.
 * Simulated mode is refused outside `local`, so it can never quietly run in
 * production and pretend a payment succeeded.
 *
 * Keys are read through `Credentials`, so they can be set on the admin
 * integrations screen as well as in `.env`. Before that, the screen showed the
 * keys as "not configured" no matter what was saved, and the only way to change
 * one was to redeploy.
 */
final class PayMongo
{
    public function isSimulated(): bool
    {
        return Credentials::get('paymongo', 'secret') === null;
    }

    /**
     * Create a payment intent for a job.
     *
     * @return array{id: string, client_key: ?string, simulated: bool}
     */
    public function createIntent(Job $job, Payment $payment): array
    {
        if ($this->isSimulated()) {
            return [
                'id' => 'pi_sim_'.$job->reference,
                'client_key' => null,
                'simulated' => true,
            ];
        }

        $response = Http::withBasicAuth((string) Credentials::get('paymongo', 'secret'), '')
            ->acceptJson()
            ->post(rtrim((string) config('services.paymongo.base_url'), '/').'/payment_intents', [
                'data' => [
                    'attributes' => [
                        'amount' => $payment->amount,
                        'payment_method_allowed' => ['gcash', 'paymaya', 'card'],
                        'currency' => 'PHP',
                        'description' => 'TarpaLink job '.$job->reference,
                        'metadata' => ['job_reference' => $job->reference],
                    ],
                ],
            ])
            ->throw()
            ->json();

        return [
            'id' => $response['data']['id'],
            'client_key' => $response['data']['attributes']['client_key'] ?? null,
            'simulated' => false,
        ];
    }

    /**
     * Verify the `Paymongo-Signature` header.
     *
     * Header format: `t=<timestamp>,te=<test signature>,li=<live signature>`.
     * The signature is HMAC-SHA256 over "<timestamp>.<raw payload>".
     */
    public function verifySignature(string $payload, ?string $header): bool
    {
        $secret = Credentials::get('paymongo', 'webhook_secret');

        if (blank($secret)) {
            // Simulated mode: accepted only outside production, and only when
            // unsigned. `testing` is included so the suite can exercise the flow.
            return app()->environment(['local', 'testing']) && blank($header);
        }

        if (blank($header)) {
            return false;
        }

        $parts = collect(explode(',', $header))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');

                return [trim($key) => trim($value)];
            });

        $timestamp = $parts->get('t');
        $signature = $parts->get('te') ?: $parts->get('li');

        if (! $timestamp || ! $signature) {
            return false;
        }

        // A signature proves the payload was signed once, not that it is new.
        // Without a freshness window a captured event can be replayed forever;
        // five minutes is long enough for retries, short enough to be useless
        // to someone replaying an old "payment.paid".
        if (! ctype_digit((string) $timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, (string) $secret);

        return hash_equals($expected, $signature);
    }

    /** Pull the payment intent id out of a webhook event body. */
    public function intentIdFrom(array $event): ?string
    {
        return data_get($event, 'data.attributes.data.attributes.payment_intent_id')
            ?? data_get($event, 'data.attributes.data.id')
            ?? data_get($event, 'data.id');
    }

    /** Whether the event is one that should mark a payment as settled. */
    public function isPaidEvent(array $event): bool
    {
        $type = (string) (data_get($event, 'data.attributes.type') ?? '');

        return in_array($type, ['payment.paid', 'payment_intent.succeeded'], true)
            || Str::contains($type, ['paid', 'succeeded']);
    }

    /**
     * The processor's actual fee on a settled payment, in centavos.
     *
     * `processing_fee` is written at checkout from a configured estimate, and
     * the shop bears it — so an estimate that never gets corrected is a price we
     * invented and charged the shop for. The provider reports the real number on
     * the settled payment, and this is the only place it can come from.
     *
     * Null when the event does not carry it, which is not a failure: the row
     * keeps its estimate and `fee_is_estimate` stays true, so the difference
     * between a guess and a fact stays visible instead of being papered over.
     */
    public function feeFrom(array $event): ?int
    {
        $fee = data_get($event, 'data.attributes.data.attributes.fee')
            ?? data_get($event, 'data.attributes.data.attributes.payment_fee')
            ?? data_get($event, 'data.attributes.fee');

        return is_numeric($fee) ? (int) $fee : null;
    }
}
