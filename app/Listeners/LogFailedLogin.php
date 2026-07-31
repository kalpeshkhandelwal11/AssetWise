<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        $request = request();

        LoginHistory::create([
            'user_id'        => $event->user?->id,
            'email'          => $event->credentials['email'] ?? 'unknown',
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'device_type'    => self::detectDevice($request->userAgent()),
            'status'         => 'failed',
            'failure_reason' => 'Invalid credentials',
            'created_at'     => now(),
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
