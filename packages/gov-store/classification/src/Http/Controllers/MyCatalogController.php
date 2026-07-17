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
     * Contextual Security Shield
     */
    private function checkAccess(TenantContext $tenantContext): string
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        // 1. Superadmin / Global Bypass
        if ($user->isSuperUser() || $user->hasAccess('admin')) {
            if (!$tenantContext->locationId) {
                // If in Global Overview, redirect cleanly to the Governance Dashboard
                redirect()->route('gov.catalog.governance.index')->send();
                exit;
            }
            return 'admin';
        }

        // 2. Regular Operational Staff Context Verification (Require locationId only)
        if (!$tenantContext->locationId) {
            abort(403, 'No active operational context found for your session. Please choose an office from the top bar.');
        }

        // Verify local role-responsibility in the active location
        $hasRole = \GovStore\OfficeMembership\Models\OfficeResponsibility::where('user_id', $user->id)
            ->where('location_id', $tenantContext->locationId)
            ->whereIn('role_slug', ['storekeeper', 'office_admin', 'ict_officer'])
            ->exists();

        return $hasRole ? 'admin' : 'employee';
    }

    /**
     * Dashboard listing all items in the storekeeper's warehouse (Strictly Location Scoped)
     */
    public function index(TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        
        // STRICT RULE: Resolve company ID purely from the active working location context (NO User fallbacks)
        $companyId = $tenantContext->companyId ?? 0;

        if ($companyId === 0) {
            // Standalone Office: Browse only the globally shared standard categories
            $categories = $this->service->getGlobalStandardsGrid(50);
            return view('gov-classification::my-catalog.unassigned', compact('categories'));
        }

        $categories = $this->service->getLocalGrid($companyId, 50);
        $isReadOnly = ($accessMode === 'employee');

        return view('gov-classification::my-catalog.index', compact('categories', 'isReadOnly'));
    }

    /**
     * Manage individual category lifecycle states (Gated strictly to Admins)
     */
    public function show($id, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        
        if ($accessMode === 'employee') {
            abort(403, 'Unauthorized access. Standard employees cannot manage category lifecycle states.');
        }
        
        $companyId = $tenantContext->companyId ?? 0;

        if ($companyId === 0) {
            abort(403, 'You cannot manage category states in local-only mode. Your office must be assigned to a parent Ministry.');
        }

        $details = $this->service->getLocalDetails($id, $companyId, $tenantContext->locationId);
        if (!$details) {
            abort(404, 'Category not found in your operational catalog.');
        }

        return view('gov-classification::my-catalog.show', $details);
    }

    /**
     * Soft-Archive Action
     */
    public function archive(Request $request, CategoryAdoptionService $adoptionService, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        if ($accessMode === 'employee') abort(403);

        $request->validate(['category_id' => 'required|integer']);
        $companyId = $tenantContext->companyId ?? 0;

        try {
            $adoptionService->archiveCategory($request->category_id, $companyId);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Restore Action
     */
    public function restore(Request $request, CategoryAdoptionService $adoptionService, TenantContext $tenantContext)
    {
        $accessMode = $this->checkAccess($tenantContext);
        if ($accessMode === 'employee') abort(403);

        $request->validate(['category_id' => 'required|integer']);
        $companyId = $tenantContext->companyId ?? 0;

        try {
            $adoptionService->restoreCategory($request->category_id, $companyId);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}