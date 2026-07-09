<?php

namespace GovStore\TenantScope\Services;

use Illuminate\Database\Eloquent\Model;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Exceptions\TenantBoundaryException;
use GovStore\TenantScope\Policies\AssetBoundaryPolicy;
use GovStore\TenantScope\Policies\CategoryBoundaryPolicy;
use GovStore\TenantScope\Validators\BusinessRuleValidator;
use Illuminate\Support\Facades\Log;

class TenantBoundaryService
{
    /**
     * When true, all boundary enforcement is skipped. Wrap trusted server-side flows
     * (e.g. workflow-driven checkout/fulfillment) via runWithoutBoundaries().
     */
    public static bool $bypass = false;

    /**
     * Runs a callback with boundary enforcement temporarily disabled, restoring the
     * previous state afterwards (nesting-safe).
     */
    public static function runWithoutBoundaries(callable $callback)
    {
        $previous = static::$bypass;
        static::$bypass = true;
        try {
            return $callback();
        } finally {
            static::$bypass = $previous;
        }
    }

    /**
     * Validates both ownership, relationship mappings, and business integrity rules.
     */
    public function verify(Model $model, string $action): void
    {
        // Never enforce during CLI (imports, seeders, migrations, queue workers, tinker)
        // or when a trusted flow has explicitly bypassed enforcement.
        if (static::$bypass || app()->runningInConsole()) return;

        if (!app()->bound(TenantContext::class)) return;
        $context = app(TenantContext::class);
        if (!$context->isActive) return; // Superadmins are bypassed

        // 1. Enforce Primary Ownership Verification
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

            // 2. Enforce Relationship Integrity validation
            if (in_array($action, ['create', 'update'])) {
                $this->validateRelationshipIntegrity($model, $policy);
            }
        }

        // 3. Enforce Business Rule validation
        app(BusinessRuleValidator::class)->validate($model, $action);
    }

    protected function validateRelationshipIntegrity(Model $model, $policy): void
    {
        if (!isset($policy->relationMap)) {
            return;
        }

        foreach ($policy->relationMap as $column => $relatedModelClass) {
            if (isset($model->{$column})) {
                
                // Query without scopes first to check physical existence (Resolves 404)
                $rawItem = $relatedModelClass::withoutGlobalScopes()->find($model->{$column});
                if (!$rawItem) {
                    throw new TenantBoundaryException(
                        "Setup Error: The selected " . str_replace('_id', '', $column) . " (ID: {$model->{$column}}) does not exist in the database.",
                        'NOT_FOUND',
                        404
                    );
                }

                // Verify that the retrieved reference belongs to the user's scope (Resolves 403)
                $policyForRelated = $this->resolvePolicy($rawItem);
                if ($policyForRelated) {
                    $context = app(TenantContext::class);
                    if (!$policyForRelated->canMutate($rawItem, $context)) {
                        throw new TenantBoundaryException(
                            "Security Violation: You are not authorized to assign " . class_basename($rawItem) . " '{$rawItem->name}' to this resource.",
                            'RELATIONSHIP',
                            403
                        );
                    }
                }
            }
        }
    }

    private function resolvePolicy(Model $model)
    {
        $className = get_class($model);

        $transactionalModels = [
            \App\Models\Asset::class,
            \App\Models\User::class,
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

        if (in_array($className, $transactionalModels)) {
            return app(AssetBoundaryPolicy::class);
        }

        if (in_array($className, $referenceModels)) {
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