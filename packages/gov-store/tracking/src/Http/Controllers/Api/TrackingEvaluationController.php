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

    public function evaluate(Request $request)
    {
        $request->validate([
            'code'        => 'required|string',
            'location_id' => 'required|integer|exists:locations,id',
            'category_id' => 'required|integer|exists:categories,id',
            'qty'         => 'required|integer|min:1',
        ]);

        $trackingCode = TrackingCode::with(['initiative', 'targets.category'])
            ->where('tracking_code', $request->input('code'))
            ->where('status', 'ACTIVE') // Only active codes allowed on GRNs
            ->first();

        if (!$trackingCode) {
            return response()->json([
                'can_proceed' => false,
                'override_required' => false,
                'messages' => ['Invalid or Inactive Tracking Code. This code is not authorized for operations.']
            ], 404);
        }

        $initiative = $trackingCode->initiative;
        $specificity = $trackingCode->specificity_level;
        $qtyToAdd = (int) $request->input('qty');

        // 1. Validate Scopes (Geography and Participants)
        $scopeCheck = $this->scopeValidator->validateExecutionScope($trackingCode, $request->input('location_id'));
        if (!$scopeCheck['is_valid']) {
            return response()->json([
                'can_proceed' => false,
                'override_required' => false, // Hard block on scope breaches
                'context' => $this->buildContext($trackingCode),
                'messages' => [$scopeCheck['message']],
            ], 403);
        }

        // =====================================================================
        // 2. CASCADING EVALUATION MATRIX
        // =====================================================================
        
        // --- LEVEL 1: BLANKET CODE ---
        if ($specificity === '1_BLANKET') {
            return response()->json([
                'can_proceed' => true,
                'override_required' => false,
                'context' => $this->buildContext($trackingCode),
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
            
            if (!$target) {
                return response()->json([
                    'can_proceed' => false,
                    'override_required' => false,
                    'messages' => ["This Tracking Code does not authorize the procurement of this item category."]
                ], 403);
            }

            // Sum global received quantity
            $receivedQty = (int) DB::table('gov_tracking_associations')
                ->where('tracking_code_id', $trackingCode->id)
                ->where('category_id', $request->input('category_id'))
                ->where('status', 'ACTIVE')
                ->sum('quantity');

            $isExceeded = ($receivedQty + $qtyToAdd) > $target->planned_qty;
            
            // OPERATIONAL EXCEPTION RULE: Level 2 never forces overshoot block/override.
            // Only returns silent warnings in the messages array.
            $messages = [];
            if ($isExceeded) {
                $messages[] = "Operational Notice: The shared category allocation for '{$target->category->name}' has been exceeded globally.";
            }

            return response()->json([
                'can_proceed' => true, // Always allowed
                'override_required' => false, // Never force lockouts on Level 2
                'context' => $this->buildContext($trackingCode),
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
            
            if (!$target) {
                return response()->json([
                    'can_proceed' => false,
                    'override_required' => false,
                    'messages' => ["This Tracking Code does not authorize this item category."]
                ], 403);
            }

            // Verify if an allocation cell exists specifically for this warehouse location
            $allocation = DB::table('gov_tracking_allocations')
                ->where('target_id', $target->id)
                ->where('location_id', $request->input('location_id'))
                ->first();

            if (!$allocation) {
                return response()->json([
                    'can_proceed' => false,
                    'override_required' => false,
                    'messages' => ["This warehouse is not authorized to receive items under this specific delivery matrix."]
                ], 403);
            }

            // Sum quantity received *specifically* at this location
            $receivedAtLocation = (int) DB::table('gov_tracking_associations')
                ->where('tracking_code_id', $trackingCode->id)
                ->where('category_id', $request->input('category_id'))
                ->where('location_id', $request->input('location_id'))
                ->where('status', 'ACTIVE')
                ->sum('quantity');

            $isExceeded = ($receivedAtLocation + $qtyToAdd) > $allocation->allocated_qty;
            
            $canProceed = true;
            $overrideRequired = false;
            $messages = [];

            if ($isExceeded) {
                if ($initiative->allow_overshoot) {
                    $messages[] = "Location allocation exceeded (Advisory Warning).";
                } else {
                    $canProceed = false;
                    $overrideRequired = true;
                    $messages[] = "Location allocation limit of {$allocation->allocated_qty} reached. Authorized override justification required.";
                }
            }

            return response()->json([
                'can_proceed' => $canProceed,
                'override_required' => $overrideRequired,
                'context' => $this->buildContext($trackingCode),
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
    public function checkUniqueness(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:100'
        ]);

        // Returns true if the code does NOT exist in the database (meaning it is available)
        $exists = TrackingCode::where('tracking_code', $request->input('code'))->exists();

        return response()->json([
            'is_unique' => !$exists
        ], 200);
    }
}