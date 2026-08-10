<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\MovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id'         => Asset::factory(),
            'movement_type_id' => fn () => MovementType::firstOrCreate(
                ['code' => 'ASSIGNMENT'],
                ['name' => 'Assignment', 'is_active' => true],
            )->id,
            'status'       => 'pending_approval',
            'requested_by' => User::factory(),
        ];
    }
}
