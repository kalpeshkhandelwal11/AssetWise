<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetFieldValue extends Model
{
    protected $fillable = ['asset_id', 'category_field_id', 'value_text', 'value_number', 'value_date', 'value_boolean'];

    protected function casts(): array
    {
        return [
            'value_number'  => 'decimal:4',
            'value_date'    => 'date',
            'value_boolean' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    // category_field_id links to the definition even after it's soft-deleted (never hard-deleted while referenced).
    public function categoryField(): BelongsTo
    {
        return $this->belongsTo(CategoryField::class)->withTrashed();
    }

    // Raw value in the shape an HTML form input expects, used to prefill the edit form.
    public function rawValue(): mixed
    {
        return match ($this->categoryField?->field_type) {
            'boolean' => $this->value_boolean ? '1' : '0',
            'date'    => $this->value_date?->format('Y-m-d'),
            'number'  => $this->trimmedNumber(),
            default   => $this->value_text,
        };
    }

    public function displayValue(): string
    {
        $type = $this->categoryField?->field_type;

        return match ($type) {
            'boolean' => $this->value_boolean ? 'Yes' : 'No',
            'date'    => $this->value_date?->format('d M Y') ?? '—',
            'number'  => $this->trimmedNumber() ?? '—',
            'dropdown' => $this->categoryField?->options()->where('option_value', $this->value_text)->value('option_label') ?? $this->value_text ?? '—',
            default   => $this->value_text ?? '—',
        };
    }

    // The decimal:4 cast always formats with 4 fixed decimal places (e.g. "8.0000");
    // trim trailing zeros so plain integers don't look like decimals to the user.
    private function trimmedNumber(): ?string
    {
        if ($this->value_number === null) {
            return null;
        }

        return rtrim(rtrim((string) $this->value_number, '0'), '.') ?: '0';
    }
}
