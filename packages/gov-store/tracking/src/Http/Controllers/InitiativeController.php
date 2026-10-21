<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use GovStore\Organization\Models\CompanyAdmin;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Services\ProgrammeReportService;
use GovStore\Tracking\Services\TrackingAuthorizationService;
use GovStore\Tracking\Services\TrackingManageService;
use GovStore\Tracking\Services\TrackingMonitorService;
use GovStore\Tracking\Services\TrackingPortfolioService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InitiativeController extends Controller
{
    protected TrackingAuthorizationService $authService;

    public function __construct(TrackingAuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    public function index(Request $request, TrackingPortfolioService $portfolio)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:150', 'status' => 'nullable|in:all,Active,Planning,Closed,Archived',
            'funding' => 'nullable|in:all,ADP,REVENUE,OTHER', 'mine' => 'nullable|boolean',
            'sort' => 'nullable|in:'.implode(',', TrackingPortfolioService::SORTS), 'direction' => 'nullable|in:asc,desc',
            'view' => 'nullable|in:table,cards', 'page' => 'nullable|integer|min:1|max:1000000',
        ]);
        $preference = 'tracking.portfolio.view.'.auth()->id();
        if (isset($filters['view'])) $request->session()->put($preference, $filters['view']);
        $filters = array_replace(['status' => 'all', 'funding' => 'all', 'sort' => 'priority', 'direction' => 'asc',
            'view' => $request->session()->get($preference, 'table')], array_filter($filters, fn ($v) => $v !== null));

        return view('govtracking::initiatives.index', $portfolio->portfolio($filters));
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

    public function manage(Initiative $initiative, Request $request, TrackingManageService $manage)
    {
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER', 'SUPPORT', 'MONITOR']);
        $filters = $request->validate(['tab' => 'nullable|in:'.implode(',', TrackingManageService::TABS),
            'q' => 'nullable|string|max:150', 'activity' => 'nullable|in:'.implode(',', TrackingManageService::ACTIVITY)]);
        // Writes are refused (409) once a programme is closed or archived, so the page offers none.
        $open = in_array($initiative->status, ['Planning', 'Active'], true);
        $can = ['edit' => $open && $this->authService->allows($initiative, ['HEAD', 'OFFICER']),
            'head' => $open && $this->authService->allows($initiative, ['HEAD']),
            'retro' => $this->authService->allows($initiative, ['HEAD', 'OFFICER', 'SUPPORT']),
            'settings' => $this->authService->allows($initiative, ['HEAD'])];

        return view('govtracking::initiatives.workspace', $manage->workspace($initiative, array_filter($filters, fn ($v) => $v !== null), $can) + ['can' => $can]);
    }

    public function show(Initiative $initiative, Request $request)
    {
        $filters = $request->validate(['status' => 'nullable|in:all,ACTIVE,DRAFT',
            'plan' => 'nullable|in:all,1_BLANKET,2_CATEGORY,3_MATRIX', 'q' => 'nullable|string|max:150']);
        $monitor = app(TrackingMonitorService::class)->programme($initiative, $filters);

        return view('govtracking::initiatives.monitor', compact('initiative', 'monitor'));
    }

    public function report(Initiative $initiative, ProgrammeReportService $reports)
    {
        $report = $reports->programme($initiative);

        return view('govtracking::initiatives.report', compact('initiative', 'report'));
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
