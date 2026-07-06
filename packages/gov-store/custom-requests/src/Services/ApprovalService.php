<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class ApprovalService
{
    public function processDecision(ServiceRequest $request, User $admin, array $itemDecisions): ServiceRequest
    {
        if (!in_array($request->approval_status, ['submitted', 'under_review'])) {
        
            throw new Exception("This service request has already been processed.");
        }

        DB::transaction(function () use ($request, $admin, $itemDecisions) {
            $approvedCount = 0;
            $rejectedCount = 0;

            foreach ($request->items as $item) {
                // Check if a decision was submitted for this row
                $decision = $itemDecisions[$item->id] ?? null;

                if (!$decision) {
                    throw new Exception("No decision was provided for line item: " . ($item->requested->name ?? 'Unknown'));
                }

                $status = $decision['status']; // 'approved' or 'rejected'
                $qty = (int)($decision['qty'] ?? 1);
                $notes = $decision['notes'] ?? null;

                if ($status === 'approved') {
                    if ($qty <= 0) {
                        throw new Exception("Approved quantity must be greater than 0 for approved lines.");
                    }
                    if ($qty > $item->requested_qty) {
                        throw new Exception("Approved quantity cannot exceed the requested quantity.");
                    }

                    // Log quantity reduction adjustment
                    if ($qty < $item->requested_qty) {
                        RequestEvent::create([
                            'request_id' => $request->id,
                            'user_id' => $admin->id,
                            'event_type' => 'quantity_adjusted',
                            'details' => [
                                'item_name' => $item->requested->name ?? 'Item',
                                'old_qty' => $item->requested_qty,
                                'new_qty' => $qty,
                                'notes' => $notes
                            ]
                        ]);
                    }

                    $item->update([
                        'approved_qty' => $qty,
                        'line_approval_status' => 'approved',
                        'line_fulfillment_status' => 'waiting', // Enters fulfillment queue
                        'notes' => $notes
                    ]);
                    $approvedCount++;
                } else {
                    // Rejected row
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

            // Calculate parent document status
            $finalApprovalStatus = 'approved';
            $finalFulfillmentStatus = 'unstarted';

            if ($approvedCount === 0) {
                $finalApprovalStatus = 'rejected';
                $finalFulfillmentStatus = 'closed';
                $request->closed_at = now();
            } elseif ($rejectedCount > 0) {
                $finalApprovalStatus = 'partially_approved';
            }

            // Update parent document
            $request->update([
                'approval_status' => $finalApprovalStatus,
                'fulfillment_status' => $finalFulfillmentStatus,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            // Log final parent milestone event
            RequestEvent::create([
                'request_id' => $request->id,
                'user_id' => $admin->id,
                'event_type' => $finalApprovalStatus,
                'details' => [
                    'approved_lines' => $approvedCount,
                    'rejected_lines' => $rejectedCount
                ]
            ]);
        });

        return $request;
    }
}

// Helper inline array checker
function mountaineer_in_array($val, $arr) { return in_array($val, $arr); }