<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => $this->faker->company(),
            'code'          => strtoupper($this->faker->unique()->lexify('????')),
            'address'       => $this->faker->streetAddress(),
            'city'          => $this->faker->city(),
            'country'       => $this->faker->country(),
            'contact_name'  => $this->faker->name(),
            'contact_email' => $this->faker->companyEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'is_active'     => true,
        ];
    }
}
