<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'legal_name', 'code', 'parent_company_id', 'is_head_office',
        'address', 'city', 'country',
        'gstin', 'pan', 'cin',
        'registered_address', 'registered_city', 'registered_state',
        'registered_pincode', 'registered_country',
        'contact_name', 'contact_email', 'contact_phone', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'is_head_office' => 'boolean',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function parentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'parent_company_id');
    }

    public function childCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'parent_company_id');
    }
}
