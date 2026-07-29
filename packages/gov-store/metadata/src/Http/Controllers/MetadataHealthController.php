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
        if (!auth()->user()->isSuperUser() && !auth()->user()->hasAccess('admin')) {
            abort(403, 'Unauthorized access.');
        }

        $report = $this->healthService->generateReport();

        return view('govmeta::health', compact('report'));
    }

    /**
     * Dispatches the convergence queue job and redirects back.
     */
    public function trigger()
    {
        if (!auth()->user()->isSuperUser() && !auth()->user()->hasAccess('admin')) {
            abort(403, 'Unauthorized access.');
        }

        // Dispatch job to the queue
        ConvergeMetadataJob::dispatch();

        return redirect()->back()->with('success', 'Metadata convergence process has been successfully queued in the background.');
    }
}