<?php

namespace App\Http\Middleware;

use App\Support\HelpdeskConnection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API helpdesk: hanya panggilan dengan header Authorization: Bearer {token}. Token berasal dari kode koneksi di
 * Pengaturan Helpdesk (atau HELPDESK_API_TOKEN di .env sebagai cadangan). Selama belum ada token, semua panggilan ditolak.
 */
class AuthenticateHelpdesk
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! HelpdeskConnection::tokenMatches((string) $request->bearerToken())) {
            return response()->json(['message' => 'Token integrasi tidak valid.'], 401);
        }

        return $next($request);
    }
}
