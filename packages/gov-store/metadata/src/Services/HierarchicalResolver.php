<?php

namespace GovStore\Metadata\Services;

use App\Models\AssetModel;
use GovStore\Metadata\Registry\MetadataRegistry;
use GovStore\Metadata\Support\LogicalSchema;
use GovStore\TenantScope\Contexts\TenantContext;

class HierarchicalResolver
{
    protected MetadataRegistry $registry;

    public function __construct(MetadataRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Evaluates active providers and returns a compiled Logical Schema matching target contexts.
     */
    public function resolve(AssetModel $assetModel): LogicalSchema
    {
        $schema = new LogicalSchema();

        // 1. Core Model & Category properties
        $context = [
            'model_id'      => $assetModel->id,
            'category_id'   => $assetModel->category_id,
            'category_name' => $assetModel->category ? $assetModel->category->name : null,
            'company_id'    => null,
            'company_name'  => null,
            'location_id'   => null,
        ];

        // 2. Safely resolve Tenant-Scope if Context is bound
        if (app()->bound(TenantContext::class)) {
            $tenantContext = app(TenantContext::class);
            if ($tenantContext->isActive) {
                $context['company_id']  = $tenantContext->companyId;
                $context['location_id'] = $tenantContext->locationId;

                // Lookup active Company name
                if ($assetModel->category && $assetModel->category->company) {
                    $context['company_name'] = $assetModel->category->company->name;
                }
            }
        }

        // 3. Stack fields of all matching providers
        foreach ($this->registry->getProviders() as $provider) {
            if ($provider->supports($context)) {
                $schema->addFields($provider->getFields());
            }
        }

        return $schema;
    }
}