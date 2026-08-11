<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarrantyRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id'   => Asset::factory(),
            'provider'   => $this->faker->company(),
            'start_date' => now()->subMonths(3),
            'end_date'   => now()->addMonths(9),
            'terms'      => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
