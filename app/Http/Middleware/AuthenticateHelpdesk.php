<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API helpdesk: hanya panggilan dengan header Authorization: Bearer {HELPDESK_API_TOKEN}. Selama token belum diatur,
 * semua panggilan ditolak.
 */
class AuthenticateHelpdesk
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.helpdesk.api_token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Token integrasi tidak valid.'], 401);
        }

        return $next($request);
    }
}
