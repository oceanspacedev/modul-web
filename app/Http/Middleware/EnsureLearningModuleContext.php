<?php

namespace App\Http\Middleware;

use App\Models\LearningModule;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kuis video, kuis dokumen, dan Modul Belajar memakai controller yang sama tetapi alamat sendiri-sendiri.
 * Modul yang dibuka lewat alamat konteks lain (mis. link lama /modul-saya/... untuk kuis video) dialihkan ke
 * alamatnya yang benar bila halaman itu ada; selain itu 404.
 */
class EnsureLearningModuleContext
{
    /**
     * @param  string  $context  standalone | video | document
     */
    public function handle(Request $request, Closure $next, string $context): Response
    {
        $route = $request->route();
        $module = $route->parameter('module');

        if (! $module instanceof LearningModule || $module->routeContext() === $context) {
            return $next($request);
        }

        $target = $module->routeName(LearningModule::actionFromRouteName($route->getName()));
        abort_unless($request->isMethod('GET') && Route::has($target), 404);

        return redirect()->route($target, $route->parameters());
    }
}
