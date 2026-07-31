<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Location;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Repositories\TrackingProjectionRepositoryInterface;
use Illuminate\Http\Request;

class InitiativeController extends Controller
{
    public function index()
    {
        $initiatives = Initiative::with('ownerCompany')->get();
        return view('govtracking::initiatives.index', compact('initiatives'));
    }

    public function create()
    {
        $companies = Company::all();
        
        $locations = Location::all();
        $user = auth()->user();
        if ($locations->isEmpty() && $user) {
            $locations = $user->isSuperUser() 
                ? Location::withoutGlobalScopes()->get() 
                : Location::withoutGlobalScopes()->where('company_id', $user->company_id)->get();
        }
        
        return view('govtracking::initiatives.create', compact('companies', 'locations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'purpose' => 'nullable|string',
            'status' => 'required|in:Planning,Active,Closed,Archived',
            'primary_funding' => 'required|in:ADP,REVENUE,OTHER',
            'require_documents' => 'boolean',
            'allow_overshoot' => 'boolean',
            'owner_company_id' => 'required|exists:companies,id',
            'manager_location_id' => 'required|exists:locations,id',
        ]);

        $validated['require_documents'] = $request->has('require_documents');
        $validated['allow_overshoot'] = $request->has('allow_overshoot');

        $initiative = Initiative::create($validated);

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', 'Initiative created successfully.');
    }

    public function show(Initiative $initiative)
    {
        $initiative->load(['ownerCompany', 'managingOffice']);
        
        $trackingCodes = \GovStore\Tracking\Models\TrackingCode::with(['targets.category', 'scopes', 'fundingType'])
            ->where('initiative_id', $initiative->id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        $recentActivity = \GovStore\Tracking\Models\TrackingTimeline::with('actor')
            ->where('initiative_id', $initiative->id)
            ->orderBy('occurred_at', 'desc')
            ->limit(20)
            ->get();
        
        $projectionRepo = app(TrackingProjectionRepositoryInterface::class);
        $health = $projectionRepo->getLifecycleSummary($initiative);
        
        foreach ($trackingCodes as $code) {
            if ($code->specificity_level === '3_MATRIX') {
                $code->matrixProgress = $projectionRepo->getMatrixProgress($code->id);
            } else {
                foreach ($code->targets as $target) {
                    $target->progress = $projectionRepo->getTargetProgress($code->id, $target->category_id);
                }
            }
        }
        
        return view('govtracking::initiatives.workspace', compact('initiative', 'health', 'trackingCodes', 'recentActivity'));
    }

    /**
     * NEW: Compiles all analytical, geographical, and fiscal progress indicators
     * into a unified executive report.
     */
    /**
     * Compiles all analytical, geographical, and fiscal progress indicators
     * into a unified, high-performance executive report.
     */
    public function report(Initiative $initiative, TrackingProjectionRepositoryInterface $projectionRepo)
    {
        $initiative->load(['ownerCompany', 'managingOffice']);

        // 1. Compile overall macro health
        $health = $projectionRepo->getLifecycleSummary($initiative);

        // 2. Load tracking codes and resolve their micro-progress
        $trackingCodes = \GovStore\Tracking\Models\TrackingCode::with(['targets.category', 'scopes', 'fundingType'])
            ->where('initiative_id', $initiative->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($trackingCodes as $code) {
            if ($code->specificity_level === '3_MATRIX') {
                $code->matrixProgress = $projectionRepo->getMatrixProgress($code->id);
            } else {
                foreach ($code->targets as $target) {
                    $target->progress = $projectionRepo->getTargetProgress($code->id, $target->category_id);
                }
            }
        }

        // 3. NEW: Load the pre-compiled analytical facts from the Delivery Cube (Bypassing global location scopes)
        $facts = \GovStore\Tracking\Models\TrackingFactDelivery::with([
            'category',
            'trackingCode',
            'location' => function($query) {
                $query->withoutGlobalScopes(); // Prevents empty lists for non-contextual admins
            }
        ])
        ->where('initiative_id', $initiative->id)
        ->get();

        // Section 4 Timeline is completely removed as requested.

        return view('govtracking::initiatives.report', compact('initiative', 'health', 'trackingCodes', 'facts'));
    }
}