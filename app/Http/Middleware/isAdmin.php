<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class isAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Logout is always permitted for any authenticated user
        if ($request->is('logout')) {
            return $next($request);
        }

        // Admin or job_level_id 1 has universal access
        if ($user->job_level_id == 1 || $user->hasRole('Admin')) {
            return $next($request);
        }

        // Module permission checks based on route
        if ($request->is('training*') && $user->can('manage-trainings')) {
            return $next($request);
        }
        if ($request->is('video-manage*') && $user->can('manage-videos')) {
            return $next($request);
        }
        if ($request->is('document*') && ($user->can('manage-documents') || $user->can('view-documents'))) {
            return $next($request);
        }
        if (($request->is('quiz*') || $request->is('question*') || $request->is('option*')) && $user->can('manage-quizzes')) {
            return $next($request);
        }
        if ($request->is('absent*') && $user->can('manage-presence')) {
            return $next($request);
        }
        if ($request->is('dashboard*') && $user->can('view-dashboard')) {
            return $next($request);
        }
        if ($request->is('roles*') && $user->can('manage-roles')) {
            return $next($request);
        }
        if ($request->is('user*') && $user->can('manage-users')) {
            return $next($request);
        }
        if (($request->is('divisi*') || $request->is('subdivisi*') || $request->is('joblevel*') || $request->is('dokumentype*')) && $user->can('manage-master-data')) {
            return $next($request);
        }

        return back()->with('error', 'Role akun Anda tidak memiliki hak akses (permission) untuk membuka fitur ini.');
    }
}
