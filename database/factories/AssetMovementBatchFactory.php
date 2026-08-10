<?php

namespace Database\Factories;

use App\Models\MovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetMovementBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'movement_type_id' => fn () => MovementType::firstOrCreate(
                ['code' => 'ASSIGNMENT'],
                ['name' => 'Assignment', 'is_active' => true],
            )->id,
            'status'       => 'pending_approval',
            'requested_by' => User::factory(),
        ];
    }
}
