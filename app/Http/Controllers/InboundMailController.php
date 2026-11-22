<?php

namespace App\Http\Controllers;

use App\Services\InboundMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives a forwarded email and files it against its job.
 *
 * The body must be sent as JSON and must be signed. Both are requirements, not
 * preferences: the signature covers the exact bytes, so a form-encoded body —
 * which PHP parses and consumes before the application sees it — cannot be
 * verified at all.
 *
 * Answers 202 for a message that matched nothing, as well as for one that was
 * filed. A 404 would be accurate and would also make every provider retry a
 * message that can never match, forever, while filling the error log.
 */
class InboundMailController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! InboundMail::verify($payload, $request->header('X-Inbound-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $message = json_decode($payload, true);

        if (! is_array($message)) {
            return response()->json(['message' => 'Malformed payload.'], 422);
        }

        return response()->json([
            'received' => true,
            'matched' => InboundMail::accept($message) !== null,
        ], 202);
    }
}
