<?php

namespace GovStore\Tracking\Services;

use App\Models\Asset;
use GovStore\Tracking\Models\TrackingReference;
use GovStore\Tracking\Models\TrackingTarget;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingTimeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ValidationPolicyEngine
{
    /**
     * Evaluate if associating the asset with the specified tracking reference
     * breaches the planning target, and execute the corresponding validation policy.
     */
    public function evaluate(TrackingReference $reference, int $categoryId, int $qtyToAdd = 1): void
    {
        $target = TrackingTarget::where('tracking_reference_id', $reference->id)
            ->where('category_id', $categoryId)
            ->first();

        $plannedQty = $target ? $target->planned_qty : 0;

        // Query the active counts directly from the associated core asset records
        $currentCount = TrackingAssociation::where('tracking_reference_id', $reference->id)
            ->where('associatable_type', Asset::class)
            ->where('status', 'ACTIVE')
            ->whereHasMorph('associatable', [Asset::class], function ($query) use ($categoryId) {
                $query->whereHas('model', function ($subQuery) use ($categoryId) {
                    $subQuery->where('category_id', $categoryId);
                });
            })
            ->count();

        if (($currentCount + $qtyToAdd) > $plannedQty) {
            $policy = $reference->trackingType->validation_policy;

            $this->executePolicyAction($reference, $categoryId, $plannedQty, $currentCount, $policy);
        }
    }

    protected function executePolicyAction(
        TrackingReference $reference,
        int $categoryId,
        int $planned,
        int $current,
        string $policy
    ): void {
        $msg = "Target allocation exceeded for reference '{$reference->reference_code}'. Planned limit: {$planned}. Current assigned total: {$current}.";

        switch ($policy) {
            case 'BLOCK':
                throw ValidationException::withMessages([
                    'tracking_reference_id' => [$msg . " Action blocked by administrative policy."]
                ]);

            case 'REQUIRE_OVERRIDE':
                if (!request()->filled('tracking_override_reason')) {
                    throw ValidationException::withMessages([
                        'tracking_override_reason' => [$msg . " A mandatory justification reason of at least 10 characters is required to bypass this target."]
                    ]);
                }

                if (strlen(request()->input('tracking_override_reason')) < 10) {
                    throw ValidationException::withMessages([
                        'tracking_override_reason' => ["Override justification must contain at least 10 characters."]
                    ]);
                }

                // Log authorized bypass to the audit timeline
                TrackingTimeline::create([
                    'tracking_reference_id' => $reference->id,
                    'event_type' => 'POLICY_OVERRIDE_GRANTED',
                    'description' => "Manager bypass authorized: target overshoot approved. Reason: " . request()->input('tracking_override_reason'),
                    'actor_id' => Auth::id(),
                    'metadata' => [
                        'category_id' => $categoryId,
                        'planned_limit' => $planned,
                        'new_total' => $current + 1
                    ],
                    'occurred_at' => now(),
                ]);
                break;

            case 'WARN':
                // Write silent warning and continue
                TrackingTimeline::create([
                    'tracking_reference_id' => $reference->id,
                    'event_type' => 'POLICY_OVERSHOOT_WARN',
                    'description' => "Warning recorded: transaction allowed under loose advisory overshoot limits.",
                    'actor_id' => Auth::id(),
                    'metadata' => [
                        'category_id' => $categoryId,
                        'planned_limit' => $planned,
                        'new_total' => $current + 1
                    ],
                    'occurred_at' => now(),
                ]);
                
                session()->flash('warning', "Operational Warning: Reference '{$reference->reference_code}' target limits exceeded.");
                break;

            case 'INFORM_ONLY':
                TrackingTimeline::create([
                    'tracking_reference_id' => $reference->id,
                    'event_type' => 'POLICY_OVERSHOOT_INFORM',
                    'description' => "Informational note recorded: planning allocations exceeded.",
                    'actor_id' => Auth::id(),
                    'metadata' => [
                        'category_id' => $categoryId,
                        'planned_limit' => $planned
                    ],
                    'occurred_at' => now(),
                ]);
                break;
        }
    }
}