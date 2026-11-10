<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                 => fake()->name(),
            'email'                => fake()->unique()->safeEmail(),
            'email_verified_at'    => now(),
            'password'             => static::$password ??= Hash::make('Admin@1234'),
            'remember_token'       => Str::random(10),
            'mfa_enabled'          => false,
            'must_change_password' => false,
            'is_active'            => true,
            // Tests predate company isolation; restricted users opt in via restrictedTo().
            'all_companies'        => true,
        ];
    }

    /** A user who only sees the given companies. */
    public function restrictedTo(array|int $companyIds): static
    {
        return $this->state(['all_companies' => false])
            ->afterCreating(fn ($user) => $user->companies()->sync((array) $companyIds));
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }
}
