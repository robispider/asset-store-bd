<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\DTOs\CompiledProfile;
use Exception;

class DocumentValidationService
{
    /**
     * Loops through all line items, resolves their assigned capabilities, 
     * and executes their native validate() methods with complete type safety.
     */
    public function validateDocument(Document $document, array $requestData): array
    {
        $errors = [];
        $snapshot = $document->getCompiledProfileSnapshot() ?? [];

        if (empty($snapshot)) {
            return [];
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
                
                // RESOLVED UNDEFINED KEY "TYPE": Extract both type and ID from the composed key!
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
                // Defensive extraction of Code and Config
                $realCode = is_string($capCode) ? $capCode : (is_array($config) ? ($config['code'] ?? null) : $config);
                $realConfig = is_array($config) ? $config : [];

                if (!$realCode || is_bool($realCode)) {
                    continue;
                }

                $capability = CapabilityRegistry::make($realCode);
                
                // Run plugin validation
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

        // 1. Evaluate Legal Header References
        $totalRequirements++;
        $hasRefNo = !empty($document->reference_no);
        if ($hasRefNo) $satisfiedRequirements++;
        $checklist[] = ['label' => 'Reference (Challan) Number', 'passed' => $hasRefNo];

        $totalRequirements++;
        $hasRefDate = !empty($document->reference_date);
        if ($hasRefDate) $satisfiedRequirements++;
        $checklist[] = ['label' => 'Reference Date', 'passed' => $hasRefDate];

        // 2. Evaluate Item-level Quantity & Capabilities
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
                // Extract capability string code safely
                $realCode = is_string($capCode) ? $capCode : (is_array($config) ? ($config['code'] ?? null) : $config);
                $realConfig = is_array($config) ? $config : [];

                if (!$realCode || is_bool($realCode)) {
                    continue;
                }

                $capability = CapabilityRegistry::make($realCode);
                $requirements = $capability->getRequirements($realConfig);

                // Ensure $requirements is an array before looping
                if (!is_array($requirements) || empty($requirements)) {
                    continue;
                }

                foreach ($requirements as $reqKey) {
                    $totalRequirements++;
                    
                    $filledCount = $item->metadata()
                        ->where('field_key', $reqKey)
                        ->whereNotNull('value')
                        ->where('value', '!=', '')
                        ->count();

                    $isSatisfied = ($filledCount >= $item->quantity && $item->quantity > 0);
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