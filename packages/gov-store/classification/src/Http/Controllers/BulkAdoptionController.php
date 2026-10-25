<?php

namespace GovStore\Classification\Http\Controllers;

use Exception;
use GovStore\Classification\Services\BulkAdoptionService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ActionFailure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

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
        $request->validate([
            'codes' => 'required|array|min:1',
            'codes.*' => ['required', 'string', 'distinct', 'exists:gov_catalog_nodes,code'],
            'collection_id' => ['nullable', 'integer', Rule::exists('gov_catalog_collections', 'id')->where('is_active', true)],
        ]);
        if ($request->filled('collection_id')) {
            $request->validate(['codes.*' => [Rule::exists('gov_catalog_collection_nodes', 'code')
                ->where('collection_id', $request->integer('collection_id'))]]);
        }

        try {
            $scope = $this->resolveScope($tenantContext);
            $summary = $this->service->preview(
                $request->codes,
                $scope['type'],
                $scope['id'],
                $tenantContext->companyId,
                $tenantContext->locationId,
                $request->filled('collection_id') ? $request->integer('collection_id') : null
            );

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'target_scope' => __('classification::collections.scopes.'.$scope['type']),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => app(ActionFailure::class)->message($e)], 403);
        }
    }

    public function execute(Request $request, TenantContext $tenantContext)
    {
        // Validate structured item inputs (code + category type)
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.code' => 'required|string|distinct|exists:gov_catalog_nodes,code',
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
            return response()->json(['success' => false, 'message' => app(ActionFailure::class)->message($e)], 500);
        }
    }
}
