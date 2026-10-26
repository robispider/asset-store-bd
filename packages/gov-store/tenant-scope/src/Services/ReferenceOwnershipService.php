<?php

namespace GovStore\TenantScope\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ownership is explicit and independent of adoption/visibility. */
class ReferenceOwnershipService
{
    public function owner(Model $model): array
    {
        $type = ReferenceTypes::key($model);
        if ($type === 'category' && Schema::hasTable('gov_category_governance')
            && DB::table('gov_category_governance')->where('category_id', $model->getKey())->whereIn('governance_type', ['global', 'national'])->exists()) {
            return ['national', null];
        }
        if (Schema::hasTable('gov_reference_owners')) {
            $row = DB::table('gov_reference_owners')->where('reference_type', $type)->where('reference_id', $model->getKey())->first();
            if ($row) {
                return [$row->owner_type, $row->owner_id === null ? null : (int) $row->owner_id];
            }
        }
        if ($type === 'category' && Schema::hasTable('gov_category_governance')) {
            $row = DB::table('gov_category_governance')->where('category_id', $model->getKey())->first();
            if ($row) {
                return match ($row->governance_type) {
                    'global', 'national' => ['national', null],
                    'company' => $row->created_by_company_id ? ['company', (int) $row->created_by_company_id] : ['unresolved', null],
                    default => ['unresolved', null],
                };
            }
        }

        if ($type === 'category' && Schema::hasTable('gov_tenant_scope_mappings')
            && DB::table('gov_tenant_scope_mappings')->where('reference_type', $type)->where('reference_id', $model->getKey())->exists()) {
            return ['unresolved', null];
        }

        return ['national', null];
    }

    public function getOwnershipState(Model $model): string
    {
        return match ($this->owner($model)[0]) { 'national' => 'GLOBAL', 'office' => 'LOCATION', 'company' => 'COMPANY', default => 'UNRESOLVED' };
    }

    public function getOwnerId(Model $model): ?int
    {
        return $this->owner($model)[1];
    }

    public function assign(Model $model, string $scope, ?int $id, ?int $actor, string $reason): void
    {
        abort_unless(in_array($scope, ['national', 'company', 'office'], true), 422);
        if ($scope !== 'national') {
            ($scope === 'company' ? \App\Models\Company::class : \App\Models\Location::class)::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
        }
        DB::transaction(function () use ($model, $scope, $id, $actor, $reason) {
            $key = ['reference_type' => ReferenceTypes::key($model), 'reference_id' => $model->getKey()];
            $exists = DB::table('gov_reference_owners')->where($key)->lockForUpdate()->first();
            DB::table('gov_reference_owners')->updateOrInsert($key,
                ['owner_type' => $scope, 'owner_id' => $scope === 'national' ? null : $id,
                    'reviewed_by' => $actor, 'review_reason' => $reason, 'created_at' => $exists?->created_at ?? now(), 'updated_at' => now()]);
            if ($model instanceof \App\Models\Category) {
                $query = DB::table('gov_category_governance')->where('category_id', $model->id);
                if ($query->exists()) {
                    // Ownership transfers never rewrite original creator attribution.
                    $query->update(['governance_type' => $scope, 'updated_at' => now()]);
                } else {
                    $query->insert(['category_id' => $model->id, 'governance_type' => $scope,
                        'created_by_company_id' => $scope === 'company' ? $id : null,
                        'created_by_user_id' => $actor, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }
}
