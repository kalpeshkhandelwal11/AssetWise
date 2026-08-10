<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\DisposalType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisposalRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id'         => Asset::factory(),
            'disposal_type_id' => fn () => DisposalType::firstOrCreate(
                ['code' => 'SCRAP'],
                ['name' => 'Scrap', 'is_active' => true],
            )->id,
            'reason'       => $this->faker->sentence(),
            'status'       => 'pending_approval',
            'requested_by' => User::factory(),
        ];
    }
}
