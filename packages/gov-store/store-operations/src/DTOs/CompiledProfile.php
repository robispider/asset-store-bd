<?php

namespace GovStore\StoreOperations\DTOs;

use GovStore\StoreOperations\Services\CapabilityRegistry;

class CompiledProfile
{
    protected array $snapshot = [];

    public function __construct(array $snapshot = [])
    {
        // Force cast snapshot to array to prevent any scalar/boolean crashes
        $this->snapshot = is_array($snapshot) ? $snapshot : [];
    }

    /**
     * Core lookup: Returns all active capability codes and their configs for an item.
     * GUARANTEES a clean array output under any circumstances.
     */
    public function getCapabilitiesForProduct(string $productType, int $productId): array
    {
        $key = "{$productType}_{$productId}";
        $caps = null;

        // 1. Check nested 'items' snapshot array
        if (isset($this->snapshot['items'][$key])) {
            $caps = $this->snapshot['items'][$key];
        } 
        // 2. Check root array fallback
        elseif (isset($this->snapshot[$key])) {
            $caps = $this->snapshot[$key];
        }

        // SECURE TYPECAST: If the snapshot has corrupted boolean or scalar values,
        // force-cast them to a safe empty array to prevent downstream foreach crashes.
        return is_array($caps) ? $caps : [];
    }

    /**
     * Returns presentation capabilities formatted for the Blade Workspace view.
     */
    public function getPresentationCapabilities(string $productType, int $productId): array
    {
        $itemCaps = $this->getCapabilitiesForProduct($productType, $productId);
        $presentationCaps = [];

        foreach ($itemCaps as $code => $config) {
            // Ensure array structure is [ 'code' => '...', 'config' => [...] ]
            $capCode = is_array($config) && isset($config['code']) ? $config['code'] : $code;
            $capConfig = is_array($config) && isset($config['config']) ? $config['config'] : (is_array($config) ? $config : []);

            $presentationCaps[] = [
                'code'   => $capCode,
                'config' => $capConfig
            ];
        }

        return $presentationCaps;
    }

    /**
     * Alias for Materialization Pipeline execution steps.
     */
    public function getPipelineCapabilities(string $productType, int $productId): array
    {
        return $this->getCapabilitiesForProduct($productType, $productId);
    }

    public function toArray(): array
    {
        return $this->snapshot;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}