<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlazaHandoverLine extends Model
{
    protected $fillable = [
        'handover_id', 'asset_id', 'action', 'sale_price', 'buyer_company_id', 'buyer_name',
        'to_location_id', 'to_custodian_id', 'condition', 'remarks', 'nbv_snapshot',
        'disposal_request_id', 'movement_id', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price'   => 'decimal:2',
            'nbv_snapshot' => 'decimal:2',
            'applied_at'   => 'datetime',
        ];
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(PlazaHandover::class, 'handover_id');
    }

    /** Unscoped: the handover screen must list every asset at the plaza, incl. NHAI-owned. */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_company_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function toCustodian(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_custodian_id');
    }
}
