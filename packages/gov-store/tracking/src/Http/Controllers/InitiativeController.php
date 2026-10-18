<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use GovStore\Organization\Models\CompanyAdmin;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode; // Added
use GovStore\Tracking\Models\TrackingTimeline; // Added
use GovStore\Tracking\Repositories\TrackingProjectionRepositoryInterface;
use GovStore\Tracking\Services\TrackingAuthorizationService;
use GovStore\Tracking\Services\TrackingMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // Add high-performance subqueries to count relations without N+1 bottlenecks
        $initiatives = Initiative::with('ownerCompany')
            ->withCount('trackingCodes')
            ->withCount('operationUnits')
            ->orderBy('updated_at', 'desc') // Ensure most recently active projects sit at the top
            ->get();

        return view('govtracking::initiatives.index', compact('initiatives'));
    }

    public function create()
    {
        abort_unless($this->authService->canCreate(), 403);
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
            'title' => 'required|string|max:255',
            'purpose' => 'nullable|string',
            'status' => 'required|in:Planning,Active,Closed,Archived',
            'primary_funding' => 'required|in:ADP,REVENUE,OTHER',
            'require_documents' => 'boolean',
            'allow_overshoot' => 'boolean',
            'owner_company_id' => 'required|exists:companies,id',
        ]);

        $this->authorizeCompany((int) $validated['owner_company_id']);
        abort_unless($validated['status'] === 'Planning', 422);

        $validated['require_documents'] = $request->has('require_documents');
        $validated['allow_overshoot'] = $request->has('allow_overshoot');

        $initiative = Initiative::create($validated);

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
            ->with('success', 'Initiative created successfully. Please assign an Operation Unit team.');
    }

    public function manage(Initiative $initiative)
    {
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER', 'SUPPORT', 'MONITOR']);
        $initiative->load(['ownerCompany', 'operationUnits.user']);

        // 1. Load the active Tracking Codes (Tasks) and their specific goals
        $trackingCodes = TrackingCode::with([
            'targets.category' => function ($query) {
                $query->withoutGlobalScopes();
            },
            'scopes',
            'fundingType',
        ])
            ->where('initiative_id', $initiative->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Load Recent Activity Timeline
        $recentActivity = TrackingTimeline::with('actor')
            ->where('initiative_id', $initiative->id)
            ->orderBy('occurred_at', 'desc')
            ->limit(20)
            ->get();

        // 3. Compile Progress Percentages for the Active Tasks
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

        // =====================================================================
        // 4. NEW: COMPILE THE PROGRAMME SNAPSHOT (The OLAP Cube Aggregator)
        // =====================================================================
        // Runs ultra-fast aggregate queries directly on our pre-compiled Fact Table

        $monitor = app(TrackingMonitorService::class)->programme($initiative);
        $rows = collect($monitor['receipts']);
        $snapshot = [
            'total_received_qty' => $monitor['received'], 'total_cost' => $monitor['value'],
            'total_shipments' => $monitor['grn_count'], 'distinct_locations' => $monitor['reached'],
            'distinct_geo_areas' => DB::table('gov_location_profiles')->whereIn('location_id', $rows->pluck('location_id'))->distinct()->count('geo_area_id'), 'distinct_categories' => $rows->pluck('category_id')->unique()->count(),
            'distinct_manufacturers' => $rows->pluck('manufacturer_id')->filter()->unique()->count(),
            'distinct_suppliers' => $monitor['supplier_count'],
        ];

        return view('govtracking::initiatives.workspace', compact(
            'initiative', 'health', 'trackingCodes', 'recentActivity', 'snapshot'
        ));
    }

    public function show(Initiative $initiative, Request $request)
    {
        $filters = $request->validate(['status' => 'nullable|in:all,ACTIVE,DRAFT',
            'plan' => 'nullable|in:all,1_BLANKET,2_CATEGORY,3_MATRIX', 'q' => 'nullable|string|max:150']);
        $monitor = app(TrackingMonitorService::class)->programme($initiative, $filters);

        return view('govtracking::initiatives.monitor', compact('initiative', 'monitor'));
    }

    public function report(Initiative $initiative, TrackingProjectionRepositoryInterface $projectionRepo)
    {
        $monitor = app(TrackingMonitorService::class)->programme($initiative);

        return view('govtracking::initiatives.report', compact('initiative', 'monitor'));
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
        $initiative = Initiative::whereKey($initiative->id)->lockForUpdate()->firstOrFail();
        // GATED (Centralized): Only HEAD (Operation Head) or Company Admin can update
        $this->authService->authorize($initiative, ['HEAD']);

        $rules = [
            'title' => 'required|string|max:255',
            'purpose' => 'nullable|string',
            'status' => 'required|in:Planning,Active,Closed,Archived',
            'require_documents' => 'boolean',
            'allow_overshoot' => 'boolean',
        ];

        if ($initiative->status === 'Planning') {
            $rules['primary_funding'] = 'required|in:ADP,REVENUE,OTHER';
            $rules['owner_company_id'] = 'required|exists:companies,id';
        }

        $request->validate($rules);
        if ($initiative->status === 'Planning') {
            $this->authorizeCompany($request->integer('owner_company_id'));
        }

        if ($initiative->status !== 'Planning') {
            if ($request->filled('primary_funding') && $request->input('primary_funding') !== $initiative->primary_funding) {
                throw ValidationException::withMessages(['primary_funding' => 'Immutable Error: Core Funding Segment cannot be changed once an initiative has been promoted past the Planning stage.']);
            }
            if ($request->filled('owner_company_id') && (int) $request->input('owner_company_id') !== (int) $initiative->owner_company_id) {
                throw ValidationException::withMessages(['owner_company_id' => 'Immutable Error: Owning Organization cannot be changed once an initiative has been promoted past the Planning stage.']);
            }
        }

        if ($request->input('status') === 'Active' && ! $initiative->isOperationallyReady()) {
            throw ValidationException::withMessages([
                'status' => 'Readiness Block: You cannot activate this initiative. You must first assign an Operation Head and at least one Operation Officer in the Operation Unit console.',
            ]);
        }

        $initiative->update([
            'title' => $request->input('title'),
            'purpose' => $request->input('purpose'),
            'status' => $request->input('status'),
            'require_documents' => $request->has('require_documents'),
            'allow_overshoot' => $request->has('allow_overshoot'),
            'primary_funding' => $initiative->status === 'Planning' ? $request->input('primary_funding') : $initiative->primary_funding,
            'owner_company_id' => $initiative->status === 'Planning' ? $request->input('owner_company_id') : $initiative->owner_company_id,
        ]);

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
            ->with('success', 'Initiative properties updated successfully.');
    }

    public function destroy(Initiative $initiative)
    {
        $initiative = Initiative::whereKey($initiative->id)->lockForUpdate()->firstOrFail();
        // GATED (Centralized): Only HEAD (Operation Head) or Company Admin can delete
        $this->authService->authorize($initiative, ['HEAD']);

        if ($initiative->status !== 'Planning') {
            return redirect()->back()->with('error', 'Cannot delete active, closed, or archived initiatives. You must archive it instead.');
        }

        abort_if($initiative->trackingCodes()->exists(), 409);
        $initiative->delete();

        return redirect()->route('gov.tracking.initiatives.index')
            ->with('success', 'Initiative has been deleted.');
    }

    private function authorizeCompany(int $companyId): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isSuperUser() || CompanyAdmin::where('user_id', $user->id)->where('company_id', $companyId)->exists()), 403);
    }
}
