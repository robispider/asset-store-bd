<?php

namespace GovStore\Tracking\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Services\ScopeValidatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingEvaluationController extends Controller
{
    protected ScopeValidatorService $scopeValidator;

    public function __construct(ScopeValidatorService $scopeValidator)
    {
        $this->scopeValidator = $scopeValidator;
    }

    /**
     * Handshake A1: Header-Level Verification
     * Verifies if the requesting office has authority to select this code.
     * STRICTLY blocks geographical and organizational scope breaches.
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'code'        => 'required|string',
            'location_id' => 'required|integer|exists:locations,id',
        ]);

        $trackingCode = TrackingCode::with('initiative')
            ->where('tracking_code', $request->input('code'))
            ->where('status', 'ACTIVE') // Only active codes allowed on GRNs
            ->first();

        if (!$trackingCode) {
            return response()->json([
                'can_proceed' => false,
                'messages' => ['Invalid or Inactive Tracking Code. Selection blocked.']
            ], 403);
        }

        $initiative = $trackingCode->initiative;

        // Verify Geographical and Organizational Visibility (Enforces Bounded Context limits)
        $scopeCheck = $this->scopeValidator->validateExecutionScope($trackingCode, $request->input('location_id'));
        if (!$scopeCheck['is_valid']) {
            return response()->json([
                'can_proceed' => false,
                'messages' => [$scopeCheck['message']]
            ], 403);
        }

        // Scope Approved - Clear to Proceed
        return response()->json([
            'can_proceed' => true,
            'context' => [
                'initiative'        => $initiative->title,
                'task'              => $trackingCode->task_title,
                'fiscal_year'       => $trackingCode->fiscal_year,
                'specificity_level' => $trackingCode->specificity_level,
            ],
            'messages' => []
        ], 200);
    }

    /**
     * Handshake A2: Line-Item-Level Evaluation
     * Evaluates quantitative targets and classifications.
     * NEVER blocks saving; returns non-blocking, visual advisory warnings.
     */
    public function evaluate(Request $request)
    {
        $request->validate([
            'code'        => 'required|string',
            'location_id' => 'required|integer|exists:locations,id',
            'category_id' => 'required|integer|exists:categories,id',
            'qty'         => 'required|integer|min:1',
        ]);

        $trackingCode = TrackingCode::with(['initiative', 'targets'])
            ->where('tracking_code', $request->input('code'))
            ->where('status', 'ACTIVE')
            ->first();

        if (!$trackingCode) {
            return response()->json([
                'can_proceed' => false,
                'messages' => ['Invalid or Inactive Tracking Code.']
            ], 403);
        }

        $specificity = $trackingCode->specificity_level;
        $qtyToAdd = (int) $request->input('qty');

        // Double check Scope on line items (Strict security guard)
        $scopeCheck = $this->scopeValidator->validateExecutionScope($trackingCode, $request->input('location_id'));
        if (!$scopeCheck['is_valid']) {
            return response()->json([
                'can_proceed' => false,
                'messages' => [$scopeCheck['message']],
            ], 403);
        }

        // =============================================================
        // CASCADING VALIDATION ENGINE (ADVISORY RULES)
        // =============================================================

        // --- LEVEL 1: BLANKET CODE ---
        if ($specificity === '1_BLANKET') {
            return response()->json([
                'can_proceed' => true,
                'context' => ['specificity_level' => '1_BLANKET'],
                'messages' => [],
                'target_status' => [
                    'category' => 'Any Component (Blanket Mode)',
                    'is_exceeded' => false
                ]
            ], 200);
        }

        // --- LEVEL 2: CATEGORY CONSTRAINTS ---
        if ($specificity === '2_CATEGORY') {
            $target = $trackingCode->targets->where('category_id', $request->input('category_id'))->first();
            
            // If the category is NOT allocated at all, display a warning
            if (!$target) {
                return response()->json([
                    'can_proceed' => true, // Allowed to proceed
                    'context' => ['specificity_level' => '2_CATEGORY'],
                    'messages' => ["Warning: This item category is not in the allocated planning targets."],
                    'target_status' => [
                        'category' => 'Unallocated Category',
                        'is_exceeded' => true
                    ]
                ], 200);
            }

            // Sum global received quantity autonomously from our associations table
            $receivedQty = (int) DB::table('gov_tracking_associations')
                ->where('tracking_code_id', $trackingCode->id)
                ->where('category_id', $request->input('category_id'))
                ->where('status', 'ACTIVE')
                ->sum('quantity');

            $isExceeded = ($receivedQty + $qtyToAdd) > $target->planned_qty;
            
            $messages = [];
            if ($isExceeded) {
                $messages[] = "Warning: The shared category allocation for '{$target->category->name}' has been exceeded globally.";
            }

            return response()->json([
                'can_proceed' => true, // Saving is never blocked
                'context' => ['specificity_level' => '2_CATEGORY'],
                'messages' => $messages,
                'target_status' => [
                    'category' => $target->category->name,
                    'is_exceeded' => $isExceeded
                ]
            ], 200);
        }

        // --- LEVEL 3: EXACT DELIVERY MATRIX ---
        if ($specificity === '3_MATRIX') {
            $target = $trackingCode->targets->where('category_id', $request->input('category_id'))->first();
            
            // If the category is not mapped to the code at all
            if (!$target) {
                return response()->json([
                    'can_proceed' => true,
                    'context' => ['specificity_level' => '3_MATRIX'],
                    'messages' => ["Warning: This item category is not allocated to your office under this delivery schedule."],
                    'target_status' => [
                        'category' => 'Unallocated Category',
                        'is_exceeded' => true
                    ]
                ], 200);
            }

            // Check if an allocation cell exists for this specific location
            $allocation = DB::table('gov_tracking_allocations')
                ->where('target_id', $target->id)
                ->where('location_id', $request->input('location_id'))
                ->first();

            // If the office has no cell allocation, display an orange warning
            if (!$allocation) {
                return response()->json([
                    'can_proceed' => true, // Allowed to proceed
                    'context' => ['specificity_level' => '3_MATRIX'],
                    'messages' => ["Warning: This item category is not allocated to your office under this delivery schedule."],
                    'target_status' => [
                        'category' => $target->category->name,
                        'is_exceeded' => true
                    ]
                ], 200);
            }

            // Sum quantity received specifically at this warehouse location
            $receivedAtLocation = (int) DB::table('gov_tracking_associations')
                ->where('tracking_code_id', $trackingCode->id)
                ->where('category_id', $request->input('category_id'))
                ->where('location_id', $request->input('location_id'))
                ->where('status', 'ACTIVE')
                ->sum('quantity');

            $isExceeded = ($receivedAtLocation + $qtyToAdd) > $allocation->allocated_qty;
            
            $messages = [];
            if ($isExceeded) {
                $messages[] = "Warning: Your office has exceeded its specific delivery allocation of {$allocation->allocated_qty} units for {$target->category->name}.";
            }

            return response()->json([
                'can_proceed' => true, // Saving is never blocked on overshoot
                'context' => ['specificity_level' => '3_MATRIX'],
                'messages' => $messages,
                'target_status' => [
                    'category' => $target->category->name,
                    'is_exceeded' => $isExceeded
                ]
            ], 200);
        }
    }

    protected function buildContext(TrackingCode $trackingCode): array
    {
        return [
            'initiative'  => $trackingCode->initiative->title,
            'task'        => $trackingCode->task_title,
            'fiscal_year' => $trackingCode->fiscal_year,
        ];
    }
}