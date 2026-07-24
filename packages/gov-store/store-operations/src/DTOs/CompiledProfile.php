<?php

namespace GovStore\StoreOperations\DTOs;

class CompiledProfile
{
    protected array $snapshot = [];

    public function __construct(array $snapshot = [])
    {
        // Force cast snapshot to array to prevent any scalar/boolean crashes
        $this->snapshot = is_array($snapshot) ? $snapshot : [];
    }

    /**
     * Gets RAW compiled capabilities (Used heavily by the new UI Simulator)
     * Includes rules that were explicitly DISABLED by lower layers, and full traceability metadata.
     */
    public function getRawCapabilities(string $productType, int $productId): array
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
     * Core lookup: Gets ONLY active, enforced capabilities.
     * Used natively by Validation, Presentation, & Pipeline execution.
     * Strips the traceability metadata and returns just the config payload.
     */
    public function getCapabilitiesForProduct(string $productType, int $productId): array
    {
        $rawCaps = $this->getRawCapabilities($productType, $productId);
        $executableCaps = [];

        foreach ($rawCaps as $code => $meta) {
            // Defensive check: Ensure meta is actually an array
            if (!is_array($meta)) {
                continue;
            }

            // New Engine Format (v2.0): Check if explicitly enforced
            if (isset($meta['enforced'])) {
                if ($meta['enforced'] === true) {
                    $executableCaps[$code] = $meta['config'] ?? [];
                }
            } 
            // Legacy Engine Format (v1.0): Pre-engine upgrade snapshots didn't have 'enforced' wrapper
            else {
                $executableCaps[$code] = $meta;
            }
        }

        return $executableCaps;
    }

    /**
     * Returns presentation capabilities formatted for the Blade Workspace view.
     * Ensures disabled capabilities do NOT render UI partials.
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