<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\OfficeMembership\Models\OfficeResponsibility; // IMPORT PIVOT
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class BasketService
{
    // ... [keep getOrCreateDraftBasket, addItem, updateItemQty, removeItem untouched] ...

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