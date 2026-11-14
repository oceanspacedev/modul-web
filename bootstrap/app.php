<?php

use App\Http\Middleware\LastUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Honour X-Forwarded-* headers from the reverse proxy / load balancer.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);

        $middleware->web(append: [
            LastUserActivity::class,
        ]);

        // Portal peserta diautentikasi oleh token rahasia di alamatnya, bukan oleh cookie, jadi token CSRF tidak
        // menambah perlindungan. Tanpa pengecualian ini peserta yang membiarkan halaman terbuka selama sesi (lebih
        // lama dari masa sesi) mendapat "Halaman Kadaluwarsa" saat mengirim presensi, evaluasi, atau kuis.
        $middleware->preventRequestForgery(except: ['training/portal/*']);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Unauthenticated browser requests go to the login page; authenticated
        // users hitting a "guest" route (the login page) are sent home. "/" is the
        // login page itself, so home is the dashboard, or "Pelatihan Saya" for roles
        // without dashboard access (the dashboard would bounce them back here).
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homeUrl() ?? route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson(),
        );

        // Kiriman peserta yang melebihi batas ukuran server (foto presensi terlalu besar): kembali ke portal dengan
        // pesan, bukan halaman error 413. Terjadi sebelum sesi dimulai, jadi pesannya dibawa lewat alamat.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if (! $request->is('training/portal/*') || $request->expectsJson()) {
                return null;
            }

            return redirect('/training/portal/'.$request->segment(3).'?unggahan=terlalu-besar');
        });

        // Policy / "can" failures in the browser: go back with a readable message instead of a bare 403 page.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return back(fallback: route('training.my-trainings'))
                ->with('error', 'Role akun Anda tidak memiliki hak akses (permission) untuk membuka fitur ini.');
        });
    })->create();
