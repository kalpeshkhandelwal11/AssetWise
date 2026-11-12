<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetFieldValue;
use App\Models\CategoryField;
use App\Models\CategoryFieldOverride;
use App\Services\DynamicFields\ResolvedField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DynamicFieldService
{
    /** @var array<int, Collection<int, ResolvedField>> */
    protected array $cache = [];

    /**
     * @return Collection<int, ResolvedField>
     */
    public function resolveForCategory(int $categoryId): Collection
    {
        if (isset($this->cache[$categoryId])) {
            return $this->cache[$categoryId];
        }

        $categoryIds = $this->ancestorChain($categoryId);

        $definitions = CategoryField::whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->with(['options' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('display_order')
            ->get();

        $overrides = CategoryFieldOverride::where('category_id', $categoryId)
            ->whereIn('category_field_id', $definitions->pluck('id'))
            ->get()
            ->keyBy('category_field_id');

        $resolved = $definitions
            ->reject(fn (CategoryField $field) => $overrides->get($field->id)?->override_type === 'hide')
            ->map(function (CategoryField $field) use ($overrides, $categoryId) {
                $override = $overrides->get($field->id);

                return new ResolvedField(
                    id: $field->id,
                    fieldKey: $field->field_key,
                    label: $override?->override_type === 'relabel' ? $override->override_label : $field->label,
                    fieldType: $field->field_type,
                    isRequired: $override?->override_type === 'change_required' ? (bool) $override->is_required : $field->is_required,
                    validationRules: $field->validation_rules ?? [],
                    displayOrder: $field->display_order,
                    isSearchable: $field->is_searchable,
                    options: $field->options->map(fn ($o) => ['value' => $o->option_value, 'label' => $o->option_label])->values()->all(),
                    definedOnCategoryId: $field->category_id,
                    isInherited: $field->category_id !== $categoryId,
                    isUnique: (bool) $field->is_unique,
                );
            })
            ->sortBy(fn (ResolvedField $f) => $f->displayOrder)
            ->values();

        return $this->cache[$categoryId] = $resolved;
    }

    /**
     * @return int[] category ids from root to leaf, inclusive of $categoryId
     */
    private function ancestorChain(int $categoryId): array
    {
        $chain = [];
        $cursor = AssetCategory::find($categoryId);

        while ($cursor) {
            array_unshift($chain, $cursor->id);
            $cursor = $cursor->parent_id ? AssetCategory::find($cursor->parent_id) : null;
        }

        return $chain;
    }

    /**
     * @param array<string, mixed> $input keyed by field_key
     * @param Collection<int, ResolvedField> $fields
     * @return array<string, mixed> validated data, keyed by field_key
     */
    public function validate(array $input, Collection $fields, ?int $ignoreAssetId = null): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $rules[$field->fieldKey] = $this->rulesFor($field, $ignoreAssetId);
        }

        return Validator::make($input, $rules)->validate();
    }

    private function rulesFor(ResolvedField $field, ?int $ignoreAssetId = null): array
    {
        $rules = [$field->isRequired ? 'required' : 'nullable'];
        $vr = $field->validationRules;

        switch ($field->fieldType) {
            case 'text':
            case 'textarea':
                $rules[] = 'string';
                $rules[] = 'max:' . ($vr['max_length'] ?? 65535);
                break;
            case 'number':
                $rules[] = 'numeric';
                if (isset($vr['min'])) {
                    $rules[] = 'min:' . $vr['min'];
                }
                if (isset($vr['max'])) {
                    $rules[] = 'max:' . $vr['max'];
                }
                if (isset($vr['decimal_places'])) {
                    $rules[] = 'decimal:0,' . $vr['decimal_places'];
                }
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'boolean':
                // The form always posts 0 or 1, so plain "required" could never fail. A required
                // Yes/No field therefore means "must be ticked" (a confirmation).
                if ($field->isRequired) {
                    $rules[0] = 'accepted';
                }
                $rules[] = 'boolean';
                break;
            case 'dropdown':
                $rules[] = Rule::in(collect($field->options)->pluck('value')->all());
                break;
        }

        if (! empty($vr['regex'])) {
            $rules[] = 'regex:/' . $vr['regex'] . '/';
        }

        if ($field->isUnique) {
            $rules[] = function (string $attribute, mixed $value, \Closure $fail) use ($field, $ignoreAssetId) {
                if ($value === null || $value === '') {
                    return;
                }

                $clash = $this->findDuplicate($field, $value, $ignoreAssetId);

                if ($clash) {
                    // Don't reveal another company's asset through the error message.
                    $who = \App\Support\CompanyVisibility::canSee($clash->company_id) || \App\Support\CompanyVisibility::canSee($clash->operating_company_id)
                        ? 'asset ' . ($clash->asset_tag ?: $clash->name)
                        : 'another asset';
                    $fail("{$field->label} \"{$value}\" is already used by {$who}.");
                }
            };
        }

        return $rules;
    }

    /**
     * The asset that already holds $value for this field, if any. Global across companies
     * (uniqueness must hold system-wide, so company isolation is bypassed here), case-
     * insensitive for text, and ignoring soft-deleted assets. Inherited fields share one
     * category_field_id, so this spans every child category automatically.
     */
    public function findDuplicate(ResolvedField $field, mixed $value, ?int $ignoreAssetId = null): ?Asset
    {
        $query = AssetFieldValue::query()->where('category_field_id', $field->id);

        match ($field->fieldType) {
            'number' => $query->where('value_number', $value),
            'date'   => $query->whereDate('value_date', $value),
            default  => $query->whereRaw('LOWER(TRIM(value_text)) = ?', [mb_strtolower(trim((string) $value))]),
        };

        if ($ignoreAssetId) {
            $query->where('asset_id', '!=', $ignoreAssetId);
        }

        $assetId = $query->whereIn('asset_id', Asset::withoutGlobalScopes()->whereNull('deleted_at')->select('id'))
            ->value('asset_id');

        return $assetId ? Asset::withoutGlobalScopes()->find($assetId) : null;
    }

    /**
     * @param array<string, mixed> $input keyed by field_key
     * @param Collection<int, ResolvedField> $fields
     */
    public function saveValues(Asset $asset, array $input, Collection $fields): void
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field->fieldKey, $input)) {
                continue;
            }

            $raw = $input[$field->fieldKey];
            $isEmpty = $raw === null || $raw === '';

            if ($isEmpty) {
                AssetFieldValue::where('asset_id', $asset->id)
                    ->where('category_field_id', $field->id)
                    ->delete();
                continue;
            }

            $columns = ['value_text' => null, 'value_number' => null, 'value_date' => null, 'value_boolean' => null];

            match ($field->fieldType) {
                'text', 'textarea', 'dropdown' => $columns['value_text'] = $field->isUnique ? trim((string) $raw) : $raw,
                'number'  => $columns['value_number'] = $raw,
                'date'    => $columns['value_date'] = $raw,
                'boolean' => $columns['value_boolean'] = (bool) $raw,
                default   => null,
            };

            AssetFieldValue::updateOrCreate(
                ['asset_id' => $asset->id, 'category_field_id' => $field->id],
                $columns
            );
        }
    }

    public function searchQuery(Builder $query, string $fieldKey, mixed $value): Builder
    {
        $fieldIds = CategoryField::where('field_key', $fieldKey)->pluck('id');

        return $query->whereHas('fieldValues', function (Builder $q) use ($fieldIds, $value) {
            $q->whereIn('category_field_id', $fieldIds)
                ->where(function (Builder $q2) use ($value) {
                    $q2->where('value_text', $value)
                        ->orWhere('value_number', $value)
                        ->orWhere('value_date', $value)
                        ->orWhere('value_boolean', $value);
                });
        });
    }
}
