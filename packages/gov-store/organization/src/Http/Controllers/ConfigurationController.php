<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Services\OfficeConfigurationService;
use GovStore\Organization\Services\OfficeReadinessService;

class ConfigurationController extends Controller
{
    private function resolveAssignedLocationId()
    {
        $user = auth()->user();

        // 1. Superadmins bypass strict local scopes and can pass any location ID
        if ($user->isSuperUser() && request()->has('location_id')) {
            $id = (int) request()->input('location_id');
            $guard = app(\GovStore\Organization\Services\OfficeAdministration::class);
            $guard->office($guard->actor((int) $user->id), $id);
            return $id;
        }

        // 2. Standard Office Administrators are locked strictly to their assigned profile
        $profile = LocationProfile::where('office_admin_id', $user->id)
            ->where('location_id', app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId)->first();
        
        if (!$profile) {
            abort(403, 'Access Denied: You are not assigned as an Office Administrator.');
        }

        $guard = app(\GovStore\Organization\Services\OfficeAdministration::class);
        $guard->office($guard->actor((int) $user->id), (int) $profile->location_id);
        return $profile->location_id;
    }

    public function index(OfficeReadinessService $readinessService)
    {
        $locationId = $this->resolveAssignedLocationId();
        
        $location = Location::findOrFail($locationId);
        $profile = LocationProfile::where('location_id', $locationId)->firstOrFail();
        
        // =========================================================================
        // REFACTORED: Load roles from the new pivot matrix, keeping view compatible
        // =========================================================================
        $rolesList = OfficeResponsibility::where('location_id', $locationId)->get();

        $roles = (object)[
            'primary_approver_id' => $rolesList->where('role_slug', 'primary_approver')->first()?->user_id,
            'final_approver_id'   => $rolesList->where('role_slug', 'final_approver')->first()?->user_id,
            'storekeeper_id'      => $rolesList->where('role_slug', 'storekeeper')->first()?->user_id,
        ];
        
        // Fetch all local staff users mapped to this physical building/location
        $localStaff = User::withoutGlobalScopes()->whereNull('deleted_at')->where('activated', true)
            ->where('company_id', $location->company_id)->whereIn('id', function ($query) use ($locationId) {
                $query->select('user_id')->from('gov_office_memberships')->where('location_id', $locationId)->where('status', 'active');
            })->orderBy('first_name')->get()->filter(fn ($user) => app(\GovStore\TenantScope\Contracts\MembershipContextResolver::class)
                ->hasActiveMembershipAt((int) $user->id, (int) $locationId));
        
        // Execute operational checks and fetch status
        $readiness = $readinessService->evaluateAndTransition($locationId);
        $profile->refresh();

        return view('govorg::configuration.index', compact('location', 'profile', 'roles', 'localStaff', 'readiness'));
    }

    public function save(Request $request, OfficeConfigurationService $service)
    {
        $locationId = $this->resolveAssignedLocationId();

        $request->validate([
            'primary_approver_id' => 'required|integer',
            'final_approver_id'   => 'nullable|integer',
            'storekeeper_id'      => 'required|integer',
        ]);

        $service->saveRoles($locationId, $request->only(['primary_approver_id', 'final_approver_id', 'storekeeper_id']), (int) auth()->id());
        return redirect()->back()->with('success', __('organization_labels::orglabel.lifecycle_saved'));
    }
}
