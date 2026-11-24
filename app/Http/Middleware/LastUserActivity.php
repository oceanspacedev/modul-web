<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records when a signed-in user was last active (users.last_seen, shown on the user list per minute). Written at
 * most once a minute: pages poll (live stats, notifications), and a write on every request is wasted work.
 */
class LastUserActivity
{
    public const WRITE_EVERY_SECONDS = 60;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $impersonate = app('impersonate');

        // Saat impersonate yang beraksi adalah adminnya, jadi aktivitas dicatat untuk akun asal, bukan karyawannya.
        $user = $impersonate->isImpersonating() ? User::find($impersonate->getImpersonatorId()) : Auth::user();

        if ($user instanceof User
            && ($user->last_seen === null || $user->last_seen->lte(now()->subSeconds(self::WRITE_EVERY_SECONDS)))) {
            // Satu query UPDATE (seperti sebelumnya, ikut mengisi updated_at), tanpa event model.
            User::whereKey($user->getKey())->update(['last_seen' => now()]);
            $user->setAttribute('last_seen', now());
            $user->syncOriginalAttribute('last_seen');
        }

        return $next($request);
    }
}
