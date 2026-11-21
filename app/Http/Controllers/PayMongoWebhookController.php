<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPayMongoWebhook;
use App\Services\PayMongo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives PayMongo events. Verifies the signature, then hands the body to a
 * queued job so a slow or failing consumer cannot make PayMongo retry the whole
 * request. Always answers 200 once the signature is valid — the queue owns the
 * retry policy, not the provider.
 */
class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, PayMongo $paymongo): JsonResponse
    {
        $payload = $request->getContent();

        if (! $paymongo->verifySignature($payload, $request->header('Paymongo-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response()->json(['message' => 'Malformed payload.'], 422);
        }

        ProcessPayMongoWebhook::dispatch($event);

        return response()->json(['received' => true]);
    }
}
