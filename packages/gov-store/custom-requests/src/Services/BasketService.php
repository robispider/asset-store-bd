<?php

namespace GovStore\CustomRequests\Models; // In some places mapped to Request, let's keep it safe:
namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Models\LocationRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class BasketService
{
    public function getOrCreateDraftBasket($userId): ServiceRequest
    {
        $basket = ServiceRequest::firstOrCreate(
            ['requested_by' => $userId, 'approval_status' => 'draft'],
            [
                'request_type' => 'other',
                'purpose' => 'Draft Service Request',
                'justification' => 'Pending submission...',
                'fulfillment_status' => 'unstarted',
                'resolved_policy' => 'PRIMARY_ONLY'
            ]
        );

        if ($basket->wasRecentlyCreated) {
            RequestEvent::create([
                'request_id' => $basket->id,
                'user_id' => $userId,
                'event_type' => 'draft_created',
                'details' => ['message' => 'New draft basket initialized']
            ]);
        }

        return $basket;
    }

    public function addItem($userId, $type, $id, $qty = 1): ServiceRequest
    {
        $basket = $this->getOrCreateDraftBasket($userId);
        $cleanType = strtolower($type);

        $item = RequestItem::where('request_id', $basket->id)
            ->where('requested_type', $cleanType)
            ->where('requested_id', $id)
            ->first();

        if ($item) {
            if ($cleanType !== 'asset') {
                $item->requested_qty += $qty;
                $item->save();
            }
        } else {
            RequestItem::create([
                'request_id' => $basket->id,
                'requested_type' => $cleanType,
                'requested_id' => $id,
                'requested_qty' => ($cleanType === 'asset') ? 1 : $qty,
                'line_approval_status' => 'pending',
                'line_fulfillment_status' => 'unstarted',
            ]);
        }

        return $basket->load('items');
    }

    public function updateItemQty($userId, $itemId, $newQty): bool
    {
        $basket = $this->getOrCreateDraftBasket($userId);
        $item = RequestItem::where('request_id', $basket->id)->where('id', $itemId)->firstOrFail();

        if ($item->requested_type === 'asset') {
            throw new Exception("Hardware assets can only be requested at a quantity of 1 per line item.");
        }

        if ($newQty <= 0) {
            return $item->delete();
        }

        $item->requested_qty = $newQty;
        return $item->save();
    }

    public function removeItem($userId, $itemId): bool
    {
        $basket = $this->getOrCreateDraftBasket($userId);
        return RequestItem::where('request_id', $basket->id)->where('id', $itemId)->delete();
    }

    /**
     * Splits and submits the user's active draft basket into independent single-policy requests.
     */
    public function submitBasket($userId, array $metadata): array
    {
        $draftBasket = $this->getOrCreateDraftBasket($userId);

        if ($draftBasket->items()->count() === 0) {
            throw new Exception("You cannot submit an empty service request basket.");
        }

        $requester = User::findOrFail($userId);
        if (!$requester->location_id) {
            throw new Exception("Your user account does not have an assigned office location. Please contact an administrator.");
        }

        // Fetch location roles
        $locationRole = LocationRole::where('location_id', $requester->location_id)->first();
        $policyService = app(PolicyService::class);
        $draftItems = $draftBasket->items()->get();

        // 1. Group draft items by their resolved policy
        $groupedItems = [];
        foreach ($draftItems as $item) {
            $policy = $policyService->resolvePolicy($item->requested_type, $item->requested_id);
            $groupedItems[$policy][] = $item;
        }

        // 2. Validate location roles if any item requires human approval
        foreach (array_keys($groupedItems) as $policy) {
            if ($policy !== 'AUTO_APPROVE' && !$locationRole) {
                throw new Exception("Your office location (" . ($requester->location->name ?? 'Main Office') . ") does not have approval roles configured. Please contact an administrator.");
            }
        }

        $submittedRequests = [];

        DB::transaction(function () use ($groupedItems, $requester, $locationRole, $metadata, &$submittedRequests, $draftBasket) {
            
            foreach ($groupedItems as $policy => $items) {
                
                // Set default base states
                $approvalStatus = 'pending_primary';
                $fulfillmentStatus = 'unstarted';
                $assignedApproverId = null;
                $approvedAt = null;

                if ($policy === 'AUTO_APPROVE') {
                    $approvalStatus = 'approved';
                    $fulfillmentStatus = 'unstarted';
                    $approvedAt = now();
                } else {
                    // Resolve active Primary Approver (with Delegation calendar checks)
                    $activePrimary = $locationRole->primary_approver_id;
                    $today = date('Y-m-d');
                    if ($locationRole->primary_delegate_id && 
                        $locationRole->primary_delegate_until && 
                        $locationRole->primary_delegate_until->format('Y-m-d') >= $today
                    ) {
                        $activePrimary = $locationRole->primary_delegate_id;
                    }

                    // Resolve active Final Approver (with Delegation calendar checks)
                    $activeFinal = $locationRole->final_approver_id;
                    if ($locationRole->final_delegate_id && 
                        $locationRole->final_delegate_until && 
                        $locationRole->final_delegate_until->format('Y-m-d') >= $today
                    ) {
                        $activeFinal = $locationRole->final_delegate_id;
                    }

                    // Enforce Conflict Rules (Self-Approval Checks)
                    if ($requester->id === $activePrimary) {
                        if ($policy === 'PRIMARY_ONLY') {
                            $approvalStatus = 'approved';
                            $approvedAt = now();
                        } elseif ($policy === 'PRIMARY_AND_FINAL') {
                            $approvalStatus = 'pending_final';
                            $assignedApproverId = $activeFinal;
                        }
                    } else {
                        $approvalStatus = 'pending_primary';
                        $assignedApproverId = $activePrimary;
                    }
                }

                // Create the split Request document
                $newRequest = ServiceRequest::create([
                    'requested_by' => $requester->id,
                    'request_type' => $metadata['request_type'],
                    'resolved_policy' => $policy,
                    'assigned_approver_id' => $assignedApproverId,
                    'purpose' => $metadata['purpose'],
                    'justification' => $metadata['justification'],
                    'required_by_date' => $metadata['required_by_date'] ?? null,
                    'delivery_location_id' => $metadata['delivery_location_id'] ?? null,
                    'cost_center' => $metadata['cost_center'] ?? null,
                    'approval_status' => $approvalStatus,
                    'fulfillment_status' => $fulfillmentStatus,
                    'submitted_at' => now(),
                    'approved_at' => $approvedAt,
                ]);

                // Create individual request items linked to this split document
                foreach ($items as $draftItem) {
                    $approvedQty = ($policy === 'AUTO_APPROVE' || $approvalStatus === 'approved') ? $draftItem->requested_qty : 0;
                    $lineAppStatus = ($policy === 'AUTO_APPROVE' || $approvalStatus === 'approved') ? 'approved' : 'pending';
                    $lineFulfillStatus = ($policy === 'AUTO_APPROVE' || $approvalStatus === 'approved') ? 'waiting' : 'unstarted';

                    RequestItem::create([
                        'request_id' => $newRequest->id,
                        'requested_type' => $draftItem->requested_type,
                        'requested_id' => $draftItem->requested_id,
                        'requested_qty' => $draftItem->requested_qty,
                        'approved_qty' => $approvedQty,
                        'line_approval_status' => $lineAppStatus,
                        'line_fulfillment_status' => $lineFulfillStatus,
                    ]);
                }

                // Log immutable timeline event
                RequestEvent::create([
                    'request_id' => $newRequest->id,
                    'user_id' => $requester->id,
                    'event_type' => 'submitted',
                    'details' => [
                        'policy' => $policy,
                        'initial_status' => $approvalStatus,
                        'item_count' => count($items)
                    ]
                ]);

                // If auto-approved, log system approval event
                if ($approvalStatus === 'approved') {
                    RequestEvent::create([
                        'request_id' => $newRequest->id,
                        'user_id' => User::first()->id, // System Admin
                        'event_type' => 'closed',
                        'details' => ['message' => 'System auto-approved request based on policy: ' . $policy]
                    ]);
                }

                $submittedRequests[] = $newRequest;
            }

            // Clear the original draft basket items
            $draftBasket->items()->delete();
            $draftBasket->delete();
        });

        return $submittedRequests;
    }
}