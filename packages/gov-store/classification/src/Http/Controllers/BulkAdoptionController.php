<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Classification\Services\BulkAdoptionService;
use GovStore\TenantScope\Contexts\TenantContext;
use Exception;

class BulkAdoptionController extends Controller
{
    protected BulkAdoptionService $service;

    public function __construct(BulkAdoptionService $service)
    {
        $this->service = $service;
    }

    private function resolveScope(TenantContext $context): array
    {
        if ($context->isGlobal) {
            return ['type' => 'global', 'id' => null];
        }

        if ($context->isCompanyAdmin && $context->companyId > 0) {
            return ['type' => 'company', 'id' => $context->companyId];
        }

        if ($context->locationId > 0) {
            return ['type' => 'location', 'id' => $context->locationId];
        }

        throw new Exception(__('classification::texts.ctrl_exception_no_operational_context'));
    }

    public function preview(Request $request, TenantContext $tenantContext)
    {
        $request->validate(['codes' => 'required|array|min:1']);

        try {
            $scope = $this->resolveScope($tenantContext);
            $summary = $this->service->preview(
                $request->codes, 
                $scope['type'], 
                $scope['id'],
                $tenantContext->companyId,
                $tenantContext->locationId
            );
            
            return response()->json([
                'success' => true,
                'summary' => $summary,
                'target_scope' => ucfirst($scope['type'])
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function execute(Request $request, TenantContext $tenantContext)
    {
        // Validate structured item inputs (code + category type)
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.code' => 'required|string',
            'items.*.category_type' => 'required|string|in:asset,consumable,accessory,component,license',
        ]);

        try {
            $scope = $this->resolveScope($tenantContext);
            $result = $this->service->execute(
                $request->items, 
                $scope['type'], 
                $scope['id'], 
                auth()->id()
            );
            
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}