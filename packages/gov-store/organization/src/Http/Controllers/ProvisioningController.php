<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Company;
use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\Organization\Services\OfficeProvisioningService;

class ProvisioningController extends Controller
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

    public function index()
    {
        $this->checkIctOfficerAccess();
        $user = auth()->user();

        $officesQuery = Location::with(['company', 'parent', 'profile.geoArea', 'profile.officeAdmin']);
        
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            $jurisdiction = IctJurisdiction::with('geoArea')->where('user_id', $user->id)->firstOrFail();
            $officerHid = $jurisdiction->geoArea->hid ?? null;

            if (empty($officerHid)) {
                // Broken/missing jurisdiction geo → show nothing (fail closed), never everything.
                $officesQuery->whereRaw('1 = 0');
            } else {
                $officesQuery->whereHas('profile', function ($q) use ($officerHid) {
                    $q->whereIn('geo_area_id', function ($sub) use ($officerHid) {
                        $sub->select('GeoAreaId')
                            ->from('gov_geo_areas')
                            ->where('hid', 'like', $officerHid . '%');
                    });
                });
            }
        }

        $offices = $officesQuery->orderBy('name')->get();
        $profiles = LocationProfile::with('officeAdmin')->get()->keyBy('location_id');
        $users = User::orderBy('first_name')->get();
        $companies = Company::orderBy('name')->get();

        return view('govorg::provisioning.index', compact('offices', 'profiles', 'users', 'companies'));
    }

    public function create()
    {
        $this->checkIctOfficerAccess();
        $user = auth()->user();

        // DECOUPLED SECURITY PASSTHROUGH: 
        // We pre-calculate the restricted HID tree serverside, and pass it to the view
        $restrictToHid = null;
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            $jurisdiction = IctJurisdiction::with('geoArea')->where('user_id', $user->id)->first();
            $restrictToHid = $jurisdiction && $jurisdiction->geoArea ? $jurisdiction->geoArea->hid : null;
        }
        
        $companies = Company::orderBy('name')->get();
        $offices = Location::orderBy('name')->get();
        $users = User::orderBy('first_name')->get();

        return view('govorg::provisioning.create', compact('companies', 'offices', 'users', 'restrictToHid'));
    }

    public function checkDuplicate(Request $request)
    {
        $this->checkIctOfficerAccess();
        $companyId = $request->input('company_id');
        $geoAreaId = $request->input('geo_area_id');

        if (empty($companyId) || empty($geoAreaId)) {
            return response()->json([]);
        }

        $duplicates = Location::where('company_id', $companyId)
            ->whereHas('profile', function($q) use ($geoAreaId) {
                $q->where('geo_area_id', $geoAreaId);
            })
            ->with(['profile.geoArea', 'company'])
            ->get();

        $formatted = [];
        foreach ($duplicates as $loc) {
            $formatted[] = [
                'id' => $loc->id,
                'name' => $loc->name,
                'geo_name' => $loc->profile->geoArea->en_name ?? 'N/A'
            ];
        }

        return response()->json($formatted);
    }

    public function provision(Request $request, OfficeProvisioningService $service, GeoAreaService $geoService)
    {
        $this->checkIctOfficerAccess();

        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'office_admin_id' => 'nullable|integer',
            'geo_area_id' => 'required|integer',
        ]);

        try {
            $data = $request->all();
            $geoArea = $geoService->getById((int)$data['geo_area_id']);
            
            if ($geoArea) {
                $parts = array_filter(explode('/', $geoArea->hid));
                $city = ''; $state = '';

                foreach ($parts as $code) {
                    $parent = \GovStore\GeoAreas\Models\GeoArea::where('geo_code', $code)->first();
                    if ($parent) {
                        if ($parent->geo_type === 'upazilla' || $parent->geo_type === 'city') {
                            $city = $parent->en_name;
                        } elseif ($parent->geo_type === 'district') {
                            $state = $parent->en_name;
                        }
                    }
                }

                $data['city'] = $city;
                $data['state'] = $state;
            }

            $service->provisionOffice($data, auth()->id());
            return redirect()->route('gov.org.provisioning.index')->with('success', 'Office successfully provisioned.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function assignAdmin(Request $request, OfficeProvisioningService $service)
    {
        $this->checkIctOfficerAccess();

        $request->validate([
            'location_id' => 'required|integer',
            'office_admin_id' => 'nullable|integer',
        ]);

        try {
            $service->assignOfficeAdmin(
                $request->location_id, 
                $request->office_admin_id ?: null, 
                auth()->id()
            );
            return redirect()->back()->with('success', 'Office Administrator updated.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Update error: ' . $e->getMessage());
        }
    }

    /* ==========================================
       ICT OFFICER JURISDICTIONS (The Setup Tag)
       ========================================== */

    public function jurisdictionsIndex()
    {
        $this->checkIctOfficerAccess();

        $jurisdictions = IctJurisdiction::with(['user.location', 'geoArea'])->get();
        $users = User::orderBy('first_name')->get();

        return view('govorg::provisioning.jurisdictions', compact('jurisdictions', 'users'));
    }

    public function jurisdictionsStore(Request $request)
    {
        $this->checkIctOfficerAccess();

        $request->validate([
            'user_id' => 'required|integer',
            'geo_area_id' => 'required|integer',
        ]);

        try {
            IctJurisdiction::updateOrCreate(
                ['user_id' => $request->user_id],
                ['geo_area_id' => $request->geo_area_id]
            );

            return redirect()->back()->with('success', 'ICT Officer boundary successfully mapped.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Mapping error: ' . $e->getMessage());
        }
    }

    public function jurisdictionsDestroy($id)
    {
        $this->checkIctOfficerAccess();

        try {
            $jurisdiction = IctJurisdiction::findOrFail($id);
            $jurisdiction->delete();

            return redirect()->back()->with('success', 'ICT Officer jurisdiction revoked.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Revocation error: ' . $e->getMessage());
        }
    }
}