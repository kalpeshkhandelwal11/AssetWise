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
        'contact_name', 'contact_email', 'contact_phone', 'is_active', 'party_type',
    ];

    public const PARTY_TYPES = [
        'internal'           => 'Our company',
        'external_authority' => 'Authority (e.g. NHAI)',
        'external_agency'    => 'External party (agency / buyer / seller)',
    ];

    /** Not one of our own companies — NHAI, a previous toll agency, a buyer. */
    public function isExternal(): bool
    {
        return ($this->party_type ?? 'internal') !== 'internal';
    }

    public function isAuthority(): bool
    {
        return $this->party_type === 'external_authority';
    }

    public function scopeInternal(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('party_type', 'internal');
    }

    public function scopeExternal(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('party_type', '!=', 'internal');
    }

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'is_head_office' => 'boolean',
        ];
    }

    /** Companies the current actor may see (all when unrestricted) — use for pickers and filters. */
    public function scopeVisible(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $ids = \App\Support\CompanyVisibility::ids();

        if ($ids !== null) {
            $query->whereIn($this->getTable().'.id', $ids);
        }
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
