<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The canonical "person who can hold an asset" (custodian / assignee). An employee is NOT
 * necessarily a system user — the optional user_id links those who also log in. Custodian
 * FKs across assets/movements/kits reference this table (see the repoint migration).
 */
class Employee extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name', 'employee_code', 'email', 'phone', 'user_id',
        'company_id', 'department_id', 'branch_id', 'designation_id',
        'date_of_joining', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_of_joining' => 'date',
            'is_active'       => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('employee');
    }
}
