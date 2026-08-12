<?php

namespace App\Models;

use App\Support\NotificationCatalog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    protected $fillable = [
        'name',
        'email',
        'password',
        'mfa_enabled',
        'must_change_password',
        'password_changed_at',
        'is_active',
        'last_login_at',
        'department_id',
        'branch_id',
        'designation_id',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function custodiedAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'custodian_id');
    }

    public function createdAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'created_by');
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Reads the LOADED relation when present (NotificationService::sendMany() eager-loads
     * it once per batch to avoid a query per recipient) and falls back to a fresh query
     * only when called standalone. Missing row = inherits the catalog default.
     */
    public function wantsEmailFor(string $type): bool
    {
        $preferences = $this->relationLoaded('notificationPreferences')
            ? $this->notificationPreferences
            : $this->notificationPreferences()->where('type', $type)->get();

        $preference = $preferences->firstWhere('type', $type);

        if ($preference) {
            return $preference->email_enabled;
        }

        return in_array('mail', app(NotificationCatalog::class)->channels($type), true);
    }

    // Convenience: highest session_lifetime_minutes across all roles (null = global default)
    public function sessionLifetimeMinutes(): ?int
    {
        $max = $this->roles->max('session_lifetime_minutes');
        return $max ?? null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'email', 'is_active', 'mfa_enabled', 'must_change_password',
                'department_id', 'branch_id', 'designation_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user');
    }
}
