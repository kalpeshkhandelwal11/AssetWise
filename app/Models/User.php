<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'mfa_enabled',
        'must_change_password',
        'password_changed_at',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'mfa_enabled'          => 'boolean',
            'must_change_password' => 'boolean',
            'is_active'            => 'boolean',
            'password_changed_at'  => 'datetime',
            'last_login_at'        => 'datetime',
        ];
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function exportLogs(): HasMany
    {
        return $this->hasMany(ExportLog::class);
    }

    // Convenience: highest session_lifetime_minutes across all roles (null = global default)
    public function sessionLifetimeMinutes(): ?int
    {
        $max = $this->roles->max('session_lifetime_minutes');
        return $max ?? null;
    }
}
