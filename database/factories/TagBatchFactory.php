<?php

namespace Database\Factories;

use App\Models\TagBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagBatchFactory extends Factory
{
    protected $model = TagBatch::class;

    public function definition(): array
    {
        return [
            'quantity'   => 10,
            'code_type'  => 'qr',
            'created_by' => User::factory(),
        ];
    }

    public function barcode(): static
    {
        return $this->state(fn () => ['code_type' => 'barcode']);
    }
}
