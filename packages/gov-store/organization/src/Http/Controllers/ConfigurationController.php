<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\LocationRole;
use GovStore\Organization\Services\OfficeConfigurationService;
use GovStore\Organization\Services\OfficeReadinessService;

class ConfigurationController extends Controller
{
    private function resolveAssignedLocationId()
    {
        $user = auth()->user();

        // 1. Superadmins bypass strict local scopes and can pass any location ID
        if (($user->isSuperUser() || $user->hasAccess('admin')) && request()->has('location_id')) {
            return (int)request()->input('location_id');
        }

        // 2. Standard Office Administrators are locked strictly to their assigned profile
        $profile = LocationProfile::where('office_admin_id', $user->id)->first();
        
        if (!$profile) {
            abort(403, 'Access Denied: You are not assigned as an Office Administrator.');
        }

        return $profile->location_id;
    }

    public function index(OfficeReadinessService $readinessService)
    {
        $locationId = $this->resolveAssignedLocationId();
        
        $location = Location::findOrFail($locationId);
        $profile = LocationProfile::where('location_id', $locationId)->firstOrFail();
        $roles = LocationRole::where('location_id', $locationId)->first();
        
        // Fetch all local staff users mapped to this physical building/location
        $localStaff = User::where('location_id', $locationId)->orderBy('first_name')->get();
        
        // Execute operational checks and fetch status
        $readiness = $readinessService->evaluateAndTransition($locationId);

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

        try {
            $service->saveRoles($locationId, $request->all(), auth()->id());
            return redirect()->back()->with('success', 'Office roles successfully saved.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Configuration error: ' . $e->getMessage());
        }
    }
}