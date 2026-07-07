<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Company;
use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Services\OfficeProvisioningService;
use GovStore\GeoAreas\Services\GeoAreaService;

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

        $officesQuery = Location::with(['company', 'parent']);
        
        // Scope office grid viewing strictly to the officer's jurisdiction bounds
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            $jurisdiction = IctJurisdiction::where('user_id', $user->id)->firstOrFail();
            
            $officesQuery->whereHas('profile', function ($q) use ($jurisdiction) {
                $q->whereIn('geo_area_id', function ($sub) use ($jurisdiction) {
                    $sub->select('GeoAreaId')
                        ->from('gov_geo_areas')
                        ->where('hid', 'like', $jurisdiction->geoArea->hid . '%');
                });
            });
        }

        $offices = $officesQuery->orderBy('name')->get();
        $profiles = LocationProfile::with('officeAdmin')->get()->keyBy('location_id');
        $users = User::orderBy('first_name')->get();
        $companies = Company::orderBy('name')->get();

        return view('govorg::provisioning.index', compact('offices', 'profiles', 'users', 'companies'));
    }

    /**
     * Consume the shared GeoAreaService to search across all designated regional types
     * dynamically filtered by the ICT Officer's territory boundaries.
     */
    public function geoSearch(Request $request, GeoAreaService $geoService)
    {
        $this->checkIctOfficerAccess();
        $user = auth()->user();
        $term = $request->input('q', '');

        if (empty($term)) {
            return response()->json([]);
        }

        $restrictToHid = null;
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            $jurisdiction = IctJurisdiction::where('user_id', $user->id)->firstOrFail();
            $restrictToHid = $jurisdiction->geoArea->hid;
        }

        // Broad range of allowed regional classifications in Bangladesh
        $allowedTypes = [
            'divison', 
            'district', 
            'upazilla', 
            'union', 
            'pourasabha', 
            'pouro_ward', 
            'city_thana', 
            'city', 
            'thana_ward'
        ];

        // Query the core geographic database through the shared reference API
        $results = $geoService->search($term, $allowedTypes, $restrictToHid);

        $formatted = [];
        foreach ($results as $area) {
            $formatted[] = [
                'id' => $area->GeoAreaId,
                'text' => "{$area->en_name} ({$area->bn_name}) - " . ucwords(str_replace('_', ' ', $area->geo_type))
            ];
        }

        return response()->json($formatted);
    }

    public function provision(Request $request, OfficeProvisioningService $service)
    {
        $this->checkIctOfficerAccess();

        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'office_admin_id' => 'nullable|integer',
            'geo_area_id' => 'required|integer',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
        ]);

        try {
            $service->provisionOffice($request->all(), auth()->id());
            
            if (session()->has('duplicate_warning')) {
                return redirect()->back()->with('warning', session('duplicate_warning'));
            }

            return redirect()->back()->with('success', 'New office successfully provisioned.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Provisioning error: ' . $e->getMessage());
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

        // Load all active officer mappings with their home office users and geographic territories
        $jurisdictions = IctJurisdiction::with(['user.location', 'geoArea'])->get();
        
        // Fetch all users to select from
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