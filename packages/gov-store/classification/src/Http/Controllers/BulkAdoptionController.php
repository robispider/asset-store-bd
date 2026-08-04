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

    /**
     * Resolves the target boundary (Ministry vs Local Office).
     */
    private function resolveScope(TenantContext $context): array
    {
        $user = auth()->user();
        $isCompanyAdmin = \GovStore\Organization\Models\CompanyAdmin::where('user_id', $user->id)
            ->where('company_id', $context->companyId)
            ->exists();

        if ($isCompanyAdmin && $context->companyId > 0) {
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
            $summary = $this->service->preview($request->codes, $scope['type'], $scope['id']);
            
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
        $request->validate(['codes' => 'required|array|min:1']);

        try {
            $scope = $this->resolveScope($tenantContext);
            $result = $this->service->execute($request->codes, $scope['type'], $scope['id'], auth()->id());
            
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}