<?php

namespace GovStore\Metadata\Http\Controllers;

use Illuminate\Routing\Controller;
use GovStore\Metadata\Services\MetadataHealthService;
use GovStore\Metadata\Jobs\ConvergeMetadataJob;

class MetadataHealthController extends Controller
{
    protected MetadataHealthService $healthService;

    public function __construct(MetadataHealthService $healthService)
    {
        $this->healthService = $healthService;
    }

    public function show()
    {
        if (!app(\GovStore\TenantScope\Services\GovAccess::class)->decide(auth()->user(), 'metadata.national.manage')->allowed) {
            abort(403, 'Unauthorized access.');
        }

        $report = $this->healthService->generateReport();
        $operations = \Illuminate\Support\Facades\DB::table('gov_reviewed_operations')->orderByDesc('id')->limit(20)->get();

        return view('govmeta::health', compact('report', 'operations'));
    }

    /**
     * Dispatches the convergence queue job and redirects back.
     */
    public function trigger(\Illuminate\Http\Request $request)
    {
        if (!app(\GovStore\TenantScope\Services\GovAccess::class)->decide(auth()->user(), 'metadata.national.manage')->allowed
            || $request->attributes->get('gov_national_reviewed') !== 'metadata.national.manage') {
            abort(403, 'Unauthorized access.');
        }

        // Dispatch job to the queue
        $operation = \Illuminate\Support\Facades\DB::table('gov_reviewed_operations')->insertGetId([
            'review_hash' => $request->attributes->get('gov_review_hash'), 'actor_id' => auth()->id(),
            'ability' => 'metadata.national.manage', 'reason' => $request->input('change_reason'),
            'plan_hash' => app(\GovStore\Metadata\Services\MetadataMaintenancePlan::class)->fingerprint(),
            'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
        ]);
        ConvergeMetadataJob::dispatch($operation)->afterCommit();

        return redirect()->back()->with('success', 'Metadata convergence process has been successfully queued in the background.');
    }
}
