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
        if ($user->isSuperUser()) {
            return;
        }

        $isIctOfficer = IctJurisdiction::where('user_id', $user->id)->exists();
        if (!$isIctOfficer) {
            abort(403, __('organization_labels::orglabel.onboard_controller_unauthorized'));
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
        $jurisdiction = null;
        if (!$user->isSuperUser()) {
            $jurisdiction = IctJurisdiction::with('geoArea')->where('user_id', $user->id)->first();
            $restrictToHid = $jurisdiction && $jurisdiction->geoArea ? $jurisdiction->geoArea->hid : null;
        }

        $scopeArea = $jurisdiction?->geoArea ?? null;
        $companies = Company::orderBy('name')->get();
        $offices = Location::has('profile')->orderBy('name')->get();

        // Context-aware: a registry row or the Add office search passes the location to set up.
        $preselectedLocation = null;
        if ($request->has('location_id')) {
            $preselectedLocation = Location::doesntHave('profile')->findOrFail($request->input('location_id'));
        }

        $unprovisionedLocations = Location::doesntHave('profile')->orderBy('name')->get();

        return view('govorg::provisioning.onboard', compact(
            'companies', 'offices', 'restrictToHid', 'scopeArea', 'unprovisionedLocations', 'preselectedLocation'
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
            'office_type' => 'required|in:default,hospital,school,ict_office',
            'parent_id' => 'nullable|integer',
        ], [], ProvisioningController::attributes());

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
            $location = $service->provisionOffice($data, auth()->id());

            return redirect()->route('gov.org.hub.show', $location->id)
                ->with('success', __('organization_labels::office.onboarded', ['name' => $location->name]));
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
