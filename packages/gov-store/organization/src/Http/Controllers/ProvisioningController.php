<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Company;
use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\Organization\Services\OfficeProvisioningService;

class ProvisioningController extends Controller
{
    private function checkIctOfficerAccess()
    {
        if (!auth()->user()->isSuperUser() && !auth()->user()->hasAccess('admin')) {
            abort(403, 'Unauthorized. Administrative credentials required.');
        }
    }

    /**
     * Renders the Office Registry (Master Command Dashboard)
     */
    public function index(Request $request, GeoAreaService $geoService)
    {
        $this->checkIctOfficerAccess();
        $user = auth()->user();

        // 1. Calculate Real-Time Rollout Stats Metrics (Top Dashboard Badges)
        $totalOfficesCount = Location::count();
        $operationalCount = LocationProfile::where('lifecycle_status', 'operational')->count();
        $pendingCount = LocationProfile::where('lifecycle_status', '!=', 'operational')->count();
        $ministriesCount = Location::whereNotNull('company_id')->distinct('company_id')->count();

        // 2. Build Core Scoped Query mapping profile extensions
        $query = Location::with(['company', 'parent', 'profile.geoArea', 'profile.officeAdmin']);

        // Scope queue strictly to the officer's jurisdiction bounds if they are not a Superadmin
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            $jurisdiction = IctJurisdiction::where('user_id', $user->id)->firstOrFail();
            $query->whereHas('profile', function ($q) use ($jurisdiction) {
                $q->whereIn('geo_area_id', function ($sub) use ($jurisdiction) {
                    $sub->select('GeoAreaId')
                        ->from('gov_geo_areas')
                        ->where('hid', 'like', $jurisdiction->geoArea->hid . '%');
                });
            });
        }

        // 3. Apply Top Bar Active Filtering
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhereHas('profile.officeAdmin', function($sq) use ($term) {
                      $sq->where('first_name', 'like', "%{$term}%")
                         ->orWhere('last_name', 'like', "%{$term}%")
                         ->orWhere('username', 'like', "%{$term}%");
                  });
            });
        }

        if ($request->filled('ministry_id')) {
            $query->where('company_id', $request->input('ministry_id'));
        }

        if ($request->filled('district_id')) {
            $districtId = $request->input('district_id');
            $query->whereHas('profile.geoArea', function($q) use ($districtId, $geoService) {
                $district = $geoService->getById($districtId);
                if ($district) {
                    $q->where('hid', 'like', $district->hid . '%');
                }
            });
        }

        if ($request->filled('status')) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('lifecycle_status', $request->input('status'));
            });
        }

        $offices = $query->orderBy('name')->get();

        // Fetch select list values
        $companies = Company::orderBy('name')->get();
        $districts = GeoArea::where('geo_type', 'district')->orderBy('en_name')->get();

        return view('govorg::provisioning.index', compact(
            'offices', 'companies', 'districts',
            'totalOfficesCount', 'operationalCount', 'pendingCount', 'ministriesCount'
        ));
    }

    /**
     * Focused View: Renders the dedicated step-by-step Provisioning Workspace
     */
    public function create()
    {
        $this->checkIctOfficerAccess();
        
        $companies = Company::orderBy('name')->get();
        $offices = Location::orderBy('name')->get();
        $users = User::orderBy('first_name')->get();

        return view('govorg::provisioning.create', compact('companies', 'offices', 'users'));
    }

    /**
     * AJAX Endpoint: Soft duplicate detection checker. 
     * Scans for similar existing registries in the same Union/Upazila territory.
     */
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
            // Resolve parent geographic names and inject into the creation parameters
            // This auto-populates locations.city (Upazila) and locations.state (Zila) correctly
            $data = $request->all();
            $geoArea = $geoService->getById((int)$data['geo_area_id']);
            
            if ($geoArea) {
                // Parse standard parts
                $parts = array_filter(explode('/', $geoArea->hid));
                $city = '';
                $state = '';

                foreach ($parts as $code) {
                    $parent = GeoArea::where('geo_code', $code)->first();
                    if ($parent) {
                        if (in_array($parent->geo_type, ['upazilla', 'city'])) $city = $parent->en_name;
                        if ($parent->geo_type === 'district') $state = $parent->en_name;
                    }
                }

                $data['city'] = $city;
                $data['state'] = $state;
            }

            $service->provisionOffice($data, auth()->id());
            return redirect()->route('gov.org.provisioning.index')->with('success', 'Office successfully provisioned and tagged.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}