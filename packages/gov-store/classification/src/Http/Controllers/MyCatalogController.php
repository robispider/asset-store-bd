<?php

namespace GovStore\Classification\Http\Controllers;

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
        return app(GovAccess::class)
            ->permitsRequest(auth()->user(), 'catalog.office.adopt') ? 'admin' : 'employee';
    }

    public function index(Request $request, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);

        $companyId = $tenantContext->companyId ?? 0;
        $locationId = $tenantContext->locationId ?? 0;

        $activeTab = $request->input('tab', 'active');

        // Fetch Grid Data
        $categories = $this->service->getLocalGrid($companyId, $locationId, $activeTab, 50);

        if ($activeTab === 'active' && $categories->total() === 0 && $request->input('page', 1) == 1) {
            return view('gov-classification::discover.quickstart');
        }

        // Fetch Summary Metrics (Run lightweight counts to power the dashboard cards)
        $metrics = [
            'total_active' => $this->service->getLocalGrid($companyId, $locationId, 'active', 1)->total(),
            'needs_cleanup' => $this->service->getLocalGrid($companyId, $locationId, 'cleanup', 1)->total(),
            'archived' => $this->service->getLocalGrid($companyId, $locationId, 'archived', 1)->total(),
        ];

        $isReadOnly = ($accessMode === 'employee');

        return view('gov-classification::my-catalog.index', compact('categories', 'isReadOnly', 'activeTab', 'metrics'));
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
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => app(ActionFailure::class)->message($e)], 500);
        }
    }
}
