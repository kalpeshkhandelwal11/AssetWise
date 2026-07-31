<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'address', 'city', 'country',
        'contact_name', 'contact_email', 'contact_phone', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
