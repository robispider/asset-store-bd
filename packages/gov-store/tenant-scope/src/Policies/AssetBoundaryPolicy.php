<?php

namespace GovStore\TenantScope\Policies;

use Illuminate\Database\Eloquent\Model;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Exceptions\TenantBoundaryException;

class AssetBoundaryPolicy
{
    /**
     * Static schema declarations to avoid expensive DB column checks.
     */
    public array $tenantColumns = ['company_id', 'location_id'];

    /**
     * Declarative relationship validations.
     * Maps the model's foreign keys to their core class models.
     */
    public array $relationMap = [
        'category_id'     => \App\Models\Category::class,
        'model_id'        => \App\Models\AssetModel::class,
        'supplier_id'     => \App\Models\Supplier::class,
        'manufacturer_id' => \App\Models\Manufacturer::class,
    ];

    public function canMutate(Model $model, TenantContext $context): bool
    {
        // 1. Check Company Ownership
        if ($model->company_id && $model->company_id !== $context->companyId) {
            return false;
        }

        // 2. Check Location Ownership
        if ($model->location_id && $model->location_id !== $context->locationId) {
            return false;
        }

        return true;
    }
}