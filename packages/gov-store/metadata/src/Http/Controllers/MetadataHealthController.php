<?php

namespace GovStore\Metadata\Http\Controllers;

use Illuminate\Routing\Controller;
use GovStore\Metadata\Services\MetadataHealthService;

class MetadataHealthController extends Controller
{
    protected MetadataHealthService $healthService;

    public function __construct(MetadataHealthService $healthService)
    {
        $this->healthService = $healthService;
    }

    public function show()
    {
        // Require superuser or administrative permissions
        if (!auth()->user()->isSuperUser() && !auth()->user()->hasAccess('admin')) {
            abort(403, 'Unauthorized access.');
        }

        $report = $this->healthService->generateReport();

        return view('govmeta::health', compact('report'));
    }
}