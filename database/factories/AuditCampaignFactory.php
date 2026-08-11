<?php

namespace Database\Factories;

use App\Models\AuditType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditCampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => 'Audit — '.$this->faker->words(2, true),
            'audit_type_id' => fn () => AuditType::firstOrCreate(
                ['code' => 'PHYSICAL'],
                ['name' => 'Physical', 'is_active' => true],
            )->id,
            'description' => $this->faker->sentence(),
            'start_date'  => now()->toDateString(),
            'scope'       => [],
            'status'      => 'draft',
            'created_by'  => User::factory(),
        ];
    }
}
