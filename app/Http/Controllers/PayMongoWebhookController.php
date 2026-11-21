<?php

namespace App\Http\Controllers;

use App\Services\PayMongoWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PayMongo callback endpoint.
 *
 * Nothing in here trusts the payload until the signature checks out. With no
 * secret configured the endpoint is open only outside production, so a missing
 * env var fails closed rather than exposing a "mark anything paid" route.
 */
class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, PayMongoWebhookHandler $handler): JsonResponse
    {
        $raw = $request->getContent();

        if (! $this->signatureValid($request, $raw)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Malformed payload.'], 400);
        }

        $log = $handler->handle($payload, $raw);

        // 202 tells the provider "received, not settled" — it should keep
        // retrying, and so do we.
        return response()->json([
            'status' => $log->status,
            'webhook_id' => $log->id,
        ], $log->status === 'failed' ? 202 : 200);
    }

    private function signatureValid(Request $request, string $raw): bool
    {
        $secret = (string) config('services.paymongo.webhook_secret');

        if ($secret === '') {
            return ! app()->environment('production');
        }

        $header = (string) $request->header('Paymongo-Signature', '');

        if ($header === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[trim($key)] = trim($value);
            }
        }

        $timestamp = $parts['t'] ?? null;

        if (! $timestamp) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$raw, $secret);

        // `te` is the test-mode signature, `li` the live one. Accept either: the
        // same code path runs against sandbox and live keys.
        foreach (['te', 'li'] as $slot) {
            if (isset($parts[$slot]) && hash_equals($expected, $parts[$slot])) {
                return true;
            }
        }

        return false;
    }
}
