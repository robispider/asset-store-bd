<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\DTOs\CompiledProfile;
use Illuminate\Support\Facades\Http;
use Exception;

class DocumentValidationService
{
    /**
     * Loops through all line items, resolves their assigned capabilities, 
     * executes native validations, and runs strict server-side Tracking Verification.
     */
    public function validateDocument(Document $document, array $requestData): array
    {
        $errors = [];

        // --- 1. SERVER-SIDE TRACKING ENGINE GUARD (HANDSHAKE A1 ENFORCEMENT) ---
        // Prevents JS bypass of scope boundaries before materialization
        $allocationRef = $document->references()->where('reference_type', 'Special Allocation')->first();
        $trackingCode = $allocationRef ? $allocationRef->reference_number : null;

        if (!empty($trackingCode)) {
            try {
                // Determine the correct host dynamically for local dev vs production environments
                $host = request()->getSchemeAndHttpHost();
                $apiUrl = $host . '/gov-store/api/tracking/verify-code';

                $response = Http::timeout(5)->get($apiUrl, [
                    'code'        => $trackingCode,
                    'location_id' => $document->location_id
                ]);

                if ($response->successful()) {
                    $trackingData = $response->json();
                    
                    if (isset($trackingData['can_proceed']) && $trackingData['can_proceed'] === false) {
                        $msg = $trackingData['messages'][0] ?? 'Tracking Code scope validation failed.';
                        $errors['Administrative Reference'][] = ["BLOCKED: {$msg}"];
                    }
                } elseif ($response->status() !== 404) {
                    // Ignore 404s (meaning tracking package isn't installed), 
                    // but flag 500s or timeouts as operational risks.
                    $errors['Administrative Reference'][] = ["WARNING: Tracking engine unreachable. Cannot verify scope."];
                }
            } catch (\Exception $e) {
                // Fail open gracefully if the network loopback fails entirely
                \Illuminate\Support\Facades\Log::warning("GovStore Tracking Handshake A1 Guard Failed: " . $e->getMessage());
            }
        }

        // --- 2. CAPABILITY METADATA VALIDATION ---
        $snapshot = $document->getCompiledProfileSnapshot() ?? [];

        if (empty($snapshot)) {
            return $errors;
        }

        $profile = new CompiledProfile($snapshot);

        foreach ($document->items as $item) {
            $capabilities = $profile->getCapabilitiesForProduct($item->product_type, $item->product_id);

            if (!is_array($capabilities)) {
                continue;
            }

            // Extract the specific input data for this item from the HTTP Request
            $itemData = [];
            foreach ($requestData['items'] ?? [] as $reqItem) {
                $reqId = $reqItem['id'] ?? '';
                
                if (str_contains($reqId, '_')) {
                    [$rawType, $cleanId] = explode('_', $reqId);
                    $shortType = strtolower(class_basename($rawType));
                } else {
                    $shortType = 'consumable';
                    $cleanId = $reqId;
                }

                if ($shortType === $item->product_type && (int)$cleanId === $item->product_id) {
                    $itemData = $reqItem;
                    break;
                }
            }

            // Loop through the assigned plugins and validate
            foreach ($capabilities as $capCode => $config) {
                $realCode = is_string($capCode) ? $capCode : (is_array($config) ? ($config['code'] ?? null) : $config);
                $realConfig = is_array($config) ? $config : [];

                if (!$realCode || is_bool($realCode)) {
                    continue;
                }

                $capability = CapabilityRegistry::make($realCode);
                $capErrors = $capability->validate($itemData, $realConfig);

                if (!empty($capErrors)) {
                    $errors[$item->product_name][] = $capErrors;
                }
            }
        }

        return $errors;
    }

    /**
     * Evaluates document completion directly against server-side PHP Capability plugins.
     * Generates the authoritative checklist and completion percentage.
     */
    public function evaluateDocument(Document $document): array
    {
        $checklist = [];
        $totalRequirements = 0;
        $satisfiedRequirements = 0;

        // --- 1. EVALUATE POLYMORPHIC REFERENCES ---
        $totalRequirements++;
        $hasChallanOrNothi = $document->references()
            ->whereIn('reference_type', ['Supplier Challan', 'Nothi / Approval Letter', 'Purchase Order'])
            ->exists();
            
        if ($hasChallanOrNothi) {
            $satisfiedRequirements++;
        }
        $checklist[] = ['label' => 'Valid Administrative Reference (Challan / Nothi)', 'passed' => $hasChallanOrNothi];

        // --- 2. EVALUATE ITEM-LEVEL QUANTITY & CAPABILITIES ---
        $snapshot = $document->getCompiledProfileSnapshot() ?? [];
        $profile = new CompiledProfile($snapshot);

        foreach ($document->items as $item) {
            
            // Validate line item quantity (> 0)
            $totalRequirements++;
            $hasValidQty = ($item->quantity > 0);
            if ($hasValidQty) $satisfiedRequirements++;
            $checklist[] = ['label' => "{$item->product_name}: Valid Quantity (> 0)", 'passed' => $hasValidQty];

            $capabilities = $profile->getCapabilitiesForProduct($item->product_type, $item->product_id);

            if (!is_array($capabilities)) {
                continue;
            }

            foreach ($capabilities as $capCode => $config) {
                $realCode = is_string($capCode) ? $capCode : (is_array($config) ? ($config['code'] ?? null) : $config);
                $realConfig = is_array($config) ? $config : [];

                if (!$realCode || is_bool($realCode)) {
                    continue;
                }

                $capability = CapabilityRegistry::make($realCode);
                $requirements = $capability->getRequirements($realConfig);

                if (!is_array($requirements) || empty($requirements)) {
                    continue;
                }

                foreach ($requirements as $req) {
                    $totalRequirements++;
                    
                    $reqKey = is_array($req) ? $req['key'] : $req;
                    
                    $filledCount = $item->metadata()
                        ->where('field_key', $reqKey)
                        ->whereNotNull('value')
                        ->where('value', '!=', '')
                        ->count();

                    $requiredInputCount = in_array($reqKey, ['serial_number', 'warranty_months']) ? $item->quantity : 1;
                    
                    $isSatisfied = ($filledCount >= $requiredInputCount && $item->quantity > 0);
                    if ($isSatisfied) {
                        $satisfiedRequirements++;
                    }

                    $readableLabel = ucfirst(str_replace('_', ' ', $reqKey));
                    $checklist[] = ['label' => "{$item->product_name}: {$readableLabel}", 'passed' => $isSatisfied];
                }
            }
        }

        $percentage = $totalRequirements > 0 ? (int) round(($satisfiedRequirements / $totalRequirements) * 100) : 0;
        $isValid = ($satisfiedRequirements === $totalRequirements) && ($totalRequirements > 0);

        return [
            'is_valid'   => $isValid,
            'progress'   => $percentage,
            'checklist'  => $checklist,
        ];
    }
}