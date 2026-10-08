<?php

namespace GovStore\Tracking\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Services\ProgrammeVerifier;
use GovStore\Tracking\Services\TrackingAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Browser session handshake. This is not a bearer-token/public API. */
class TrackingEvaluationController extends Controller
{
    public function __construct(private ProgrammeVerifier $verifier) {}

    public function checkUniqueness(Request $request)
    {
        $request->validate(['code' => 'required|string|max:100', 'initiative_id' => 'required|integer']);
        $initiative = Initiative::findOrFail($request->integer('initiative_id'));
        app(TrackingAuthorizationService::class)->authorize($initiative, ['HEAD', 'OFFICER']);
        return response()->json(['is_unique' => ! TrackingCode::where('tracking_code', $request->input('code'))->exists()]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate(['code' => 'required|string|max:100', 'location_id' => 'required|integer']);
        try {
            $task = $this->verifier->resolve($request->input('code'), $request->integer('location_id'));
        } catch (ValidationException $e) {
            return response()->json(['can_proceed' => false, 'messages' => collect($e->errors())->flatten()->all()], 403);
        }
        return response()->json([
            'can_proceed' => true,
            'context' => $this->context($task) + [
                'pdf_download_url' => $task->order_pdf_path && Storage::disk('local')->exists($task->order_pdf_path)
                    ? route('gov.tracking.tracking-codes.download', $task->id) : null,
                'visual_viewer_url' => route('gov.tracking.tracking-codes.view-task', $task->id),
            ],
            'messages' => [],
        ]);
    }

    public function evaluate(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:100', 'location_id' => 'required|integer',
            'category_id' => 'required|integer|exists:categories,id', 'qty' => 'required|integer|min:1',
        ]);
        try {
            $task = $this->verifier->resolve($request->input('code'), $request->integer('location_id'));
        } catch (ValidationException $e) {
            return response()->json(['can_proceed' => false, 'messages' => collect($e->errors())->flatten()->all()], 403);
        }
        $target = $task->targets()->with('category')->where('category_id', $request->integer('category_id'))->first();
        $limit = $target?->planned_qty;
        $received = TrackingAssociation::where('tracking_code_id', $task->id)->where('status', 'ACTIVE')
            ->where('category_id', $request->integer('category_id'));
        if ($task->specificity_level === '3_MATRIX') {
            $limit = $target ? DB::table('gov_tracking_allocations')->where('target_id', $target->id)
                ->where('location_id', $request->integer('location_id'))->value('allocated_qty') : null;
            $received->where('location_id', $request->integer('location_id'));
        }
        abort_unless(in_array($task->specificity_level, ['1_BLANKET', '2_CATEGORY', '3_MATRIX'], true), 409);
        $exceeded = $task->specificity_level !== '1_BLANKET'
            && ($limit === null || $received->sum('quantity') + $request->integer('qty') > $limit);
        return response()->json([
            'can_proceed' => true, 'override_required' => false, 'context' => $this->context($task),
            'messages' => $exceeded ? [__('govtracking::general.allocation_warning')] : [],
            'target_status' => ['category' => $target?->category?->name ?? __('govtracking::general.unallocated_category'), 'is_exceeded' => $exceeded],
        ]);
    }

    private function context(TrackingCode $task): array
    {
        return ['initiative' => $task->initiative->title, 'task' => $task->task_title,
            'fiscal_year' => $task->fiscal_year, 'specificity_level' => $task->specificity_level];
    }
}
