<?php

namespace GovStore\Tracking\Services;

use App\Models\User;
use GovStore\Organization\Models\CompanyAdmin;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\OperationUnit;

class TrackingAuthorizationService
{
    public function allows(Initiative $initiative, array $designations = ['HEAD', 'OFFICER', 'SUPPORT', 'MONITOR']): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperUser()
            || CompanyAdmin::where('user_id', $user->id)->where('company_id', $initiative->owner_company_id)->exists()
            || OperationUnit::where('initiative_id', $initiative->id)->where('user_id', $user->id)->whereIn('designation', $designations)->exists());
    }

    public function canCreate(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperUser() || CompanyAdmin::where('user_id', $user->id)->exists());
    }

    /**
     * Centralized security gate enforcing the GovStore permission matrix.
     * Throws a 403 HttpException if the user fails authorization.
     */
    public function authorize(Initiative $initiative, array $allowedDesignations = ['HEAD']): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403, 'Unauthenticated.');
        }

        // 1. Global Superuser Bypass (Allowed full system overrides)
        if ($user->isSuperUser()) {
            return;
        }

        // 2. Ministry Admin Verification (Uses your native CompanyAdmin model)
        // Checks if the user is a registered Admin specifically for the Initiative's owning Ministry
        $isCompanyAdmin = CompanyAdmin::where('user_id', $user->id)
            ->where('company_id', $initiative->owner_company_id)
            ->exists();

        if ($isCompanyAdmin) {
            return;
        }

        // 3. Operation Unit Team Verification
        $isOperationUnitManager = OperationUnit::where('initiative_id', $initiative->id)
            ->where('user_id', $user->id)
            ->whereIn('designation', $allowedDesignations)
            ->exists();

        if ($isOperationUnitManager) {
            return;
        }

        // 4. Fallback Blockade
        abort(403, 'Unauthorized. Only authorized Project Directors (Operation Heads), Officers, or Ministry Admins can execute these configurations.');
    }
}
