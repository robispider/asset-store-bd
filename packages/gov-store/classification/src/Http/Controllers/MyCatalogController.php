<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Classification\Services\MyCatalogService;
use GovStore\Classification\Services\CategoryAdoptionService;
use GovStore\TenantScope\Contexts\TenantContext;

class MyCatalogController extends Controller
{
    protected MyCatalogService $service;

    public function __construct(MyCatalogService $service)
    {
        $this->service = $service;
    }

    /**
     * Helper to resolve the active operational scope.
     * Prioritizes Company. If null, falls back to standalone Location.
     */
    private function resolveScope(TenantContext $context): array
    {
        if ($context->companyId > 0) {
            return ['type' => 'company', 'id' => $context->companyId];
        }
        if ($context->locationId > 0) {
            return ['type' => 'location', 'id' => $context->locationId];
        }
        abort(403, 'No active operational context found.');
    }

    /**
     * Contextual Security Shield
     */
    private function checkAccess(TenantContext $tenantContext): string
    {
        $user = auth()->user();
        if (!$user) abort(401);

        // 1. Superadmin / Global Bypass
        if ($user->isSuperUser() || $user->hasAccess('admin')) {
            if (!$tenantContext->locationId) {
                redirect()->route('gov.catalog.governance.index')->send();
                exit;
            }
            return 'admin';
        }

        // 2. Staff Context Verification
        if (!$tenantContext->locationId) {
            abort(403, 'No active operational context found for your session. Please choose an office from the top bar.');
        }

        // 3. Verify local role-responsibility
        $hasRole = \GovStore\OfficeMembership\Models\OfficeResponsibility::where('user_id', $user->id)
            ->where('location_id', $tenantContext->locationId)
            ->whereIn('role_slug', ['storekeeper', 'office_admin', 'ict_officer'])
            ->exists();

        return $hasRole ? 'admin' : 'employee';
    }

    /**
     * Dashboard listing all items adopted by the active context
     */
    public function index(TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        $scope = $this->resolveScope($tenantContext);

        $categories = $this->service->getLocalGrid($scope['type'], $scope['id'], 50);
        
        $isReadOnly = ($accessMode === 'employee');
        $scopeNoun = ($scope['type'] === 'company') ? 'organization' : 'office location';

        return view('gov-classification::my-catalog.index', compact('categories', 'isReadOnly', 'scopeNoun'));
    }

    public function show($id, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        if ($accessMode === 'employee') abort(403);

        $scope = $this->resolveScope($tenantContext);

        $details = $this->service->getLocalDetails($id, $scope['type'], $scope['id'], $tenantContext->locationId);
        if (!$details) abort(404, 'Category not found in your operational catalog.');

        return view('gov-classification::my-catalog.show', $details);
    }

    public function archive(Request $request, CategoryAdoptionService $adoptionService, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        if ($accessMode === 'employee') abort(403);
        $request->validate(['category_id' => 'required|integer']);

        $scope = $this->resolveScope($tenantContext);

        try {
            $adoptionService->archiveCategory($request->category_id, $scope['type'], $scope['id']);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function restore(Request $request, CategoryAdoptionService $adoptionService, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        if ($accessMode === 'employee') abort(403);
        $request->validate(['category_id' => 'required|integer']);

        $scope = $this->resolveScope($tenantContext);

        try {
            $adoptionService->restoreCategory($request->category_id, $scope['type'], $scope['id']);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}