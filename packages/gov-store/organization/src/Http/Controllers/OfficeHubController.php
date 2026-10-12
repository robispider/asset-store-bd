<?php

namespace GovStore\Organization\Http\Controllers;

use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use GovStore\Classification\Services\OfficeStarterCatalog;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Events\OfficeProvisioned;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\OrganizationActivityLog;
use GovStore\Organization\Services\OfficeAdministration;
use GovStore\Organization\Services\OfficeConfigurationService;
use GovStore\Organization\Services\OfficeLifecycleService;
use GovStore\Organization\Services\OfficeProvisioningService;
use GovStore\Organization\Services\OfficeReadinessService;
use GovStore\TenantScope\Contracts\MembershipContextResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class OfficeHubController extends Controller
{
    private function checkAccess(int $id): void
    {
        $guard = app(OfficeAdministration::class);
        $guard->office($guard->actor((int) auth()->id()), $id);
    }

    public function show(int $id)
    {
        $this->checkAccess($id);
        $location = Location::with(['company', 'parent'])->findOrFail($id);
        $profile = LocationProfile::with(['geoArea', 'officeAdmin'])->where('location_id', $id)->firstOrFail();
        $rolesList = OfficeResponsibility::where('location_id', $id)->get();
        $roles = (object) [
            'primary_approver_id' => $rolesList->where('role_slug', 'primary_approver')->first()?->user_id,
            'final_approver_id' => $rolesList->where('role_slug', 'final_approver')->first()?->user_id,
            'storekeeper_id' => $rolesList->where('role_slug', 'storekeeper')->first()?->user_id,
        ];
        $localStaff = User::withoutGlobalScopes()->whereNull('deleted_at')->where('activated', true)
            ->where('company_id', $location->company_id)->whereIn('id', function ($query) use ($id) {
                $query->select('user_id')->from('gov_office_memberships')->where('location_id', $id)->where('status', 'active');
            })->orderBy('first_name')->get()->filter(fn ($user) => app(MembershipContextResolver::class)
                ->hasActiveMembershipAt((int) $user->id, $id));
        $allUsers = $localStaff;
        $companies = Company::where('id', $location->company_id)->get();
        $allOffices = Location::where('company_id', $location->company_id)->where('id', '!=', $id)->orderBy('name')->get();
        $starter = DB::table('gov_office_starter_runs')->where('location_id', $id)->first();
        $readiness = app(OfficeReadinessService::class)->evaluateAndTransition($id);
        $profile->refresh();
        $activityLogs = OrganizationActivityLog::with('performer')->where('location_id', $id)->orderByDesc('created_at')->get();

        return view('govorg::provisioning.hub', compact(
            'location', 'profile', 'roles', 'localStaff', 'allUsers', 'companies', 'allOffices', 'activityLogs', 'starter', 'readiness'
        ));
    }

    public function update(Request $request, int $id)
    {
        $this->checkAccess($id);
        $request->validate([
            'name' => 'required|string|max:255', 'company_id' => 'nullable|integer',
            'parent_id' => 'nullable|integer', 'geo_area_id' => 'required|integer', 'office_admin_id' => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $location = Location::whereNull('deleted_at')->lockForUpdate()->findOrFail($id);
            $profile = LocationProfile::where('location_id', $id)->lockForUpdate()->firstOrFail();
            $this->checkAccess($id);
            abort_unless(in_array($profile->lifecycle_status, OfficeLifecycleService::ACTIVE, true), 409);
            // Company moves need consumer migration; geography has an audited relocation action.
            abort_unless((int) $request->company_id === (int) $location->company_id
                && (int) $request->geo_area_id === (int) $profile->geo_area_id, 409);
            abort_unless((int) $request->parent_id === (int) $location->parent_id, 409);
            if ($request->parent_id) {
                Location::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail((int) $request->parent_id);
            }
            app(OfficeProvisioningService::class)->assignOfficeAdmin($id,
                $request->office_admin_id ? (int) $request->office_admin_id : null, (int) auth()->id());
            abort_unless($location->update(['name' => $request->name, 'parent_id' => $request->parent_id ?: null]), 422);
            OrganizationActivityLog::create(['location_id' => $id, 'performed_by' => auth()->id(),
                'event_type' => 'profile_updated', 'details' => ['name' => $request->name, 'parent_id' => $request->parent_id ?: null]]);

            return redirect()->route('gov.org.hub.show', $id)->with('success', __('organization_labels::orglabel.lifecycle_saved'));
        });
    }

    public function saveRoles(Request $request, int $id, OfficeConfigurationService $service)
    {
        $this->checkAccess($id);
        $roles = $request->validate([
            'primary_approver_id' => 'required|integer', 'final_approver_id' => 'nullable|integer', 'storekeeper_id' => 'required|integer',
        ]);
        $service->saveRoles($id, $roles, (int) auth()->id());

        return redirect()->route('gov.org.hub.show', $id)->with('success', __('organization_labels::orglabel.lifecycle_saved'));
    }

    public function verifyGeo(int $id)
    {
        $this->checkAccess($id);
        DB::transaction(function () use ($id) {
            Location::whereNull('deleted_at')->lockForUpdate()->findOrFail($id);
            $profile = LocationProfile::where('location_id', $id)->lockForUpdate()->firstOrFail();
            $this->checkAccess($id);
            abort_unless(in_array($profile->lifecycle_status, OfficeLifecycleService::ACTIVE, true), 409);
            $profile->update(['geo_area_verified_at' => now(), 'geo_area_verified_by' => auth()->id()]);
            OrganizationActivityLog::create(['location_id' => $id, 'performed_by' => auth()->id(),
                'event_type' => 'geography_verified', 'details' => ['geo_area_id' => $profile->geo_area_id]]);
        });

        return redirect()->route('gov.org.hub.show', $id)->with('success', __('organization_labels::orglabel.lifecycle_saved'));
    }

    public function retryStarter(int $id)
    {
        $this->checkAccess($id);
        $location = Location::findOrFail($id);
        $profile = LocationProfile::where('location_id', $id)->firstOrFail();
        abort_unless($profile->office_admin_id && in_array($profile->lifecycle_status, OfficeLifecycleService::ACTIVE, true), 409);
        app(OfficeStarterCatalog::class)->schedule(new OfficeProvisioned(
            $location, (int) auth()->id(), $profile->office_type, (int) $profile->office_admin_id
        ));

        return redirect()->route('gov.org.hub.show', $id);
    }
}
