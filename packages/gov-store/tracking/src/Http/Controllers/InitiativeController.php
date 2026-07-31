<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Location;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Repositories\TrackingProjectionRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        
        // Defensive Scope Fallback for Locations
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

    public function report(Initiative $initiative, TrackingProjectionRepositoryInterface $projectionRepo)
    {
        $initiative->load(['ownerCompany', 'managingOffice']);

        $health = $projectionRepo->getLifecycleSummary($initiative);

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

        $facts = \GovStore\Tracking\Models\TrackingFactDelivery::with([
            'category',
            'trackingCode',
            'location' => function($query) {
                $query->withoutGlobalScopes();
            }
        ])
        ->where('initiative_id', $initiative->id)
        ->get();

        return view('govtracking::initiatives.report', compact('initiative', 'health', 'trackingCodes', 'facts'));
    }

    // NEW: Render the state-aware Edit form
    public function edit(Initiative $initiative)
    {
        $companies = Company::all();
        
        // Defensive Scope Fallback for Locations
        $locations = Location::all();
        $user = auth()->user();
        if ($locations->isEmpty() && $user) {
            $locations = $user->isSuperUser() 
                ? Location::withoutGlobalScopes()->get() 
                : Location::withoutGlobalScopes()->where('company_id', $user->company_id)->get();
        }

        return view('govtracking::initiatives.edit', compact('initiative', 'companies', 'locations'));
    }

    // NEW: Handle updates while enforcing immutable core parameters past the Planning stage
    public function update(Request $request, Initiative $initiative)
    {
        $rules = [
            'title' => 'required|string|max:255',
            'purpose' => 'nullable|string',
            'status' => 'required|in:Planning,Active,Closed,Archived',
            'manager_location_id' => 'required|exists:locations,id',
            'require_documents' => 'boolean',
            'allow_overshoot' => 'boolean',
        ];

        // Only require and validate core dimensions if the Initiative is still in Setup (Planning)
        if ($initiative->status === 'Planning') {
            $rules['primary_funding'] = 'required|in:ADP,REVENUE,OTHER';
            $rules['owner_company_id'] = 'required|exists:companies,id';
        }

        $request->validate($rules);

        // Immutable Guard: Prevent modifying core parameters if promoted past Planning
        if ($initiative->status !== 'Planning') {
            if ($request->filled('primary_funding') && $request->input('primary_funding') !== $initiative->primary_funding) {
                throw ValidationException::withMessages(['primary_funding' => 'Immutable Error: Core Funding Segment cannot be changed once an initiative has been promoted past the Planning stage.']);
            }
            if ($request->filled('owner_company_id') && (int)$request->input('owner_company_id') !== (int)$initiative->owner_company_id) {
                throw ValidationException::withMessages(['owner_company_id' => 'Immutable Error: Owning Organization cannot be changed once an initiative has been promoted past the Planning stage.']);
            }
        }

        $initiative->update([
            'title' => $request->input('title'),
            'purpose' => $request->input('purpose'),
            'status' => $request->input('status'),
            'manager_location_id' => $request->input('manager_location_id'),
            'require_documents' => $request->has('require_documents'),
            'allow_overshoot' => $request->has('allow_overshoot'),
            // Only update core values if we are in Planning
            'primary_funding' => $initiative->status === 'Planning' ? $request->input('primary_funding') : $initiative->primary_funding,
            'owner_company_id' => $initiative->status === 'Planning' ? $request->input('owner_company_id') : $initiative->owner_company_id,
        ]);

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', 'Initiative properties updated successfully.');
    }

    // NEW: Destroys the Initiative. strictly blocked if it has been promoted past Planning.
    public function destroy(Initiative $initiative)
    {
        if ($initiative->status !== 'Planning') {
            return redirect()->back()->with('error', 'Cannot delete active, closed, or archived initiatives. You must archive it instead.');
        }

        $initiative->delete();

        return redirect()->route('gov.tracking.initiatives.index')
                         ->with('success', 'Initiative has been deleted.');
    }
}