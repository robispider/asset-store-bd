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

    public function checkUniqueness(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:100'
        ]);

        $exists = TrackingCode::where('tracking_code', $request->input('code'))->exists();

        return response()->json([
            'is_unique' => !$exists
        ], 200);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'code'        => 'required|string',
            'location_id' => 'required|integer|exists:locations,id',
        ]);

        // Eager load without global scopes to prevent tenant blocks
        $trackingCode = TrackingCode::with([
            'initiative' => function($query) {
                $query->withoutGlobalScopes();
            }
        ])
            ->where('tracking_code', $request->input('code'))
            ->where('status', 'ACTIVE')
            ->first();

        if (!$trackingCode) {
            return response()->json([
                'can_proceed' => false,
                'messages' => ['Invalid or Inactive Tracking Code. Selection blocked.']
            ], 403);
        }

        $initiative = $trackingCode->initiative;

        if (!$initiative) {
            return response()->json([
                'can_proceed' => false,
                'messages' => ['Unauthorized. Parent initiative is inaccessible.']
            ], 403);
        }

        // 1. INITIATIVE LIFECYCLE STATE CHECK
        if ($initiative->status !== 'Active') {
            return response()->json([
                'can_proceed' => false,
                'messages' => [$this->getLifecycleBlockMessage($initiative->title, $initiative->status)]
            ], 403);
        }

        // 2. Verify Geographical and Organizational Visibility
        $scopeCheck = $this->scopeValidator->validateExecutionScope($trackingCode, $request->input('location_id'));
        if (!$scopeCheck['is_valid']) {
            return response()->json([
                'can_proceed' => false,
                'messages' => [$scopeCheck['message']]
            ], 403);
        }

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

    public function evaluate(Request $request)
    {
        $request->validate([
            'code'        => 'required|string',
            'location_id' => 'required|integer|exists:locations,id',
            'category_id' => 'required|integer|exists:categories,id',
            'qty'         => 'required|integer|min:1',
        ]);

        $trackingCode = TrackingCode::with([
            'initiative' => function($query) {
                $query->withoutGlobalScopes();
            }, 
            'targets'
        ])
            ->where('tracking_code', $request->input('code'))
            ->where('status', 'ACTIVE')
            ->first();

        if (!$trackingCode) {
            return response()->json([
                'can_proceed' => false,
                'messages' => ['Invalid or Inactive Tracking Code.']
            ], 403);
        }

        $initiative = $trackingCode->initiative;

        if (!$initiative) {
            return response()->json([
                'can_proceed' => false,
                'messages' => ['Unauthorized. Parent initiative is inaccessible.']
            ], 403);
        }

        if ($initiative->status !== 'Active') {
            return response()->json([
                'can_proceed' => false,
                'messages' => [$this->getLifecycleBlockMessage($initiative->title, $initiative->status)]
            ], 403);
        }

        $specificity = $trackingCode->specificity_level;
        $qtyToAdd = (int) $request->input('qty');

        $scopeCheck = $this->scopeValidator->validateExecutionScope($trackingCode, $request->input('location_id'));
        if (!$scopeCheck['is_valid']) {
            return response()->json([
                'can_proceed' => false,
                'messages' => [$scopeCheck['message']],
            ], 403);
        }

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
            
            if (!$target) {
                return response()->json([
                    'can_proceed' => true, 
                    'context' => ['specificity_level' => '2_CATEGORY'],
                    'messages' => ["Warning: This item category is not in the allocated planning targets."],
                    'target_status' => [
                        'category' => 'Unallocated Category',
                        'is_exceeded' => true
                    ]
                ], 200);
            }

            return response()->json([
                'can_proceed' => true,
                'context' => ['specificity_level' => '2_CATEGORY'],
                'messages' => [], 
                'target_status' => [
                    'category' => $target->category->name,
                    'is_exceeded' => false
                ]
            ], 200);
        }

        // --- LEVEL 3: EXACT DELIVERY MATRIX ---
        if ($specificity === '3_MATRIX') {
            $target = $trackingCode->targets->where('category_id', $request->input('category_id'))->first();
            
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

            $allocation = DB::table('gov_tracking_allocations')
                ->where('target_id', $target->id)
                ->where('location_id', $request->input('location_id'))
                ->first();

            if (!$allocation) {
                return response()->json([
                    'can_proceed' => true,
                    'context' => ['specificity_level' => '3_MATRIX'],
                    'messages' => ["Warning: This item category is not allocated to your office under this delivery schedule."],
                    'target_status' => [
                        'category' => $target->category->name,
                        'is_exceeded' => true
                    ]
                ], 200);
            }

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
                'can_proceed' => true,
                'override_required' => false,
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

    protected function getLifecycleBlockMessage(string $title, string $status): string
    {
        $message = "Operational Block: ";

        if ($status === 'Planning') {
            $message .= "The initiative '{$title}' is currently in the Setup (Planning) phase and is not yet open for procurement operations.";
        } elseif ($status === 'Closed') {
            $message .= "The initiative '{$title}' has been officially Closed. New physical receipts (GRNs) under this budget are suspended.";
        } else {
            $message .= "The initiative '{$title}' has been Archived. All historical records are locked against future ledger transactions.";
        }

        return $message;
    }
}