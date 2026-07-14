<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Models\DraftBasket;
use GovStore\CustomRequests\Models\BasketItem;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class BasketService
{
    /**
     * Get or create a draft basket for the given user.
     */
    public function getOrCreateDraftBasket(int $userId): DraftBasket
    {
        return DraftBasket::getOrCreateForUser($userId);
    }

    /**
     * Add an item to the user's draft basket.
     *
     * @throws \Exception
     */
    public function addItem(int $userId, string $itemType, int $itemId): DraftBasket
    {
        $basket = DraftBasket::getOrCreateForUser($userId);

        // Check if item already exists in basket
        $existing = $basket->items()->where('requested_type', $itemType)
            ->where('requested_id', $itemId)->first();

        if ($existing) {
            $existing->increment('requested_qty');
            return $basket;
        }

        BasketItem::create([
            'basket_id' => $basket->id,
            'requested_type' => $itemType,
            'requested_id' => $itemId,
            'requested_qty' => 1,
        ]);

        return $basket;
    }

    /**
     * Update the quantity of an item in the basket.
     *
     * @throws \Exception
     */
    public function updateItemQty(int $userId, int $itemId, int $qty): DraftBasket
    {
        if ($qty < 1) {
            throw new Exception("Quantity must be at least 1.");
        }

        $basket = DraftBasket::where('user_id', $userId)
            ->where('status', 'draft')->firstOrFail();

        $item = $basket->items()->where('id', $itemId)->firstOrFail();
        $item->update(['requested_qty' => $qty]);

        return $basket;
    }

    /**
     * Remove an item from the basket.
     */
    public function removeItem(int $userId, int $itemId): DraftBasket
    {
        $basket = DraftBasket::where('user_id', $userId)
            ->where('status', 'draft')->firstOrFail();

        $item = $basket->items()->where('id', $itemId)->firstOrFail();
        $item->delete();

        return $basket;
    }

    public function submitBasket($userId, array $metadata): array
    {
        $draftBasket = $this->getOrCreateDraftBasket($userId);
        if ($draftBasket->items()->count() === 0) throw new Exception("You cannot submit an empty service request basket.");

        $requester = User::findOrFail($userId);
        if (!$requester->location_id) throw new Exception("Your user account does not have an assigned office location.");

        $policyService = app(PolicyService::class);
        $draftItems = $draftBasket->items()->get();

        $groupedItems = [];
        foreach ($draftItems as $item) {
            $policy = $policyService->resolvePolicy($item->requested_type, $item->requested_id);
            $groupedItems[$policy][] = $item;
        }

        // Validate approval roles exist in this location
        $hasApprovers = OfficeResponsibility::where('location_id', $requester->location_id)->whereIn('role_slug', ['primary_approver', 'final_approver'])->exists();
        foreach (array_keys($groupedItems) as $policy) {
            if ($policy !== 'AUTO_APPROVE' && !$hasApprovers) {
                throw new Exception("Your office location does not have approval roles configured. Please contact an administrator.");
            }
        }

        $submittedRequests = [];

        DB::transaction(function () use ($groupedItems, $requester, $metadata, &$submittedRequests, $draftBasket) {
            
            $isRequesterPrimary = OfficeResponsibility::where('location_id', $requester->location_id)->where('user_id', $requester->id)->where('role_slug', 'primary_approver')->exists();
            $isRequesterFinal = OfficeResponsibility::where('location_id', $requester->location_id)->where('user_id', $requester->id)->where('role_slug', 'final_approver')->exists();

            foreach ($groupedItems as $policy => $items) {
                $approvalStatus = 'pending_primary';
                $approvedAt = null;

                if ($policy === 'AUTO_APPROVE') {
                    $approvalStatus = 'approved';
                    $approvedAt = now();
                } else {
                    if ($isRequesterPrimary && $policy === 'PRIMARY_ONLY') {
                        $approvalStatus = 'approved';
                        $approvedAt = now();
                    } elseif ($isRequesterFinal && $policy === 'PRIMARY_AND_FINAL') {
                         $approvalStatus = 'approved';
                         $approvedAt = now();
                    } elseif ($isRequesterPrimary && $policy === 'PRIMARY_AND_FINAL') {
                         $approvalStatus = 'pending_final';
                    }
                }

                $newRequest = ServiceRequest::create([
                    'requested_by' => $requester->id,
                    'request_type' => $metadata['request_type'],
                    'resolved_policy' => $policy,
                    'assigned_approver_id' => null, // Shared queue
                    'purpose' => $metadata['purpose'],
                    'justification' => $metadata['justification'],
                    'delivery_location_id' => $metadata['delivery_location_id'] ?? null,
                    'approval_status' => $approvalStatus,
                    'fulfillment_status' => ($approvalStatus === 'approved') ? 'unstarted' : 'unstarted',
                    'submitted_at' => now(),
                    'approved_at' => $approvedAt,
                ]);

                foreach ($items as $draftItem) {
                    $lineAppStatus = ($approvalStatus === 'approved') ? 'approved' : 'pending';
                    RequestItem::create([
                        'request_id' => $newRequest->id,
                        'requested_type' => $draftItem->requested_type,
                        'requested_id' => $draftItem->requested_id,
                        'requested_qty' => $draftItem->requested_qty,
                        'approved_qty' => ($approvalStatus === 'approved') ? $draftItem->requested_qty : 0,
                        'line_approval_status' => $lineAppStatus,
                        'line_fulfillment_status' => ($lineAppStatus === 'approved') ? 'waiting' : 'unstarted',
                    ]);
                }
                
                $submittedRequests[] = $newRequest;
            }

            $draftBasket->items()->delete();
            $draftBasket->delete();
        });

        return $submittedRequests;
    }
}