<?php

namespace GovStore\Tracking\Services;

use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class TrackingOfficeAccess
{
    public function __construct(private TenantContext $context, private TrackingAuthorizationService $authorization) {}

    public function programmes(): Builder
    {
        // InitiativeScope uses current operation-unit and owner-company assignments, including MONITOR.
        return Initiative::query()->when(! auth()->check(), fn ($query) => $query->whereRaw('1 = 0'));
    }

    public function programme(Initiative $initiative): void
    {
        abort_unless($this->authorization->allows($initiative), 404);
    }

    public function officeKey(int $id): bool
    {
        if (! auth()->check() || ! $this->context->isActive) {
            return false;
        }
        if (auth()->user()->isSuperUser()) {
            return true;
        }

        // Null is not a wildcard for an ordinary actor with an uninitialized context.
        return in_array($id, $this->context->allowedLocationIds ?? [$this->context->locationId], true);
    }

    public function inventoryKey(int $id, ?int $companyId): bool
    {
        if (! auth()->check() || ! $this->context->isActive) {
            return false;
        }
        if (auth()->user()->isSuperUser()) {
            return true;
        }

        return in_array($id, $this->context->allowedInventoryLocationIds ?? [], true)
            && in_array($companyId, $this->context->allowedInventoryCompanyIds ?? [], true);
    }

    public function connected(Initiative $initiative, int $locationId, ?TrackingCode $code = null): bool
    {
        $ids = TrackingCode::where('initiative_id', $initiative->id)->when($code, fn ($q) => $q->whereKey($code->id))->pluck('id');

        return DB::table('gov_tracking_associations')->whereIn('tracking_code_id', $ids)->where('location_id', $locationId)->where('status', 'ACTIVE')->exists()
            || DB::table('gov_tracking_allocations')->join('gov_tracking_targets', 'gov_tracking_targets.id', '=', 'gov_tracking_allocations.target_id')
                ->whereIn('gov_tracking_targets.tracking_code_id', $ids)->where('gov_tracking_allocations.location_id', $locationId)->exists();
    }

    public function office(Initiative $initiative, int $locationId, ?TrackingCode $code = null): void
    {
        $this->programme($initiative);
        abort_if($code && (int) $code->initiative_id !== (int) $initiative->id, 404);
        abort_unless($this->connected($initiative, $locationId, $code), 404);
    }

    public function directoryIds(): ?array
    {
        if (! auth()->check() || ! $this->context->isActive) {
            return [];
        }
        if (auth()->user()?->isSuperUser() && $this->context->isActive) {
            return null;
        }

        return array_values(array_filter($this->context->allowedLocationIds ?? [$this->context->locationId]));
    }
}
