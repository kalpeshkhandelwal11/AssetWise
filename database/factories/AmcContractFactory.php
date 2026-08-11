<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AmcContractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id'   => Asset::factory(),
            'vendor'     => $this->faker->company(),
            'start_date' => now()->subMonths(6),
            'end_date'   => now()->addMonths(6),
            'coverage'   => $this->faker->sentence(),
            'cost'       => $this->faker->randomFloat(2, 500, 20000),
            'created_by' => User::factory(),
        ];
    }
}
