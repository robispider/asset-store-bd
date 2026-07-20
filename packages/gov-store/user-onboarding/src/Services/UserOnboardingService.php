<?php

namespace GovStore\UserOnboarding\Services;

use GovStore\UserOnboarding\Models\UserOnboarding;
use GovStore\OfficeMembership\Services\OfficeMembershipService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class UserOnboardingService
{
    /**
     * Completes onboarding by assigning the employee to their first physical Office.
     */
    public function assignToOffice(int $onboardingId, int $locationId): void
    {
        $onboarding = UserOnboarding::findOrFail($onboardingId);
        if ($onboarding->status !== 'WAITING') {
            throw new Exception("This user has already completed onboarding.");
        }

        DB::transaction(function () use ($onboarding, $locationId) {
            
            // 1. Grant physical Home Office membership
            $membershipService = app(OfficeMembershipService::class);
            $membershipService->grantMembership($onboarding->user_id, $locationId, true);

            $membership = DB::table('gov_office_memberships')
                ->where('user_id', $onboarding->user_id)
                ->where('location_id', $locationId)
                ->first();

            // 2. Sync core Snipe-IT Location projection (Keeps core framework happy)
            $location = DB::table('locations')->where('id', $locationId)->first();
            $user = User::withoutGlobalScopes()->findOrFail($onboarding->user_id);
            $user->update([
                'location_id' => $locationId,
                'company_id'  => $location ? $location->company_id : null
            ]);

            // 3. Mark Onboarding as COMPLETED
            $onboarding->update([
                'status' => 'COMPLETED',
                'assigned_membership_id' => $membership ? $membership->id : null,
            ]);
        });
    }
}
