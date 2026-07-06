<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\ItemRequest;
use GovStore\CustomRequests\Events\ItemApproved;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class ApprovalService
{
    public function approve(ItemRequest $request, User $admin): ItemRequest
    {
        if (!$request->isPending()) {
            throw new Exception("Only pending requests can be approved.");
        }

        // Wrap in a transaction so if checkout fails, the DB rolls back
        DB::transaction(function () use ($request, $admin) {
            $request->status = 'approved';
            $request->approved_by = $admin->id;
            $request->save();

            // Fire the event which triggers ProcessItemCheckout!
            event(new ItemApproved($request, $admin));
        });

        return $request;
    }

    public function reject(ItemRequest $request, User $admin, string $reason = null): ItemRequest
    {
        if (!$request->isPending()) {
            throw new Exception("Only pending requests can be rejected.");
        }

        $request->status = 'rejected';
        $request->approved_by = $admin->id;
        
        if ($reason) {
            $request->notes = $request->notes . "\nRejection Reason: " . $reason;
        }

        $request->save();

        return $request;
    }
}