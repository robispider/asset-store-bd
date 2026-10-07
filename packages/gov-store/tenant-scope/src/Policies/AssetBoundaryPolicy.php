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
        if (! $context->canUseInventoryOffice()) {
            return false;
        }
        $schema = app(\GovStore\TenantScope\Services\SchemaKnowledge::class);
        foreach (['company_id' => $context->companyId, 'location_id' => $context->locationId] as $column => $id) {
            if (! $schema->hasColumn($model, $column)) {
                continue;
            }
            // Check persisted ownership as well as submitted values: rehoming a
            // foreign row into the current office is still a foreign mutation.
            if ((int) $model->{$column} !== $id || ($model->exists && (int) $model->getRawOriginal($column) !== $id)) {
                return false;
            }
        }
        return true;
    }
}
