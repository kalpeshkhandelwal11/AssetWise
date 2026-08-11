<?php

namespace App\Models;

use App\Services\Depreciation\DepreciationCalculatorInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepreciationMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'calculator_class', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Strategy-pattern resolver: instantiate the calculator bound to this method. */
    public function calculator(): DepreciationCalculatorInterface
    {
        return app($this->calculator_class);
    }
}
