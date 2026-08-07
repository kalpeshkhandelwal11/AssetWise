<?php

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    public function definition(): array
    {
        return [
            'workflow_id'             => ApprovalWorkflow::factory(),
            'approvable_type'         => Asset::class,
            'approvable_id'           => Asset::factory(),
            'status'                  => 'pending',
            'current_step'            => 1,
            'current_step_started_at' => now(),
            'submitted_by'            => User::factory(),
        ];
    }
}
