<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['name', 'code', 'address', 'is_active', 'manager_employee_id', 'is_toll_plaza', 'chainage'];

    /** Receives location transfers by scanning at this location; default plaza custodian. */
    public function manager(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_toll_plaza' => 'boolean'];
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }
}
