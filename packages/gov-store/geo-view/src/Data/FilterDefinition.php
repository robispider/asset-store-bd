<?php

namespace GovStore\GeoView\Data;

/** A source-declared filter (code, plan, fiscal year), shown in the toolbar or bound to the host form. */
final class FilterDefinition
{
    /** @param  array<int, array{value:string|int, label:string}>  $options */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $options = [],
    ) {}

    public function allows(mixed $value): bool
    {
        return in_array((string) $value, array_map(fn ($o) => (string) $o['value'], $this->options), true);
    }

    public function toArray(mixed $current = null): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'value' => $current === null ? null : (string) $current,
            'options' => array_map(fn ($o) => ['value' => $o['value'], 'label' => (string) $o['label']], $this->options)];
    }
}
