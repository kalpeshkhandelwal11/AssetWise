<?php

namespace App\Providers;

use App\Services\DynamicFieldService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton so resolveForCategory()'s per-request memoization holds across
        // multiple injection points (e.g. controller + view) within one request.
        $this->app->singleton(DynamicFieldService::class);
    }

    public function boot(): void
    {
        // Scanning: tag holders, plus location managers receiving deliveries (Phase 2) — they
        // need no tags.view to confirm an asset arrived at their location.
        $managesALocation = fn (\App\Models\User $user) => \App\Models\Location::whereIn(
            'manager_employee_id', \App\Models\Employee::where('user_id', $user->id)->select('id')
        )->exists();

        \Illuminate\Support\Facades\Gate::define('scan.use', fn (\App\Models\User $user) => $user->can('tags.view') || $managesALocation($user));

        // Who sees "Incoming" (Phase 2): stores/admins, or anyone managing a location.
        \Illuminate\Support\Facades\Gate::define('movement.receive', fn (\App\Models\User $user) => $user->can('movement.verify') || $managesALocation($user));

        // Global password policy: min 8, mixed case, number, symbol
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // Global session default: 8 hours
        config(['session.lifetime' => 480]);

        // Login/Failed listeners are auto-discovered from app/Listeners/ via handle() type-hints
    }
}
