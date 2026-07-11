<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Company;
use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Models\OrganizationActivityLog;
use GovStore\Organization\Services\OfficeConfigurationService;
use GovStore\GeoAreas\Services\GeoAreaService;

class OfficeHubController extends Controller
{
   private function checkAccess($locationId)
    {
        $user = auth()->user();
        
        // 1. Superadmins and Global Admins always have access
        if ($user->isSuperUser() || $user->hasAccess('admin')) {
            return;
        }

        // Fetch the location's profile to inspect its territory
        $profile = LocationProfile::where('location_id', $locationId)->first();
        if (!$profile) {
            abort(403, 'Access Denied: This office building is not profiled.');
        }

        // 2. ICT OFFICER CHECK: Are they the authorized ICT Officer for this territory?
        $jurisdiction = \GovStore\Organization\Models\IctJurisdiction::where('user_id', $user->id)->first();
        if ($jurisdiction && $jurisdiction->geo_area_id) {
            $geoService = app(GeoAreaService::class);
            
            // Allow access if the office falls recursively inside their jurisdiction boundary
            if ($geoService->isWithinBoundary($jurisdiction->geo_area_id, $profile->geo_area_id)) {
                return;
            }
        }

        // 3. OFFICE ADMIN CHECK: Are they the designated administrator for this specific building?
        if ((int)$profile->office_admin_id === (int)$user->id) {
            return;
        }

        // If all checks fail, block access
        abort(403, 'Access Denied: You are not authorized to administer this office.');
    }

    public function show($id)
    {
        $this->checkAccess($id);

        $location = Location::with(['company', 'parent'])->findOrFail($id);
        
        $profile = LocationProfile::with(['geoArea', 'officeAdmin'])->where('location_id', $id)->first();
        
        if (!$profile) {
            return redirect()->route('gov.org.provisioning.index')
                             ->with('error', 'This office building has not been configured with geographic territory parameters yet. Please provision it first.');
        }

        // 1. Fetch assigned responsibilities from our new pivot matrix
        $rolesList = OfficeResponsibility::where('location_id', $id)->get();

        // 2. Format into a flat object to maintain compatibility with the Blade template
        $roles = (object)[
            'primary_approver_id' => $rolesList->where('role_slug', 'primary_approver')->first()?->user_id,
            'final_approver_id'   => $rolesList->where('role_slug', 'final_approver')->first()?->user_id,
            'storekeeper_id'      => $rolesList->where('role_slug', 'storekeeper')->first()?->user_id,
        ];

        $localStaff = User::where('location_id', $id)->orderBy('first_name')->get();
        $allUsers = User::orderBy('first_name')->get();
        $companies = Company::orderBy('name')->get();
        $allOffices = Location::where('id', '!=', $id)->orderBy('name')->get();

        $activityLogs = OrganizationActivityLog::with('performer')
                            ->where('location_id', $id)
                            ->orderBy('created_at', 'desc')
                            ->get();

        return view('govorg::provisioning.hub', compact(
            'location', 'profile', 'roles', 'localStaff', 'allUsers', 'companies', 'allOffices', 'activityLogs'
        ));
    }

    public function update(Request $request, $id, GeoAreaService $geoService)
    {
        $this->checkAccess($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|integer',
            'parent_id' => 'nullable|integer',
            'geo_area_id' => 'required|integer',
            'office_admin_id' => 'nullable|integer',
        ]);

        $location = Location::findOrFail($id);
        $profile = LocationProfile::where('location_id', $id)->first();

        if (!$profile) {
            return redirect()->route('gov.org.provisioning.index')->with('error', 'Office profile details not found.');
        }

        $city = $location->city;
        $state = $location->state;

        if ((int)$request->geo_area_id !== (int)$profile->geo_area_id) {
            $geoArea = $geoService->getById((int)$request->geo_area_id);
            if ($geoArea) {
                $geoNames = $geoService->resolveParentNames($geoArea->hid);
                $city = $geoNames['city'];
                $state = $geoNames['state'];
            }
        }

        $location->update([
            'name' => $request->name,
            'company_id' => $request->company_id ?: null,
            'parent_id' => $request->parent_id ?: null,
            'city' => $city,
            'state' => $state
        ]);

        $profile->update([
            'geo_area_id' => $request->geo_area_id,
            'office_admin_id' => $request->office_admin_id ?: null
        ]);

        OrganizationActivityLog::create([
            'location_id' => $id,
            'performed_by' => auth()->id(),
            'event_type' => 'status_changed',
            'details' => ['message' => 'Office profiles and metadata modified']
        ]);

        return redirect()->back()->with('success', 'Office profiles updated successfully.');
    }

    public function saveRoles(Request $request, $id, OfficeConfigurationService $service)
    {
        $this->checkAccess($id);

        $request->validate([
            'primary_approver_id' => 'required|integer',
            'final_approver_id' => 'nullable|integer',
            'storekeeper_id' => 'required|integer',
        ]);

        try {
            $service->saveRoles($id, $request->all(), auth()->id());
            return redirect()->back()->with('success', 'Office role configuration saved.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function verifyGeo($id)
    {
        $this->checkAccess($id);
        $profile = LocationProfile::where('location_id', $id)->first();

        if (!$profile) {
            return redirect()->route('gov.org.provisioning.index')->with('error', 'Office profile details not found.');
        }

        $profile->update([
            'geo_area_verified_at' => now(),
            'geo_area_verified_by' => auth()->id()
        ]);

        OrganizationActivityLog::create([
            'location_id' => $id,
            'performed_by' => auth()->id(),
            'event_type' => 'status_changed',
            'details' => ['message' => 'Geographic tag accuracy verified and locked.']
        ]);

        return redirect()->back()->with('success', 'Geographical territory tagged and verified successfully.');
    }
}