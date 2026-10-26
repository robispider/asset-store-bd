<?php

namespace GovStore\Classification\Services;

use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ReferenceOwnershipService;
use Illuminate\Support\Facades\DB;

class CatalogSourceEligibility
{
    public function category(int $id): Category
    {
        $category = Category::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
        $context = app(TenantContext::class);
        if (! $context->isActive || $context->isGlobal) {
            return $category;
        }
        [$type, $owner] = app(ReferenceOwnershipService::class)->owner($category);
        $eligible = match ($type) {
            'national' => true,
            'company' => $owner === $context->companyId,
            'office' => $context->companyId && Location::withoutGlobalScopes()->whereNull('deleted_at')
                ->whereKey($owner)->where('company_id', $context->companyId)->exists(),
            default => DB::table('gov_tenant_scope_mappings')->where('reference_type', 'category')
                ->where('reference_id', $id)->where(fn ($q) => $q->where(fn ($q) => $q->where('scope_type', 'company')->where('scope_id', $context->companyId))
                ->orWhere(fn ($q) => $q->where('scope_type', 'location')->where('scope_id', $context->locationId)))->exists(),
        };
        abort_unless($eligible, 404);

        return $category;
    }

    public function target(string $type, int $id): void
    {
        abort_unless(in_array($type, ['company', 'location'], true), 422);
        $target = ($type === 'company' ? Company::class : Location::class)::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
        $context = app(TenantContext::class);
        if (! $context->isActive || $context->isGlobal) {
            return;
        }
        abort_unless($type === 'company'
            ? $context->isCompanyAdmin && $context->companyId === $id
            : $context->locationId === $id && (int) $target->company_id === $context->companyId, 404);
    }
}
