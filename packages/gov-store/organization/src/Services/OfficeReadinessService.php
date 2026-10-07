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
            $hasPrimary = OfficeResponsibility::where('location_id', $locationId)
                ->whereIn('user_id', $eligible)
                ->where('role_slug', 'primary_approver')
                ->exists();

            $hasStorekeeper = OfficeResponsibility::where('location_id', $locationId)
                ->whereIn('user_id', $eligible)
                ->where('role_slug', 'storekeeper')
                ->exists();
        
            $usersCount = $eligible->count();

            $checklist = [
                'has_office_admin'     => $profile && $eligible->contains($profile->office_admin_id),
                'has_primary_approver' => $hasPrimary,
                'has_storekeeper'      => $hasStorekeeper,
                'has_users'            => $usersCount > 0,
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
}
