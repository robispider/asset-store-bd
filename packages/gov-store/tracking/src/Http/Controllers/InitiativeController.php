<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Location;
use GovStore\Tracking\Models\Initiative;
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
        $user = auth()->user();
        
        // Explicitly bypass global scopes to ensure admins always get their locations
        // regardless of the current TenantContext initialization state.
        $locations = $user->isSuperUser() 
            ? Location::withoutGlobalScopes()->get() 
            : Location::withoutGlobalScopes()->where('company_id', $user->company_id)->get();
            
        return view('govtracking::initiatives.create', compact('companies', 'locations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'purpose' => 'nullable|string',
            'status' => 'required|in:Planning,Active,Closed,Archived',
            'primary_funding' => 'required|in:ADP,REVENUE,OTHER',
            
            // Governance Rules
            'require_documents' => 'boolean',
            'allow_overshoot' => 'boolean',
            
            // Ownership & Management
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
            
        $projectionRepo = app(\GovStore\Tracking\Repositories\TrackingProjectionRepositoryInterface::class);
        $health = $projectionRepo->getLifecycleSummary($initiative);
        
        // --- UPDATED: Dynamic Specificity-Aware Calculation Router ---
        foreach ($trackingCodes as $code) {
            if ($code->specificity_level === '3_MATRIX') {
                // Compile geography-grouped allocation matrices
                $code->matrixProgress = $projectionRepo->getMatrixProgress($code->id);
            } else {
                // Compile standard global category summaries
                foreach ($code->targets as $target) {
                    $target->progress = $projectionRepo->getTargetProgress($code->id, $target->category_id);
                }
            }
        }
            
        $recentActivity = \GovStore\Tracking\Models\TrackingTimeline::with('actor')
            ->where('initiative_id', $initiative->id)
            ->orderBy('occurred_at', 'desc')
            ->limit(20)
            ->get();
        
        return view('govtracking::initiatives.workspace', compact('initiative', 'health', 'trackingCodes', 'recentActivity'));
    }
}