<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $request = request();

        $event->user->update(['last_login_at' => now()]);

        LoginHistory::create([
            'user_id'     => $event->user->id,
            'email'       => $event->user->email,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'device_type' => self::detectDevice($request->userAgent()),
            'status'      => 'success',
            'created_at'  => now(),
        ]);
    }

    private static function detectDevice(?string $ua): string
    {
        if (!$ua) return 'unknown';
        $ua = strtolower($ua);
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) return 'mobile';
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) return 'tablet';
        return 'desktop';
    }
}
