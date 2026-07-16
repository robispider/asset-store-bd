<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Classification\Services\CategoryAdoptionService;
use GovStore\Classification\Services\CatalogCategoryCreator;
use GovStore\TenantScope\Contexts\TenantContext;
use Exception;

class CategoryAdoptionController extends Controller
{
    /**
     * Adopts a pre-existing mapped Snipe-IT category into the active company's catalog.
     */
    public function adopt(Request $request, CategoryAdoptionService $adoptionService, TenantContext $tenantContext)
    {
        $request->validate(['category_id' => 'required|integer']);
        
        if (!$tenantContext->companyId) {
            return response()->json(['success' => false, 'message' => 'No active operational context found for your user session.'], 403);
        }

        try {
            $adoptionService->useCategory($request->category_id, $tenantContext->companyId);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Removes an adopted category from the active company's catalog.
     * Guarded by strong multi-table Governance Rules.
     */
    public function abandon(Request $request, CategoryAdoptionService $adoptionService, TenantContext $tenantContext)
    {
        $request->validate(['category_id' => 'required|integer']);

        if (!$tenantContext->companyId) {
            return response()->json(['success' => false, 'message' => 'No active operational context found.'], 403);
        }

        try {
            $adoptionService->stopUsingCategory($request->category_id, $tenantContext->companyId);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            // 422 Unprocessable Entity denotes a Business Governance Rule violation
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422); 
        }
    }

    /**
     * Executes the 1-Click Provisioning Workflow:
     * Provisions the native Snipe-IT category, links it to UNSPSC, and auto-adopts it.
     */
    public function provision(Request $request, CatalogCategoryCreator $creator, TenantContext $tenantContext)
    {
        $request->validate([
            'unspsc_code'      => 'required|string',
            'category_type'    => 'required|string|in:asset,consumable,accessory,license,component',
            'custom_name'      => 'nullable|string|max:255',
            'governance_type'  => 'nullable|string|in:global,company',
            'target_company_id'=> 'nullable|integer'
        ]);

        $user = auth()->user();
        $isSuperAdmin = $user->isSuperUser() || $user->hasAccess('admin');

        // Security Shield: Determine Target Scope based on Role
        if ($isSuperAdmin) {
            // Super Admins control the governance explicitly from the UI payload
            $governanceType = $request->input('governance_type', 'global');
            $targetCompanyId = ($governanceType === 'company') ? $request->input('target_company_id') : null;
            
            if ($governanceType === 'company' && empty($targetCompanyId)) {
                return response()->json(['success' => false, 'message' => 'Please select a specific company to assign this category.'], 422);
            }
        } else {
            // Regular Operational Users ALWAYS create scoped "Company" categories tied to their active session
            $governanceType = 'company';
            $targetCompanyId = $tenantContext->companyId;
            
            if (!$targetCompanyId) {
                return response()->json(['success' => false, 'message' => 'No active operational context found for your session.'], 403);
            }
        }

        try {
            $category = $creator->provisionAndMap(
                $request->unspsc_code,
                $request->category_type,
                $governanceType,
                $targetCompanyId,
                $user->id,
                $request->custom_name
            );

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}