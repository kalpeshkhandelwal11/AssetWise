<?php

namespace Database\Factories;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalStepFactory extends Factory
{
    protected $model = ApprovalStep::class;

    public function definition(): array
    {
        return [
            'workflow_id'      => ApprovalWorkflow::factory(),
            'level'            => 1,
            'approver_type'    => 'role',
            'approver_role'    => 'Approver',
            'approver_user_id' => null,
            'escalation_hours' => null,
        ];
    }

    public function forRole(string $role, int $level = 1, ?int $escalationHours = null): static
    {
        return $this->state(fn () => [
            'level'            => $level,
            'approver_type'    => 'role',
            'approver_role'    => $role,
            'approver_user_id' => null,
            'escalation_hours' => $escalationHours,
        ]);
    }

    public function forUser(User $user, int $level = 1, ?int $escalationHours = null): static
    {
        return $this->state(fn () => [
            'level'            => $level,
            'approver_type'    => 'user',
            'approver_role'    => null,
            'approver_user_id' => $user->id,
            'escalation_hours' => $escalationHours,
        ]);
    }
}
