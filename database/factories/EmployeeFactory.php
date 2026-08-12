<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => $this->faker->name(),
            'employee_code' => 'EMP-' . strtoupper($this->faker->unique()->bothify('####??')),
            'email'         => $this->faker->unique()->safeEmail(),
            'phone'         => $this->faker->phoneNumber(),
            'user_id'       => null,
            'is_active'     => true,
        ];
    }
}
