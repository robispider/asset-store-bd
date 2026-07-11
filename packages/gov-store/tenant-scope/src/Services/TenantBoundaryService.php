<?php

namespace GovStore\TenantScope\Services;

use Illuminate\Database\Eloquent\Model;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Exceptions\TenantBoundaryException;
use GovStore\TenantScope\Policies\AssetBoundaryPolicy;
use GovStore\TenantScope\Policies\CategoryBoundaryPolicy;
use GovStore\TenantScope\Validators\BusinessRuleValidator;
use GovStore\TenantScope\Validators\ResponsibilityRegistry;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use Illuminate\Support\Facades\Log;

class TenantBoundaryService
{
    public function verify(Model $model, string $action): void
    {
        if (!app()->bound(TenantContext::class)) {
            return;
        }
        
        $context = app(TenantContext::class);

        // Bypass verification if context is inactive OR Global
        if (!$context->isActive || $context->isGlobal) {
            return; 
        }

        // 1. Enforce Primary Ownership Verification (Core Scoping)
        $policy = $this->resolvePolicy($model);
        if ($policy) {
            
            // Force/inject correct ownership values if creating a new transactional record
            if ($action === 'create' && isset($policy->tenantColumns)) {
                if (in_array('company_id', $policy->tenantColumns)) $model->company_id = $context->companyId;
                if (in_array('location_id', $policy->tenantColumns)) $model->location_id = $context->locationId;
            }

            // Verify modification rights
            if ($action !== 'create' && !$policy->canMutate($model, $context)) {
                $this->logViolation($model, $action);
                throw new TenantBoundaryException(
                    "Access Denied: Your assigned office does not hold ownership rights to modify this " . class_basename($model) . ".",
                    'OWNERSHIP'
                );
            }

            // Enforce Relationship Integrity validation
            if (in_array($action, ['create', 'update'])) {
                $this->validateRelationshipIntegrity($model, $policy);
            }
        }

        // 2. Enforce Operational Responsibilities (e.g., Storekeeper checkouts)
        if (get_class($model) === \App\Models\Asset::class) {
            $this->verifyAssetMutation($model, $action, $context);
        }

        // 3. Enforce Deletion and Data Integrity business validations
        app(BusinessRuleValidator::class)->validate($model, $action);
    }

    protected function verifyAssetMutation(Model $asset, string $action, TenantContext $context): void
    {
        if ((int)$asset->location_id !== (int)$context->locationId) {
            throw new TenantBoundaryException(
                "Access Denied: The target item belongs to another office context.",
                'OUT_OF_BOUNDS',
                403
            );
        }

        if ($action === 'update' && $asset->isDirty(['assigned_to', 'status_id', 'location_id'])) {
            
            $user = auth()->user();
            if (!$user) {
                return;
            }

            $responsibility = OfficeResponsibility::where('location_id', $context->locationId)
                ->where('user_id', $user->id)
                ->first();

            $canCheckout = $responsibility && ResponsibilityRegistry::can($responsibility->role_slug, 'checkout_assets');

            if (!$canCheckout) {
                $this->logViolation($asset, 'checkout');
                throw new TenantBoundaryException(
                    "Security Violation: You do not hold active storekeeper responsibility inside this office context to execute checkouts.",
                    'ROLE_VIOLATION',
                    403
                );
            }
        }
    }

   /**
     * Verifies relationship allocations across tenant boundaries.
     */
    protected function validateRelationshipIntegrity(Model $model, $policy): void
    {
        if (!isset($policy->relationMap)) {
            return;
        }

        foreach ($policy->relationMap as $column => $relatedModelClass) {
            if (!empty($model->{$column})) {
                
                // 1. Query without scopes first to check absolute physical existence (Resolves 404)
                $rawItem = $relatedModelClass::withoutGlobalScopes()->find($model->{$column});
                if (!$rawItem) {
                    throw new TenantBoundaryException(
                        "Setup Error: The selected " . str_replace('_id', '', $column) . " (ID: {$model->{$column}}) does not exist in the database.",
                        'NOT_FOUND',
                        404
                    );
                }

                // 2. Query WITH scopes to check Contextual Visibility (Resolves 403)
                // If TenantScope/UserScope allows this user to see the item, they are allowed to assign it.
                $isVisible = $relatedModelClass::find($model->{$column}) !== null;

                if (!$isVisible) {
                    throw new TenantBoundaryException(
                        "Security Violation: You are not authorized to assign " . class_basename($rawItem) . " '{$rawItem->name}' to this resource. It lies outside your active data boundary.",
                        'RELATIONSHIP',
                        403
                    );
                }
            }
        }
    }

    /**
     * Resolves the boundary policy matching the model class.
     */
    private function resolvePolicy(Model $model)
    {
        $className = get_class($model);

        // =========================================================================
        // REMOVED \App\Models\User::class from transactional policy resolution
        // =========================================================================
        $transactionalModels = [
            \App\Models\Asset::class,
            \App\Models\Consumable::class,
            \App\Models\Accessory::class,
            \App\Models\Component::class,
            \App\Models\License::class,
        ];

        $referenceModels = [
            \App\Models\Category::class,
            \App\Models\AssetModel::class,
            \App\Models\Supplier::class,
            \App\Models\Manufacturer::class,
            \App\Models\Location::class,
        ];

        if (in_array($className, $transactionalModels, true)) {
            return app(AssetBoundaryPolicy::class);
        }

        if (in_array($className, $referenceModels, true)) {
            return app(CategoryBoundaryPolicy::class);
        }

        return null;
    }

    private function logViolation(Model $model, string $action)
    {
        Log::warning("SECURITY VIOLATION: Cross-Tenant mutation blocked.", [
            'user_id' => auth()->id(),
            'action'  => $action,
            'model'   => class_basename($model),
            'id'      => $model->getKey()
        ]);
    }
}