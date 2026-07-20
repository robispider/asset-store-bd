<?php

namespace GovStore\UserOnboarding\Observers;

use App\Models\User;
use GovStore\UserOnboarding\Models\UserOnboarding;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\AssignmentResolver;
use GovStore\OfficeMembership\Services\OfficeMembershipService;
use Illuminate\Support\Facades\DB;

class SnipeUserOnboardingObserver
{
    /**
     * Intercept BEFORE saving: Enforce auto-onboarding context injection for Office Admins
     */
    public function creating(User $model)
    {
        $creator = auth()->user();
        if ($creator && app()->bound(TenantContext::class)) {
            $context = app(TenantContext::class);
            $role = app(AssignmentResolver::class)->resolveActiveRole($creator->id, $context->locationId);

            // Office Admin rule: If they forgot to assign a location, auto-inject their active office context
            if ($role === 'office_admin' && is_null($model->location_id)) {
                $model->location_id = $context->locationId;
                $model->company_id  = $context->companyId;
            }
        }
    }

    /**
     * Intercept AFTER saving: Determine routing path (Direct completed vs Waiting Queue)
     */
    public function created(User $model)
    {
        $creator = auth()->user();
        if (!$creator || !app()->bound(TenantContext::class)) return;

        $context = app(TenantContext::class);
        $role = app(AssignmentResolver::class)->resolveActiveRole($creator->id, $context->locationId);

        if ($role === 'office_admin') {
            // 1. OFFICE ADMIN WORKFLOW: Auto-onboard immediately
            $membershipService = app(OfficeMembershipService::class);
            
            // Grant home office membership natively
            $membershipService->grantMembership($model->id, $context->locationId, true);
            $membership = DB::table('gov_office_memberships')
                ->where('user_id', $model->id)
                ->where('location_id', $context->locationId)
                ->first();

            UserOnboarding::create([
                'user_id' => $model->id,
                'status' => 'COMPLETED',
                'creator_user_id' => $creator->id,
                'owner_type' => 'OFFICE_ADMIN',
                'owner_id' => $creator->id,
                'geo_area_id' => null,
                'assigned_membership_id' => $membership ? $membership->id : null,
            ]);
        } else {
            // 2. ICT OFFICER / COMPANY ADMIN WORKFLOW: Queue as WAITING
            $geoAreaId = null;
            $ownerType = 'SYSTEM';

            if ($role === 'ict_officer') {
                $ownerType = 'ICT_OFFICER';
                // Pull the ICT officer's active jurisdiction bound
                $geoAreaId = DB::table('gov_ict_jurisdictions')->where('user_id', $creator->id)->value('geo_area_id');
            } elseif ($role === 'company_admin') {
                $ownerType = 'COMPANY_ADMIN';
            }

            UserOnboarding::create([
                'user_id' => $model->id,
                'status' => 'WAITING',
                'creator_user_id' => $creator->id,
                'owner_type' => $ownerType,
                'owner_id' => $creator->id,
                'geo_area_id' => $geoAreaId,
            ]);
        }
    }
}
