<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AssetCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id'   => null,
            'name'        => ucfirst($this->faker->unique()->word()),
            'code'        => strtoupper($this->faker->unique()->lexify('CAT????')),
            'description' => null,
            'is_active'   => true,
            'sort_order'  => 0,
        ];
    }
}
