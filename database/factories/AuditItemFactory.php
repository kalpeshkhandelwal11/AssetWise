<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AuditCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => AuditCampaign::factory(),
            'asset_id'    => Asset::factory(),
            'status'      => 'pending',
        ];
    }
}
