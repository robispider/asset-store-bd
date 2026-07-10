<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Company;
use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\LocationRole;
use GovStore\GeoAreas\Services\GeoAreaService; // ONLY IMPORT THE SERVICE
use GovStore\Organization\Services\OfficeProvisioningService;
use GovStore\Organization\ViewModels\OfficeRegistryViewModel;

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

        // 2. Build Core Scoped Query eager loading Snipe-IT relationships
        $query = Location::with(['company', 'parent', 'profile.geoArea', 'profile.officeAdmin']);

        // Scope queue strictly to the officer's jurisdiction bounds
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

        // Apply Filters
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

        // Execute query
        $offices = $query->orderBy('name')->get();

        // 3. DICTIONARY LOOKUP: Fetch roles for only the locations currently on this page
        $locationIds = $offices->pluck('id');
        $rolesDictionary = LocationRole::whereIn('location_id', $locationIds)
                            ->get()
                            ->keyBy('location_id');

        // 4. MAP ELOCUENT MODELS TO THE VIEWMODEL (Dynamic Paginator check)
        $collection = $offices instanceof \Illuminate\Pagination\LengthAwarePaginator ? $offices->getCollection() : $offices;
        
        $collection->transform(function ($loc) use ($rolesDictionary) {
            $role = $rolesDictionary->get($loc->id); // Match role to location in memory
            return new OfficeRegistryViewModel($loc, $role);
        });

        // Fetch select list values using the decoupled Shared Service API
        $companies = Company::orderBy('name')->get();
        $districts = $geoService->getAllDistricts(); // BOUNDARY CORRECTED

        return view('govorg::provisioning.index', compact(
            'offices', 'companies', 'districts',
            'totalOfficesCount', 'operationalCount', 'pendingCount', 'ministriesCount'
        ));
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
                // Resolved parents names using the decoupled Service API
                $geoNames = $geoService->resolveParentNames($geoArea->hid); // BOUNDARY CORRECTED
                $data['city'] = $geoNames['city'];
                $data['state'] = $geoNames['state'];
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