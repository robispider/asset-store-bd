<?php

namespace GovStore\Organization\Services;

use App\Models\User;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use GovStore\TenantScope\Contracts\MembershipContextResolver;
use GovStore\Organization\Models\LocationProfile;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Models\OrganizationActivityLog;

class OfficeReadinessService
{
    public function evaluateAndTransition(int $locationId, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($locationId, $actorId) {
            $location = Location::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($locationId);
            $profile = LocationProfile::where('location_id', $locationId)->lockForUpdate()->first();
            $memberships = app(MembershipContextResolver::class);
            $eligible = User::withoutGlobalScopes()->whereNull('deleted_at')->where('activated', true)
                ->where('company_id', $location->company_id)->whereIn('id', function ($query) use ($locationId) {
                    $query->select('user_id')->from('gov_office_memberships')->where('location_id', $locationId)->where('status', 'active');
                })->pluck('id')->filter(fn ($id) => $memberships->hasActiveMembershipAt((int) $id, $locationId));
        
            // STRICT PIVOT LOOKUP: Read from the multi-office matrix
            $routing = app(\GovStore\CustomRequests\Services\ApprovalRouting::class);
            $hasPrimary = (bool) $routing->candidates($locationId, 'primary_approver');
            $hasStorekeeper = (bool) $routing->candidates($locationId, 'storekeeper');
            $requiresFinal = $this->requiresFinal($locationId);
            $hasApprovalPath = ! $requiresFinal || $routing->feasible($locationId, 'PRIMARY_AND_FINAL');
        
            $usersCount = $eligible->count();

            $checklist = [
                'has_office_admin'     => $profile && $eligible->contains($profile->office_admin_id),
                'has_primary_approver' => $hasPrimary,
                'has_storekeeper'      => $hasStorekeeper,
                'has_users'            => $usersCount > 0,
                'has_approval_path'    => $hasApprovalPath,
            ];

            $isOperational = !in_array(false, $checklist, true);

            $actorId ??= auth()->id();
            if ($profile && in_array($profile->lifecycle_status, OfficeLifecycleService::ACTIVE, true) && $actorId) {
                $oldStatus = $profile->lifecycle_status;
                $newStatus = $isOperational ? 'operational' : ($checklist['has_office_admin'] ? 'configured' : 'provisioned');

                if ($oldStatus !== $newStatus) {
                    $profile->update(['lifecycle_status' => $newStatus]);

                    OrganizationActivityLog::create([
                        'location_id' => $locationId,
                        'performed_by' => $actorId,
                        'event_type' => 'status_changed',
                        'details' => [
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                        ]
                    ]);
                }
            }

            return [
                'is_operational' => $isOperational && $profile && in_array($profile->lifecycle_status, OfficeLifecycleService::ACTIVE, true),
                'checklist' => $checklist,
                'users_count' => $usersCount
            ];
        });
    }

    private function requiresFinal(int $office): bool
    {
        $schema = DB::getSchemaBuilder();
        if (! $schema->hasTable('gov_approval_policies')) {
            return false;
        }
        $targets = [];
        $categories = [];
        foreach (['consumable' => 'consumables', 'accessory' => 'accessories', 'component' => 'components', 'license' => 'licenses'] as $type => $table) {
            if ($schema->hasColumn($table, 'location_id') && $schema->hasColumn($table, 'category_id')) {
                $rows = DB::table($table)->where('location_id', $office)->whereNull('deleted_at')->get(['id', 'category_id']);
                $targets[$type] = $rows->pluck('id')->all();
                $categories = array_merge($categories, $rows->pluck('category_id')->filter()->all());
            }
        }
        if ($schema->hasColumn('assets', 'model_id') && $schema->hasColumn('models', 'category_id')) {
            $rows = DB::table('assets as a')->join('models as m', 'm.id', '=', 'a.model_id')->where('a.location_id', $office)
                ->whereNull('a.deleted_at')->whereNull('m.deleted_at')->get(['a.id', 'm.id as model_id', 'm.category_id']);
            $targets['asset'] = $rows->pluck('id')->all();
            $targets['assetmodel'] = $targets['asset_model'] = $rows->pluck('model_id')->all();
            $categories = array_merge($categories, $rows->pluck('category_id')->filter()->all());
        }
        $targets['category'] = array_unique($categories);
        return DB::table('gov_approval_policies')->where(fn ($q) => $q->where('policy_name', 'PRIMARY_AND_FINAL')
            ->orWhereNotNull('threshold_qty')->orWhereNotNull('threshold_value'))->where(function ($q) use ($targets) {
                $q->whereRaw('1 = 0');
                foreach ($targets as $type => $ids) {
                    $q->orWhere(fn ($part) => $part->where('target_type', $type)->whereIn('target_id', $ids));
                }
            })->exists();
    }
}
