<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetStatus extends Model
{
    protected $fillable = ['name', 'code', 'color', 'is_system', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
