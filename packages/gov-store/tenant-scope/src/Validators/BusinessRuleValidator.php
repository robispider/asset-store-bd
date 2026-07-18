<?php

namespace GovStore\TenantScope\Validators;

use Illuminate\Database\Eloquent\Model;
use GovStore\TenantScope\Exceptions\TenantBoundaryException;

class BusinessRuleValidator
{
    /**
     * Enforces system safety rules before letting a write/delete proceed.
     */
    public function validate(Model $model, string $action): void
    {
        if ($action === 'delete') {
            $this->enforceDeletionIntegrity($model);
        }
    }

    /**
     * Prevents deletion of core items if they are currently assigned to active assets.
     */
    protected function enforceDeletionIntegrity(Model $model)
    {
        $className = get_class($model);

        $protectedModels = [
            \App\Models\Category::class,
            \App\Models\AssetModel::class,
            \App\Models\Supplier::class,
            \App\Models\Manufacturer::class,
            \App\Models\Location::class,
        ];

        if (in_array($className, $protectedModels)) {
            // Check if the model defines an 'assets' relationship and assets exist
            if (method_exists($model, 'assets') && $model->assets()->exists()) {
                throw new TenantBoundaryException(
                    __('tenantops::ops.exception_deletion_guard', ['model' => class_basename($model)]),
                    'BUSINESS_RULE'
                );
            }
        }
    }
}