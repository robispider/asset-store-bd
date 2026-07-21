<?php

namespace GovStore\StoreOperations\DTOs;

class CompiledProfile
{
    protected array $snapshot;

    public function __construct(array $snapshot)
    {
        $this->snapshot = $snapshot;
    }

    /**
     * Returns Laravel-style validation arrays for the workspace grid.
     * Generates: 'items.consumable_12.*.serial_number' => 'required|unique:...'
     */
    public function getValidationRules(): array
    {
        $rules = [];
        foreach ($this->snapshot['items'] ?? [] as $item) {
            $itemKey = $item['product_type'] . '_' . $item['product_id'];
            
            foreach ($item['requirements'] ?? [] as $req) {
                // Creates array-validation rules for dynamic UI inputs
                $rules['items.' . $itemKey . '.*.' . $req['key']] = $req['rules'];
            }
        }
        return $rules;
    }

    /**
     * Extract the executable capability codes for a specific document item.
     */
    public function getPipelineCapabilities(string $productType, int $productId): array
    {
        foreach ($this->snapshot['items'] ?? [] as $item) {
            if ($item['product_type'] === $productType && $item['product_id'] === $productId) {
                return $item['capabilities'] ?? [];
            }
        }
        return [];
    }

    /**
     * Get raw array representation.
     */
    public function toArray(): array
    {
        return $this->snapshot;
    }
}