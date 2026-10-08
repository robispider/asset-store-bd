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
    public function validateDocument(Document $document, array $requestData, bool $verifyTracking = true): array
    {
        $errors = [];

        // --- 1. SERVER-SIDE TRACKING ENGINE GUARD (HANDSHAKE A1 ENFORCEMENT) ---
        // Prevents JS bypass of scope boundaries before materialization
        if ($verifyTracking) {
            $allocationRef = $document->references()->where('reference_type', 'Special Allocation')->first();
            $errors = array_merge($errors, $this->validateTrackingReference(
                $allocationRef?->reference_number,
                (int) $document->location_id
            ));
        }

        // --- 2. CAPABILITY METADATA VALIDATION ---
        $snapshot = $document->getCompiledProfileSnapshot() ?? [];

        if (empty($snapshot)) {
            return $errors;
        }

        $profile = new CompiledProfile($snapshot);

        foreach ($document->items as $item) {
            if ($document->type === 'transfer') {
                continue;
            }
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

    /** Validate the allocation reference without performing document writes. */
    public function validateTrackingReference(?string $trackingCode, int $locationId): array
    {
        if (empty($trackingCode)) {
            return [];
        }

        try {
            $reason = app(TrackingCodeVerifier::class)->failureReason($trackingCode, $locationId);
            return $reason === null ? [] : ['Administrative Reference' => ["BLOCKED: {$reason}"]];
        } catch (\Throwable $e) {
            $reference = (string) Str::uuid();
            Log::warning('GovStore tracking verification failed', ['reference_id' => $reference, 'exception' => $e]);
            return ['Administrative Reference' => [__('storeops::storeops.tracking_verification_failed', ['reference' => $reference])]];
        }
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

        if ($document->type === 'receipt' && $document->purchase_type === 'Purchase') {
            $totalRequirements++;
            $hasSupplier = $document->supplier_id && \App\Models\Supplier::whereKey($document->supplier_id)->exists();
            $satisfiedRequirements += $hasSupplier ? 1 : 0;
            $checklist[] = ['label' => __('storeops::storeops.supplier'), 'passed' => (bool) $hasSupplier];
        }

        // --- 1. EVALUATE DOCUMENT-SPECIFIC HEADER REQUIREMENTS ---
        if ($document->type === 'transfer') {
            foreach ([
                [__('storeops::storeops.transfer_reason'), filled($document->transfer_reason)],
                [__('storeops::storeops.destination_office'), filled($document->destination_location_id)],
            ] as [$label, $passed]) {
                $totalRequirements++;
                $satisfiedRequirements += $passed ? 1 : 0;
                $checklist[] = ['label' => $label, 'passed' => $passed];
            }
        } elseif ($document->type === 'adjustment') {
            foreach ([
                [__('storeops::storeops.reason'), filled($document->adjustment_reason)],
                [__('storeops::storeops.source_document'), filled($document->source_document_id)],
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
            $checklist[] = ['label' => __($document->type === 'issue' ? 'storeops::storeops.issue_member' : 'storeops::storeops.valid_reference'), 'passed' => $hasChallanOrNothi];
        }

        // --- 2. EVALUATE ITEM-LEVEL QUANTITY & CAPABILITIES ---
        $snapshot = $document->getCompiledProfileSnapshot() ?? [];
        $profile = new CompiledProfile($snapshot);

        foreach ($document->items as $item) {

            if ($document->type === 'transfer') {
                $totalRequirements++;
                $hasTarget = filled($item->metadata()->where('field_key', 'destination_stockable_id')->value('value'));
                $satisfiedRequirements += $hasTarget ? 1 : 0;
                $checklist[] = ['label' => __('storeops::storeops.destination_item'), 'passed' => $hasTarget];
            }

            // Validate line item quantity (> 0)
            $totalRequirements++;
            $hasValidQty = ($item->quantity > 0);
            if ($hasValidQty) {
                $satisfiedRequirements++;
            }
            $checklist[] = ['label' => __('storeops::storeops.valid_quantity', ['item' => $item->product_name]), 'passed' => $hasValidQty];

            if ($document->type === 'adjustment') {
                $totalRequirements++;
                $hasDirection = in_array($item->metadata()->where('field_key', 'adjustment_direction')->value('value'), ['IN', 'OUT'], true);
                $satisfiedRequirements += $hasDirection ? 1 : 0;
                $checklist[] = ['label' => __('storeops::storeops.item_direction', ['item' => $item->product_name]), 'passed' => $hasDirection];
            }

            $capabilities = $profile->getCapabilitiesForProduct($item->product_type, $item->product_id);

            if ($document->type === 'transfer' || ! is_array($capabilities)) {
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

                    $readableLabel = match ($reqKey) {
                        'serial_number' => __('storeops::storeops.serial_required'),
                        'warranty_months' => __('storeops::storeops.warranty_months'),
                        default => ucfirst(str_replace('_', ' ', $reqKey)),
                    };
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
