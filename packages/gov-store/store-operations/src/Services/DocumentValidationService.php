<?php

namespace GovStore\StoreOperations\Services;

use Exception;
use GovStore\StoreOperations\Contracts\TrackingCodeVerifier;
use GovStore\StoreOperations\DTOs\CompiledProfile;
use GovStore\StoreOperations\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        if (! empty($trackingCode)) {
            try {
                $reason = app(TrackingCodeVerifier::class)->failureReason($trackingCode, (int) $document->location_id);
                if ($reason !== null) {
                    $errors['Administrative Reference'][] = ["BLOCKED: {$reason}"];
                }
            } catch (\Throwable $e) {
                $reference = (string) Str::uuid();
                Log::warning('GovStore tracking verification failed', ['reference_id' => $reference, 'exception' => $e]);
                $errors['Administrative Reference'][] = [__('storeops::storeops.tracking_verification_failed', ['reference' => $reference])];
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

            if (! is_array($capabilities)) {
                continue;
            }

            // Extract the specific input data for this item from the HTTP Request
            $itemData = ['qty' => $item->quantity, 'unit_cost' => $item->unit_cost, 'meta' => []];
            foreach ($item->metadata as $meta) {
                $itemData['meta'][$meta->row_index][$meta->field_key] = $meta->value;
            }
            foreach ($requestData['items'] ?? [] as $reqItem) {
                $reqId = $reqItem['id'] ?? '';

                if (str_contains($reqId, '_')) {
                    [$rawType, $cleanId] = explode('_', $reqId);
                    $shortType = strtolower(class_basename($rawType));
                } else {
                    $shortType = 'consumable';
                    $cleanId = $reqId;
                }

                if ($document->status === 'DRAFT' && $shortType === $item->product_type && (int) $cleanId === $item->product_id) {
                    $itemData = $reqItem;
                    break;
                }
            }

            // Loop through the assigned plugins and validate
            foreach ($capabilities as $capCode => $config) {
                $realCode = is_string($capCode) ? $capCode : (is_array($config) ? ($config['code'] ?? null) : $config);
                $realConfig = is_array($config) ? $config : [];

                if (! $realCode || is_bool($realCode)) {
                    continue;
                }

                $capability = CapabilityRegistry::make($realCode);
                $capErrors = $capability->validate($itemData, $realConfig);

                if (! empty($capErrors)) {
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

        // --- 1. EVALUATE DOCUMENT-SPECIFIC HEADER REQUIREMENTS ---
        if ($document->type === 'adjustment') {
            foreach ([
                ['Adjustment reason', filled($document->adjustment_reason)],
                ['Source document', filled($document->source_document_id)],
            ] as [$label, $passed]) {
                $totalRequirements++;
                $satisfiedRequirements += $passed ? 1 : 0;
                $checklist[] = ['label' => $label, 'passed' => $passed];
            }
        } else {
            $totalRequirements++;
            $hasChallanOrNothi = $document->type === 'issue'
                ? (filled($document->issued_to_user_id) || filled($document->issue_department))
                : $document->references()->whereIn('reference_type', ['Supplier Challan', 'Nothi / Approval Letter', 'Purchase Order'])->exists();
            $satisfiedRequirements += $hasChallanOrNothi ? 1 : 0;
            $checklist[] = ['label' => $document->type === 'issue' ? 'Issue recipient' : 'Valid Administrative Reference (Challan / Nothi)', 'passed' => $hasChallanOrNothi];
        }

        // --- 2. EVALUATE ITEM-LEVEL QUANTITY & CAPABILITIES ---
        $snapshot = $document->getCompiledProfileSnapshot() ?? [];
        $profile = new CompiledProfile($snapshot);

        foreach ($document->items as $item) {

            // Validate line item quantity (> 0)
            $totalRequirements++;
            $hasValidQty = ($item->quantity > 0);
            if ($hasValidQty) {
                $satisfiedRequirements++;
            }
            $checklist[] = ['label' => "{$item->product_name}: Valid Quantity (> 0)", 'passed' => $hasValidQty];

            if ($document->type === 'adjustment') {
                $totalRequirements++;
                $hasDirection = in_array($item->metadata()->where('field_key', 'adjustment_direction')->value('value'), ['IN', 'OUT'], true);
                $satisfiedRequirements += $hasDirection ? 1 : 0;
                $checklist[] = ['label' => "{$item->product_name}: Adjustment direction", 'passed' => $hasDirection];
            }

            $capabilities = $profile->getCapabilitiesForProduct($item->product_type, $item->product_id);

            if (! is_array($capabilities)) {
                continue;
            }

            foreach ($capabilities as $capCode => $config) {
                $realCode = is_string($capCode) ? $capCode : (is_array($config) ? ($config['code'] ?? null) : $config);
                $realConfig = is_array($config) ? $config : [];

                if (! $realCode || is_bool($realCode)) {
                    continue;
                }

                $capability = CapabilityRegistry::make($realCode);
                $requirements = $capability->getRequirements($realConfig);

                if (! is_array($requirements) || empty($requirements)) {
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
            'is_valid' => $isValid,
            'progress' => $percentage,
            'checklist' => $checklist,
        ];
    }
}
