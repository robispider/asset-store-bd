<?php

namespace GovStore\Classification\Http\Controllers;

use App\Models\Location;
use GovStore\Classification\Services\CategoryAdoptionService;
use GovStore\Classification\Services\MyCatalogService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ActionFailure;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MyCatalogController extends Controller
{
    protected MyCatalogService $service;

    public function __construct(MyCatalogService $service)
    {
        $this->service = $service;
    }

    private function resolveScope(TenantContext $context): array
    {
        if ($context->isCompanyAdmin && $context->companyId > 0) {
            return ['type' => 'company', 'id' => $context->companyId];
        }
        if ($context->locationId > 0) {
            return ['type' => 'location', 'id' => $context->locationId];
        }
        abort(403, __('classification::texts.ctrl_exception_no_active_context'));
    }

    private function checkAccess(TenantContext $tenantContext): string
    {
        abort_unless(app(GovAccess::class)->decide(auth()->user(), 'catalog.office.adopt')->allowed, 403);

        return 'admin';
    }

    public function index(Request $request, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);

        $companyId = $tenantContext->companyId ?? 0;
        $locationId = $tenantContext->locationId ?? 0;

        $activeTab = in_array($request->input('tab'), ['active', 'cleanup', 'archived'], true) ? $request->input('tab') : 'active';
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'type' => in_array($request->input('type'), MyCatalogService::TYPES, true) ? $request->input('type') : null,
        ];

        $metrics = $this->service->getLocalOverview($companyId, $locationId);

        if ($metrics['total_active'] === 0 && $metrics['archived_count'] === 0) {
            return view('gov-classification::discover.quickstart');
        }

        $categories = $this->service->getLocalGrid($companyId, $locationId, $activeTab, 50, $filters);
        $families = MyCatalogService::families($categories);

        // Type chips count the current tab after the search, before the type filter.
        $tabRows = match ($activeTab) {
            'cleanup' => $metrics['active']->where('total_usage_count', 0),
            'archived' => $metrics['archived'],
            default => $metrics['active'],
        };
        if ($filters['q'] !== '') {
            $tabRows = $tabRows->filter(fn ($c) => mb_stripos($c->name, $filters['q']) !== false);
        }
        $typeCounts = $tabRows->countBy('category_type')->only(MyCatalogService::TYPES)->all();

        $isReadOnly = ($accessMode === 'employee');
        $canMap = app(GovAccess::class)->permitsRequest(auth()->user(), 'catalog.master.manage');
        $office = $locationId > 0 ? Location::withoutGlobalScopes()->whereKey($locationId)->value('name') : null;
        $office = $office ? MyCatalogService::label($office)['family'] : null;

        return view('gov-classification::my-catalog.index', compact('categories', 'families', 'isReadOnly', 'canMap', 'activeTab', 'metrics', 'filters', 'typeCounts', 'office'));
    }

    public function show($id, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        if ($accessMode === 'employee') {
            abort(403);
        }

        $scope = $this->resolveScope($tenantContext);

        // Fixed: Pass the integers to match the service definition
        $details = $this->service->getLocalDetails($id, $scope['type'], $scope['id'], $tenantContext->locationId);
        if (! $details) {
            abort(404, __('classification::texts.ctrl_exception_category_not_found'));
        }

        return view('gov-classification::my-catalog.show', $details);
    }

    public function archive(Request $request, CategoryAdoptionService $adoptionService, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        if ($accessMode === 'employee') {
            abort(403);
        }
        $request->validate(['category_id' => 'required|integer']);

        $scope = $this->resolveScope($tenantContext);

        try {
            $adoptionService->archiveCategory($request->category_id, $scope['type'], $scope['id']);

            return response()->json(['success' => true]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => app(ActionFailure::class)->message($e)], 500);
        }
    }

    public function restore(Request $request, CategoryAdoptionService $adoptionService, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        if ($accessMode === 'employee') {
            abort(403);
        }
        $request->validate(['category_id' => 'required|integer']);

        $scope = $this->resolveScope($tenantContext);

        try {
            $adoptionService->restoreCategory($request->category_id, $scope['type'], $scope['id']);

            return response()->json(['success' => true]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => app(ActionFailure::class)->message($e)], 500);
        }
    }
}
