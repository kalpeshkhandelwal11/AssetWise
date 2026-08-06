<?php

namespace Database\Factories;

use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => $this->faker->words(3, true),
            'serial_number' => strtoupper($this->faker->unique()->bothify('SN-########')),
            'company_id'    => Company::factory(),
            'category_id'   => AssetCategory::factory(),
            'asset_type_id' => fn () => AssetType::create([
                'name' => ucfirst($this->faker->unique()->word()),
                'code' => strtoupper($this->faker->unique()->lexify('TYPE????')),
                'is_active' => true,
            ])->id,
            'status_id' => fn () => AssetStatus::create([
                'name'      => ucfirst($this->faker->unique()->word()),
                'code'      => strtoupper($this->faker->unique()->lexify('STAT????')),
                'color'     => '#22c55e',
                'is_system' => false,
                'is_active' => true,
            ])->id,
            'is_active'  => true,
            'created_by' => User::factory(),
        ];
    }
}
