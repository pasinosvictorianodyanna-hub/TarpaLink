<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The CMS may act on the engine, but only over this API and only with its token.
 *
 * Kept separate from the content token so the blast radius of a leak is one
 * direction: the content token reads copy, this one approves shops and releases
 * money. A single shared secret would mean an editor's browser could do both.
 */
class VerifyAdminApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.admin_api.token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
