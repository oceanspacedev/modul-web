<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Masuk sebagai user lain (lab404/laravel-impersonate) lewat POST agar terlindungi CSRF,
 * bukan rute GET bawaan paket.
 */
class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonateManager $manager) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        // Gate::before meloloskan super admin, jadi larangan ini dicek di sini juga.
        Gate::denyIf($this->manager->isImpersonating(), 'Anda sedang masuk sebagai user lain. Kembali ke akun Anda dulu.');
        Gate::denyIf($actor->is($user), 'Anda tidak dapat masuk sebagai diri sendiri.');
        Gate::denyIf(! $user->canBeImpersonated(), 'User ini sudah diarsipkan, jadi tidak bisa dimasuki.');
        Gate::authorize('impersonate', $user);

        if (! $this->manager->take($actor, $user)) {
            return back()->with('error', 'Gagal masuk sebagai '.$user->nama_lengkap.'.');
        }

        $request->session()->regenerate();
        Log::info('Impersonate dimulai', ['impersonator_id' => $actor->getKey(), 'user_id' => $user->getKey()]);

        return redirect()->to($user->homeUrl())->with('info', 'Anda masuk sebagai '.$user->nama_lengkap.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::denyIf(! $this->manager->isImpersonating());

        $impersonated = $request->user();
        $impersonatorId = $this->manager->getImpersonatorId();

        // Akun asal sudah diarsipkan atau hilang: akhiri sesi sepenuhnya.
        if (! $this->manager->leave()) {
            $this->manager->clear();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Log::warning('Impersonate dipaksa berhenti: akun asal tidak aktif lagi', ['impersonator_id' => $impersonatorId, 'user_id' => $impersonated?->getKey()]);

            return to_route('login')->with('error', 'Sesi Anda sebagai user lain berakhir karena akun asal Anda sudah tidak aktif. Silakan masuk kembali.');
        }

        $request->session()->regenerate();
        Log::info('Impersonate selesai', ['impersonator_id' => $impersonatorId, 'user_id' => $impersonated?->getKey()]);

        $actor = $request->user();

        return redirect()->to($actor->can('viewAny', User::class) ? route('user.index') : $actor->homeUrl())
            ->with('success', 'Anda kembali ke akun sendiri.');
    }
}
