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
        ]);

        $trackingCode = TrackingCode::with(['initiative', 'targets.category'])
            ->where('tracking_code', $request->input('code'))
            ->first();

        if (!$trackingCode) {
            return response()->json([
                'can_proceed' => false,
                'override_required' => false,
                'messages' => ['Invalid Tracking Code. This code does not exist in the system.']
            ], 404);
        }

        $initiative = $trackingCode->initiative;

        // 1. Check Lifecycle Status
        if ($initiative->status !== 'Active') {
            return response()->json([
                'can_proceed' => false,
                'override_required' => false,
                'messages' => ["The umbrella initiative '{$initiative->title}' is currently marked as '{$initiative->status}' and is not accepting transactions."]
            ], 403);
        }

        // 2. Validate Geographical and Organizational Scope
        $scopeCheck = $this->scopeValidator->validateExecutionScope($trackingCode, $request->input('location_id'));
        if (!$scopeCheck['is_valid']) {
            return response()->json([
                'can_proceed' => false,
                'override_required' => true,
                'context' => $this->buildContext($trackingCode),
                'messages' => [$scopeCheck['message']],
            ], 403);
        }

        // 3. Validate Quantitative Targets for the requested Category
        $target = $trackingCode->targets->where('category_id', $request->input('category_id'))->first();
        
        if (!$target) {
            $categoryName = Category::find($request->input('category_id'))->name ?? 'this category';
            return response()->json([
                'can_proceed' => false,
                'override_required' => true,
                'context' => $this->buildContext($trackingCode),
                'messages' => ["This Tracking Code does not authorize the procurement of {$categoryName}."],
            ], 403);
        }

        // Note: In Phase 5 (The Ledger Bridge), we will query gov_tracking_associations here.
        // For Phase 4, we perform a safe-check to see if the table exists yet, otherwise assume 0 received.
        $receivedQty = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('gov_tracking_associations')) {
            // Mathematical Projection: Calculate total received by counting polymorphic links to Assets
        // filtered by the exact Category ID.
        // --- DYNAMIC TABLE NAME RESOLUTION ---
        // Mathematical Projection: Calculate total received by summing quantities directly
        // from our own local associations table.
        $receivedQty = (int) DB::table('gov_tracking_associations')
            ->where('tracking_code_id', $trackingCode->id)
            ->where('category_id', $request->input('category_id'))
            ->where('status', 'ACTIVE')
            ->sum('quantity');
        }

        $isExceeded = ($receivedQty >= $target->planned_qty);
        $allowOvershoot = $initiative->allow_overshoot;

        $canProceed = true;
        $messages = [];
        $overrideRequired = false;

        if ($isExceeded) {
            if ($allowOvershoot) {
                $messages[] = "Target allocation exceeded, but the Initiative policy allows overshoot (Informational Warning).";
            } else {
                $canProceed = false;
                $overrideRequired = true;
                $messages[] = "Target allocation for {$target->category->name} has been reached or exceeded. An authorized override is required.";
            }
        }

        return response()->json([
            'can_proceed'       => $canProceed,
            'override_required' => $overrideRequired,
            'context'           => $this->buildContext($trackingCode),
            'messages'          => $messages,
            'target_status'     => [
                'category'    => $target->category->name,
                'is_exceeded' => $isExceeded
            ]
        ], 200);
    }

    /**
     * Helper to build the secure, trimmed context array.
     */
    protected function buildContext(TrackingCode $trackingCode): array
    {
        return [
            'initiative'  => $trackingCode->initiative->title,
            'task'        => $trackingCode->task_title,
            'fiscal_year' => $trackingCode->fiscal_year,
        ];
    }
}