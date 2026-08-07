<?php

namespace Database\Factories;

use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalWorkflowFactory extends Factory
{
    protected $model = ApprovalWorkflow::class;

    public function definition(): array
    {
        return [
            'name'      => ucfirst($this->faker->unique()->words(2, true)) . ' Workflow',
            'module'    => 'transfer',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function module(string $module): static
    {
        return $this->state(fn () => ['module' => $module]);
    }
}
