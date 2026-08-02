<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Repositories\TrackingProjectionRepositoryInterface;
use GovStore\Tracking\Services\TrackingAuthorizationService; // Added
use GovStore\Organization\Models\CompanyAdmin; // Added
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InitiativeController extends Controller
{
    protected TrackingAuthorizationService $authService;

    public function __construct(TrackingAuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    public function index()
    {
        $initiatives = Initiative::with('ownerCompany')->get();
        return view('govtracking::initiatives.index', compact('initiatives'));
    }

    public function create()
    {
        $user = auth()->user();

        if ($user->isSuperUser()) {
            $companies = Company::withoutGlobalScopes()->get();
        } else {
            // FIXED: Dynamically resolve the admin's company ID from your native CompanyAdmin model
            $companyId = CompanyAdmin::where('user_id', $user->id)->value('company_id');

            $companies = Company::withoutGlobalScopes()
                ->where('id', $companyId)
                ->get();
        }
            
        return view('govtracking::initiatives.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'purpose'           => 'nullable|string',
            'status'            => 'required|in:Planning,Active,Closed,Archived',
            'primary_funding'   => 'required|in:ADP,REVENUE,OTHER',
            'require_documents' => 'boolean',
            'allow_overshoot'   => 'boolean',
            'owner_company_id'  => 'required|exists:companies,id',
        ]);

        $validated['require_documents'] = $request->has('require_documents');
        $validated['allow_overshoot']   = $request->has('allow_overshoot');

        $initiative = Initiative::create($validated);

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', 'Initiative created successfully. Please assign an Operation Unit team.');
    }

    public function show(Initiative $initiative)
    {
        $initiative->load(['ownerCompany', 'operationUnits.user']);
        
        $trackingCodes = \GovStore\Tracking\Models\TrackingCode::with([
            'targets.category' => function($query) {
                $query->withTrashed(); 
            }, 
            'scopes', 
            'fundingType'
        ])
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
        $initiative->load(['ownerCompany', 'operationUnits.user']);

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

    public function edit(Initiative $initiative)
    {
        // GATED (Centralized): Only HEAD (Operation Head) or Company Admin can edit
        $this->authService->authorize($initiative, ['HEAD']);

        $user = auth()->user();

        if ($user->isSuperUser()) {
            $companies = Company::withoutGlobalScopes()->get();
        } else {
            $companyId = CompanyAdmin::where('user_id', $user->id)->value('company_id');

            $companies = Company::withoutGlobalScopes()
                ->where('id', $companyId)
                ->get();
        }

        return view('govtracking::initiatives.edit', compact('initiative', 'companies'));
    }

    public function update(Request $request, Initiative $initiative)
    {
        // GATED (Centralized): Only HEAD (Operation Head) or Company Admin can update
        $this->authService->authorize($initiative, ['HEAD']);

        $rules = [
            'title'             => 'required|string|max:255',
            'purpose'           => 'nullable|string',
            'status'            => 'required|in:Planning,Active,Closed,Archived',
            'require_documents' => 'boolean',
            'allow_overshoot'   => 'boolean',
        ];

        if ($initiative->status === 'Planning') {
            $rules['primary_funding']  = 'required|in:ADP,REVENUE,OTHER';
            $rules['owner_company_id'] = 'required|exists:companies,id';
        }

        $request->validate($rules);

        if ($initiative->status !== 'Planning') {
            if ($request->filled('primary_funding') && $request->input('primary_funding') !== $initiative->primary_funding) {
                throw ValidationException::withMessages(['primary_funding' => 'Immutable Error: Core Funding Segment cannot be changed once an initiative has been promoted past the Planning stage.']);
            }
            if ($request->filled('owner_company_id') && (int)$request->input('owner_company_id') !== (int)$initiative->owner_company_id) {
                throw ValidationException::withMessages(['owner_company_id' => 'Immutable Error: Owning Organization cannot be changed once an initiative has been promoted past the Planning stage.']);
            }
        }

        if ($request->input('status') === 'Active' && !$initiative->isOperationallyReady()) {
            throw ValidationException::withMessages([
                'status' => 'Readiness Block: You cannot activate this initiative. You must first assign an Operation Head and at least one Operation Officer in the Operation Unit console.'
            ]);
        }

        $initiative->update([
            'title'             => $request->input('title'),
            'purpose'           => $request->input('purpose'),
            'status'            => $request->input('status'),
            'require_documents' => $request->has('require_documents'),
            'allow_overshoot'   => $request->has('allow_overshoot'),
            'primary_funding'   => $initiative->status === 'Planning' ? $request->input('primary_funding') : $initiative->primary_funding,
            'owner_company_id'  => $initiative->status === 'Planning' ? $request->input('owner_company_id') : $initiative->owner_company_id,
        ]);

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', 'Initiative properties updated successfully.');
    }

    public function destroy(Initiative $initiative)
    {
        // GATED (Centralized): Only HEAD (Operation Head) or Company Admin can delete
        $this->authService->authorize($initiative, ['HEAD']);

        if ($initiative->status !== 'Planning') {
            return redirect()->back()->with('error', 'Cannot delete active, closed, or archived initiatives. You must archive it instead.');
        }

        $initiative->delete();

        return redirect()->route('gov.tracking.initiatives.index')
                         ->with('success', 'Initiative has been deleted.');
    }
}