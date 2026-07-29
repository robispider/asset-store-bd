<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use GovStore\Tracking\Models\TrackingReference;
use GovStore\Tracking\Repositories\TrackingProjectionRepositoryInterface;
use GovStore\Tracking\Services\TimelineCompilerService;

class TrackingDashboardController extends Controller
{
    protected TrackingProjectionRepositoryInterface $projectionRepo;
    protected TimelineCompilerService $timelineCompiler;

    public function __construct(
        TrackingProjectionRepositoryInterface $projectionRepo,
        TimelineCompilerService $timelineCompiler
    ) {
        $this->projectionRepo = $projectionRepo;
        $this->timelineCompiler = $timelineCompiler;
    }

    public function show(TrackingReference $reference)
    {
        $reference->load('trackingType');
        
        // Resolve metrics from decoupled read model projection repository
        $metrics = $this->projectionRepo->getLifecycleSummary($reference);
        
        // Compile audit log timeline
        $timeline = $this->timelineCompiler->compileUnifiedTimeline($reference);

        return view('govtracking::references.dashboard', compact('reference', 'metrics', 'timeline'));
    }
}
