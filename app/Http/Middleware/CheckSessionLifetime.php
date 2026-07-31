<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return $next($request);
        }

        $lifetimeMinutes = auth()->user()->sessionLifetimeMinutes()
            ?? config('session.lifetime'); // default 480 min (8 hrs)

        $lastActivity = session('_last_activity_at');

        if ($lastActivity && now()->diffInMinutes($lastActivity) > $lifetimeMinutes) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your session has expired. Please log in again.');
        }

        session(['_last_activity_at' => now()]);

        return $next($request);
    }
}
