<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\OfficeMembership\Models\OfficeResponsibility; // IMPORT PIVOT
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class ApprovalService
{
    public function processDecision(ServiceRequest $request, User $admin, array $itemDecisions): ServiceRequest
    {
        if (!in_array($request->approval_status, ['submitted', 'under_review', 'pending_primary', 'pending_final'])) {
            throw new Exception(__('requestlabels::requests.approvals_service_exception_already_processed'));
        }

        $requester = $request->requester;

        DB::transaction(function () use ($request, $admin, $itemDecisions, $requester) {
            $isPrimaryReview = in_array($request->approval_status, ['submitted', 'under_review', 'pending_primary']);
            
            $approvedCount = 0;
            $rejectedCount = 0;
            $pendingFinalCount = 0;

            foreach ($request->items as $item) {
                $decision = $itemDecisions[$item->id] ?? null;
                if (!$decision) throw new Exception(__('requestlabels::requests.approvals_service_exception_no_decision'));

                $status = $decision['status'];
                $qty = (int)($decision['qty'] ?? 1);
                $notes = $decision['notes'] ?? null;

                if ($status === 'approved') {
                    if ($qty <= 0) throw new Exception(__('requestlabels::requests.approvals_service_exception_qty_must_be_positive'));

                    if ($isPrimaryReview) {
                        if ($request->resolved_policy === 'PRIMARY_ONLY') {
                            $item->update(['approved_qty' => $qty, 'line_approval_status' => 'approved', 'line_fulfillment_status' => 'waiting', 'notes' => $notes]);
                            $approvedCount++;
                        } else {
                            $item->update(['approved_qty' => $qty, 'notes' => $notes]);
                            $pendingFinalCount++;
                        }
                    } else {
                        $item->update(['approved_qty' => min($qty, $item->approved_qty), 'line_approval_status' => 'approved', 'line_fulfillment_status' => 'waiting', 'notes' => $notes]);
                        $approvedCount++;
                    }
                } else {
                    $item->update(['approved_qty' => 0, 'line_approval_status' => 'rejected', 'line_fulfillment_status' => 'cancelled', 'notes' => $notes]);
                    RequestEvent::create(['request_id' => $request->id, 'user_id' => $admin->id, 'event_type' => 'line_rejected', 'details' => ['reason' => $notes]]);
                    $rejectedCount++;
                }
            }

            // 4. Calculate New Parent Document States
            $parentApprovalStatus = 'approved';
            $parentFulfillmentStatus = 'unstarted';

            if ($isPrimaryReview && $request->resolved_policy === 'PRIMARY_AND_FINAL' && $pendingFinalCount > 0) {
                $parentApprovalStatus = 'pending_final';
                
                // Self-Approval Conflict Check: Is the requester also a Final Approver here?
                $isRequesterFinal = OfficeResponsibility::where('location_id', $requester->location_id)->where('user_id', $requester->id)->where('role_slug', 'final_approver')->exists();
                
                if ($isRequesterFinal) {
                    $parentApprovalStatus = 'approved';
                    foreach ($request->items as $item) {
                        if ($item->line_approval_status === 'pending') {
                            $item->update(['line_approval_status' => 'approved', 'line_fulfillment_status' => 'waiting']);
                        }
                    }
                    $approvedCount += $pendingFinalCount;
                    $pendingFinalCount = 0;
                }
            } elseif ($approvedCount === 0) {
                $parentApprovalStatus = 'rejected';
                $parentFulfillmentStatus = 'closed';
                $request->closed_at = now();
            } elseif ($rejectedCount > 0) {
                $parentApprovalStatus = 'partially_approved';
            }

            $request->update([
                'approval_status' => $parentApprovalStatus,
                'fulfillment_status' => $parentFulfillmentStatus,
                'assigned_approver_id' => null, // Shared queue model
                'approved_by' => in_array($parentApprovalStatus, ['approved', 'partially_approved']) ? $admin->id : $request->approved_by,
                'approved_at' => in_array($parentApprovalStatus, ['approved', 'partially_approved']) ? now() : null,
            ]);

            RequestEvent::create(['request_id' => $request->id, 'user_id' => $admin->id, 'event_type' => $parentApprovalStatus, 'details' => []]);
        });

        return $request;
    }
}