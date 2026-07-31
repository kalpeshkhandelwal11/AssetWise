<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            auth()->check()
            && auth()->user()->must_change_password
            && !$request->routeIs('password.force-change', 'password.force-change.update', 'logout')
        ) {
            return redirect()->route('password.force-change')
                ->with('info', 'You must set a new password before continuing.');
        }

        return $next($request);
    }
}
