<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\MaintenanceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenanceRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id'             => Asset::factory(),
            'maintenance_type_id'  => fn () => MaintenanceType::firstOrCreate(
                ['code' => 'PREVENTIVE'],
                ['name' => 'Preventive', 'is_active' => true],
            )->id,
            'status'      => 'scheduled',
            'vendor'      => $this->faker->company(),
            'cost'        => $this->faker->randomFloat(2, 50, 5000),
            'description' => $this->faker->sentence(),
            'logged_by'   => User::factory(),
        ];
    }
}
