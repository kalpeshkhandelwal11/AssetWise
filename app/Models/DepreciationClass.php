<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A Companies Act Schedule II asset class — supplies the default useful life. */
class DepreciationClass extends Model
{
    /** Seeded from the company's FY 26-27 depreciation register (Schedule II sheet). */
    public const SCHEDULE_II = [
        ['code' => 'RES_BUILDING',      'name' => 'Residential Building',       'useful_life_years' => 60],
        ['code' => 'OFFICE_BUILDING',   'name' => 'Factory/ Office Building',   'useful_life_years' => 30],
        ['code' => 'TEMP_STRUCTURE',    'name' => 'Temporary Structure',        'useful_life_years' => 3],
        ['code' => 'PLANT_MACHINERY',   'name' => 'Plant & Machinery',          'useful_life_years' => 15],
        ['code' => 'CONTINUOUS_PLANT',  'name' => 'Continuous Process Plant',   'useful_life_years' => 25],
        ['code' => 'ELECTRICAL',        'name' => 'Electrical Installation',    'useful_life_years' => 10],
        ['code' => 'COMPUTERS',         'name' => 'Computers',                  'useful_life_years' => 3],
        ['code' => 'SERVERS_NETWORKS',  'name' => 'Servers & Networks',         'useful_life_years' => 6],
        ['code' => 'FURNITURE',         'name' => 'Furniture & Fixtures',       'useful_life_years' => 10],
        ['code' => 'OFFICE_EQUIPMENT',  'name' => 'Office Equipment',           'useful_life_years' => 5],
        ['code' => 'MOTOR_CAR',         'name' => 'Motor Car (Non-Commercial)', 'useful_life_years' => 8],
        ['code' => 'MOTOR_CAR_COMM',    'name' => 'Motor Car (Commercial)',     'useful_life_years' => 6],
        ['code' => 'MOTOR_CYCLE',       'name' => 'Motor Cycle',                'useful_life_years' => 10],
        ['code' => 'LAND',              'name' => 'Land',                       'useful_life_years' => null, 'is_depreciable' => false],
    ];

    protected $fillable = ['name', 'code', 'useful_life_years', 'is_depreciable', 'is_active'];

    protected function casts(): array
    {
        return [
            'useful_life_years' => 'decimal:2',
            'is_depreciable'    => 'boolean',
            'is_active'         => 'boolean',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(AssetCategory::class);
    }

    public function usefulLifeMonths(): ?int
    {
        return $this->useful_life_years ? (int) round((float) $this->useful_life_years * 12) : null;
    }

    /** Straight-line rate as Schedule II shows it: 100 ÷ life (years). */
    public function ratePercent(): ?float
    {
        return $this->useful_life_years ? round(100 / (float) $this->useful_life_years, 4) : null;
    }
}
