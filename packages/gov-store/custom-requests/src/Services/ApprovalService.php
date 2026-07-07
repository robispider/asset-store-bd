<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Models\LocationRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class ApprovalService
{
    public function processDecision(ServiceRequest $request, User $admin, array $itemDecisions): ServiceRequest
    {
        // Enforce active approval gate boundaries
        if (!in_array($request->approval_status, ['submitted', 'under_review', 'pending_primary', 'pending_final'])) {
            throw new Exception("This service request has already been processed.");
        }

        $requester = $request->requester;
        $locationRole = LocationRole::where('location_id', $requester->location_id)->firstOrFail();

        DB::transaction(function () use ($request, $admin, $itemDecisions, $locationRole, $requester) {
            $isPrimaryReview = in_array($request->approval_status, ['submitted', 'under_review', 'pending_primary']);
            
            $approvedCount = 0;
            $rejectedCount = 0;
            $pendingFinalCount = 0;

            foreach ($request->items as $item) {
                $decision = $itemDecisions[$item->id] ?? null;

                if (!$decision) {
                    throw new Exception("No decision was provided for item: " . ($item->requested->name ?? 'Item'));
                }

                $status = $decision['status']; // 'approved' or 'rejected'
                $qty = (int)($decision['qty'] ?? 1);
                $notes = $decision['notes'] ?? null;

                if ($status === 'approved') {
                    if ($qty <= 0) {
                        throw new Exception("Approved quantity must be greater than 0.");
                    }

                    if ($isPrimaryReview) {
                        // --- PRIMARY APPROVER DECISION GATE ---
                        if ($request->resolved_policy === 'PRIMARY_ONLY') {
                            // Single approval: Line item fully approved
                            $item->update([
                                'approved_qty' => $qty,
                                'line_approval_status' => 'approved',
                                'line_fulfillment_status' => 'waiting',
                                'notes' => $notes
                            ]);
                            $approvedCount++;
                        } else {
                            // Double approval: Save primary manager's adjusted quantity, wait for final gate
                            $item->update([
                                'approved_qty' => $qty,
                                'notes' => $notes
                            ]);
                            $pendingFinalCount++;
                        }
                    } else {
                        // --- FINAL APPROVER DECISION GATE ---
                        // Final quantity cannot exceed what the primary manager approved
                        $finalQty = min($qty, $item->approved_qty);
                        
                        $item->update([
                            'approved_qty' => $finalQty,
                            'line_approval_status' => 'approved',
                            'line_fulfillment_status' => 'waiting',
                            'notes' => $notes
                        ]);
                        $approvedCount++;
                    }
                } else {
                    // --- LINE REJECTION ---
                    $item->update([
                        'approved_qty' => 0,
                        'line_approval_status' => 'rejected',
                        'line_fulfillment_status' => 'cancelled',
                        'notes' => $notes
                    ]);

                    RequestEvent::create([
                        'request_id' => $request->id,
                        'user_id' => $admin->id,
                        'event_type' => 'line_rejected',
                        'details' => [
                            'item_name' => $item->requested->name ?? 'Item',
                            'reason' => $notes
                        ]
                    ]);
                    $rejectedCount++;
                }
            }

            // 4. Calculate New Parent Document States
            $parentApprovalStatus = 'approved';
            $parentFulfillmentStatus = 'unstarted';
            $assignedApproverId = null;

            if ($isPrimaryReview && $request->resolved_policy === 'PRIMARY_AND_FINAL' && $pendingFinalCount > 0) {
                // Move to Level 2 (Final Approver Gate)
                $parentApprovalStatus = 'pending_final';
                
                // Resolve active final approver or delegate
                $activeFinal = $locationRole->final_approver_id;
                $today = date('Y-m-d');
                if ($locationRole->final_delegate_id && 
                    $locationRole->final_delegate_until && 
                    $locationRole->final_delegate_until->format('Y-m-d') >= $today
                ) {
                    $activeFinal = $locationRole->final_delegate_id;
                }

                $assignedApproverId = $activeFinal;
                
                // Self-Approval Conflict Check: If requester is the Final Approver, skip step
                if ($requester->id === $activeFinal) {
                    $parentApprovalStatus = 'approved';
                    $parentFulfillmentStatus = 'unstarted';
                    $assignedApproverId = null;
                    $request->closed_at = null;
                    
                    // Auto-approve all line items on final step
                    foreach ($request->items as $item) {
                        if ($item->line_approval_status === 'pending') {
                            $item->update([
                                'line_approval_status' => 'approved',
                                'line_fulfillment_status' => 'waiting'
                            ]);
                        }
                    }
                    $approvedCount += $pendingFinalCount;
                    $pendingFinalCount = 0;
                }
            } elseif ($approvedCount === 0) {
                // All items rejected -> Transaction Closed
                $parentApprovalStatus = 'rejected';
                $parentFulfillmentStatus = 'closed';
                $request->closed_at = now();
            } elseif ($rejectedCount > 0) {
                $parentApprovalStatus = 'partially_approved';
            }

            // Update parent document
            $request->update([
                'approval_status' => $parentApprovalStatus,
                'fulfillment_status' => $parentFulfillmentStatus,
                'assigned_approver_id' => $assignedApproverId,
                'approved_by' => in_array($parentApprovalStatus, ['approved', 'partially_approved']) ? $admin->id : $request->approved_by,
                'approved_at' => in_array($parentApprovalStatus, ['approved', 'partially_approved']) ? now() : null,
            ]);

            // Log final parent milestone event
            RequestEvent::create([
                'request_id' => $request->id,
                'user_id' => $admin->id,
                'event_type' => $parentApprovalStatus,
                'details' => [
                    'approved_lines' => $approvedCount,
                    'rejected_lines' => $rejectedCount,
                    'pending_final_lines' => $pendingFinalCount
                ]
            ]);
        });

        return $request;
    }
}