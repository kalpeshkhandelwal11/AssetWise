<?php

namespace App\Services\DynamicFields;

use JsonSerializable;

final class ResolvedField implements JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $fieldKey,
        public readonly string $label,
        public readonly string $fieldType,
        public readonly bool $isRequired,
        public readonly array $validationRules,
        public readonly int $displayOrder,
        public readonly bool $isSearchable,
        public readonly array $options,
        public readonly int $definedOnCategoryId,
        public readonly bool $isInherited,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id'                     => $this->id,
            'field_key'              => $this->fieldKey,
            'label'                  => $this->label,
            'field_type'             => $this->fieldType,
            'is_required'            => $this->isRequired,
            'validation_rules'       => $this->validationRules,
            'display_order'          => $this->displayOrder,
            'is_searchable'          => $this->isSearchable,
            'options'                => $this->options,
            'defined_on_category_id' => $this->definedOnCategoryId,
            'is_inherited'           => $this->isInherited,
        ];
    }
}
