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
     * Resolves the target boundary using pre-computed TenantContext singleton states.
     */
    private function resolveScope(TenantContext $context): array
    {
        // 1. Global Superadmin Bypass
        if ($context->isGlobal) {
            return ['type' => 'global', 'id' => null];
        }

        // 2. Company Admin (Ministry-wide scope)
        if ($context->isCompanyAdmin && $context->companyId > 0) {
            return ['type' => 'company', 'id' => $context->companyId];
        }

        // 3. Local Office Staff (Location-wide scope)
        if ($context->locationId > 0) {
            return ['type' => 'location', 'id' => $context->locationId];
        }

        throw new Exception(__('classification::texts.ctrl_exception_no_operational_context'));
    }

    public function adopt(Request $request, CategoryAdoptionService $adoptionService, TenantContext $tenantContext)
    {
        $request->validate(['category_id' => 'required|integer']);

        try {
            $scope = $this->resolveScope($tenantContext);
            
            // Cannot manually adopt if already globally shared
            $adoptionService->useCategory($request->category_id, $scope['type'], $scope['id']);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function abandon(Request $request, CategoryAdoptionService $adoptionService, TenantContext $tenantContext)
    {
        $request->validate(['category_id' => 'required|integer']);

        try {
            $scope = $this->resolveScope($tenantContext);
            $adoptionService->stopUsingCategory($request->category_id, $scope['type'], $scope['id']);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422); 
        }
    }

    public function provision(Request $request, CatalogCategoryCreator $creator, TenantContext $tenantContext)
    {
        $request->validate([
            'unspsc_code'      => 'required|string',
            'category_type'    => 'required|string|in:asset,consumable,accessory,license,component',
            'custom_name'      => 'nullable|string|max:255',
            'governance_type'  => 'nullable|string|in:global,company,location',
            'target_company_id'=> 'nullable|integer'
        ]);

        $user = auth()->user();

        try {
            $scope = $this->resolveScope($tenantContext);
            $governanceType = $scope['type']; 
            $targetScopeType = $scope['type'];
            $targetScopeId = $scope['id'];

            // Superadmins can manually override standard scopes via payload inputs
            if ($tenantContext->isGlobal) {
                $governanceType = $request->input('governance_type', 'global');
                if ($governanceType === 'company') {
                    $targetScopeType = 'company';
                    $targetScopeId = $request->input('target_company_id');
                    if (empty($targetScopeId)) {
                        throw new Exception(__('classification::texts.ctrl_exception_select_target_company'));
                    }
                } elseif ($governanceType === 'global') {
                    $targetScopeId = null;
                }
            }

            $creator->provisionAndMap(
                $request->unspsc_code,
                $request->category_type,
                $governanceType,
                $targetScopeType,
                $targetScopeId,
                $user->id,
                $request->custom_name
            );

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}