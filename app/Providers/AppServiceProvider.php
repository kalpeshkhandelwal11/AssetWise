<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Global password policy: min 8, mixed case, number, symbol
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // Global session default: 8 hours
        config(['session.lifetime' => 480]);

        // Login/Failed listeners are auto-discovered from app/Listeners/ via handle() type-hints
    }
}
