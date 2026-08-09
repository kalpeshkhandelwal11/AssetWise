<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\TagBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'batch_id'   => TagBatch::factory(),
            'code_type'  => 'qr',
            'status'     => 'available',
        ];
    }

    /** Mirrors TagService::generateBatch()'s own-id-derived numbering scheme. */
    public function configure(): static
    {
        return $this->afterCreating(function (Tag $tag) {
            if ($tag->tag_number) {
                return;
            }

            $tagNumber = str_pad((string) $tag->id, 6, '0', STR_PAD_LEFT);

            $tag->update([
                'tag_number'    => $tagNumber,
                'qr_payload'    => $tag->code_type === 'qr' ? url("/scan/{$tagNumber}") : null,
                'barcode_value' => $tag->code_type === 'barcode' ? $tagNumber : null,
            ]);
        });
    }

    public function available(): static
    {
        return $this->state(fn () => ['status' => 'available']);
    }

    public function assigned(): static
    {
        return $this->state(fn () => ['status' => 'assigned']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function barcode(): static
    {
        return $this->state(fn () => ['code_type' => 'barcode']);
    }
}
