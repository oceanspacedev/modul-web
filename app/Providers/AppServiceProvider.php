<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
        $this->configureAuthorization();

        Password::defaults(fn () => Password::min(8));
    }

    /**
     * Surface N+1 queries outside production without breaking pages.
     */
    protected function configureModels(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
            Log::warning('Lazy loaded ['.$relation.'] on model ['.$model::class.'].');
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Password login: per account and per IP, answered with a normal form error.
        RateLimiter::for('login', function (Request $request) {
            $username = Str::lower((string) $request->input('username'));
            $tooManyAttempts = fn () => back()
                ->withInput($request->only('username'))
                ->with('error', 'Terlalu banyak percobaan login. Silakan coba lagi dalam 1 menit.');

            return [
                Limit::perMinute(5)->by('login:'.$username.'|'.$request->ip())->response($tooManyAttempts),
                Limit::perMinute(20)->by('login-ip:'.$request->ip())->response($tooManyAttempts),
            ];
        });

        // Sending OTPs costs a WhatsApp message; the controller also enforces a 60s cooldown per account.
        RateLimiter::for('otp-request', fn (Request $request) => Limit::perMinute(10)->by('otp-request:'.$request->ip()));

        // Verifying is keyed by account too, so rotating IPs cannot brute-force a 6-digit code.
        RateLimiter::for('otp-verify', function (Request $request) {
            $username = Str::lower((string) $request->input('username'));

            return [
                Limit::perMinute(5)->by('otp-verify:'.$username),
                Limit::perMinute(20)->by('otp-verify-ip:'.$request->ip()),
            ];
        });

        // The participant portal polls its status every 5 seconds.
        RateLimiter::for('portal', fn (Request $request) => Limit::perMinute(120)->by('portal:'.$request->route('token').'|'.$request->ip()));
    }

    /**
     * Configure application-wide authorization rules.
     */
    protected function configureAuthorization(): void
    {
        // Implicitly grant the "Admin" role (and job level 1) every ability.
        Gate::before(fn (User $user) => $user->isAdministrator() ? true : null);
    }
}
