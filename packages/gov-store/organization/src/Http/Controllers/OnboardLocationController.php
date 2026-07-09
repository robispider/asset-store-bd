<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Company;
use App\Models\User;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\Organization\Services\OfficeProvisioningService;

class OnboardLocationController extends Controller
{
    private function checkIctOfficerAccess()
    {
        $user = auth()->user();
        if ($user->isSuperUser() || $user->hasAccess('admin')) {
            return;
        }

        $isIctOfficer = IctJurisdiction::where('user_id', $user->id)->exists();
        if (!$isIctOfficer) {
            abort(403, 'Unauthorized administrative request.');
        }
    }

    /**
     * Renders the form to onboard existing unprovisioned buildings, 
     * supporting automatic pre-selection from a row action link.
     */
    public function create(Request $request)
    {
        $this->checkIctOfficerAccess();
        $user = auth()->user();

        $restrictToHid = null;
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            $jurisdiction = IctJurisdiction::with('geoArea')->where('user_id', $user->id)->first();
            $restrictToHid = $jurisdiction && $jurisdiction->geoArea ? $jurisdiction->geoArea->hid : null;
        }

        $companies = Company::orderBy('name')->get();
        $users = User::orderBy('first_name')->get();

        // CONTEXT-AWARE HOOK: Detect if a specific location ID was passed from the Registry row click
        $preselectedLocation = null;
        if ($request->has('location_id')) {
            $preselectedLocation = Location::findOrFail($request->input('location_id'));
        }

        // Standard unprovisioned dropdown list
        $unprovisionedLocations = Location::doesntHave('profile')->orderBy('name')->get();

        return view('govorg::provisioning.onboard', compact(
            'companies', 'users', 'restrictToHid', 'unprovisionedLocations', 'preselectedLocation'
        ));
    }

    public function store(Request $request, OfficeProvisioningService $service, GeoAreaService $geoService)
    {
        $this->checkIctOfficerAccess();

        $request->validate([
            'existing_location_id' => 'required|integer',
            'geo_area_id' => 'required|integer',
            'company_id' => 'nullable|integer',
            'office_admin_id' => 'nullable|integer',
        ]);

        try {
            $data = $request->all();
            
            // Auto-populate city and state names based on dynamic parents path extractor
            $geoArea = $geoService->getById((int)$data['geo_area_id']);
            if ($geoArea) {
                $parts = array_filter(explode('/', $geoArea->hid));
                $city = ''; $state = '';

                foreach ($parts as $code) {
                    $parent = \GovStore\GeoAreas\Models\GeoArea::where('geo_code', $code)->first();
                    if ($parent) {
                        if ($parent->geo_type === 'upazilla' || $parent->geo_type === 'city') $city = $parent->en_name;
                        if ($parent->geo_type === 'district') $state = $parent->en_name;
                    }
                }

                $data['city'] = $city;
                $data['state'] = $state;
            }

            // Reuse same provisioning service to keep database transactions unified
            $service->provisionOffice($data, auth()->id());

            return redirect()->route('gov.org.provisioning.index')->with('success', 'Existing office successfully onboarded.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}