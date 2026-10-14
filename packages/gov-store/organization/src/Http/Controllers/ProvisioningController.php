<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Location;
use App\Models\Company;
use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\Organization\Services\OfficeProvisioningService;
use GovStore\Organization\ViewModels\OfficeRegistryViewModel;

class ProvisioningController extends Controller
{
    /** Registry status tabs → lifecycle statuses ('unconfigured' = a location without a profile). */
    public const STATUS_TABS = [
        'all' => null,
        'action' => ['unconfigured', 'provisioned', 'configured'],
        'setup' => ['provisioned', 'configured'],
        'live' => ['operational'],
        'paused' => ['suspended'],
        'none' => ['unconfigured'],
    ];

    /** Older ?status= values keep working. */
    private const LEGACY_STATUS = ['operational' => 'live', 'suspended' => 'paused', 'unconfigured' => 'none'];

    private function checkIctOfficerAccess()
    {
        $user = auth()->user();
        if ($user->isSuperUser()) {
            return;
        }

        $isIctOfficer = IctJurisdiction::where('user_id', $user->id)->exists();
        if (!$isIctOfficer) {
            abort(403, __('organization_labels::orglabel.provisioning_controller_unauthorized'));
        }
    }

    private function jurisdiction(): ?IctJurisdiction
    {
        $user = auth()->user();

        return $user->isSuperUser() ? null : IctJurisdiction::with('geoArea')->where('user_id', $user->id)->first();
    }

    /**
     * Office registry: status tabs with counts, instant filters, and each office's next setup step.
     */
    public function index(Request $request, GeoAreaService $geoService)
    {
        $this->checkIctOfficerAccess();
        $user = auth()->user();

        // Build the authorized geography scope before computing any registry totals.
        $query = Location::with(['company', 'parent', 'profile.geoArea', 'profile.officeAdmin']);

        // Scope queue strictly to the officer's jurisdiction bounds
        if (!$user->isSuperUser()) {
            $jurisdiction = IctJurisdiction::where('user_id', $user->id)->firstOrFail();
            $query->whereHas('profile', function ($q) use ($jurisdiction) {
                $q->whereIn('geo_area_id', function ($sub) use ($jurisdiction) {
                    $sub->select('GeoAreaId')
                        ->from('gov_geo_areas')
                        ->where('hid', 'like', $jurisdiction->geoArea->hid . '%');
                });
            });
        }

        $scopeQuery = clone $query;
        $totalOfficesCount = (clone $scopeQuery)->count();
        $byStatus = LocationProfile::whereIn('location_id', (clone $scopeQuery)->select('locations.id'))
            ->selectRaw('lifecycle_status, count(*) as total')->groupBy('lifecycle_status')->pluck('total', 'lifecycle_status');
        $statusCounts = ['unconfigured' => max(0, $totalOfficesCount - (int) $byStatus->sum())] + $byStatus->map(fn ($n) => (int) $n)->all();
        $operationalCount = $statusCounts['operational'] ?? 0;
        $pendingCount = $totalOfficesCount - $operationalCount;
        $ministriesCount = (clone $scopeQuery)->whereNotNull('company_id')->distinct('company_id')->count('company_id');
        $tabCounts = [];
        foreach (self::STATUS_TABS as $tab => $statuses) {
            $tabCounts[$tab] = $statuses === null ? $totalOfficesCount
                : array_sum(array_map(fn ($status) => $statusCounts[$status] ?? 0, $statuses));
        }

        // Apply Filters
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhereHas('profile.geoArea', function ($sq) use ($term) {
                      $sq->where('en_name', 'like', "%{$term}%")->orWhere('bn_name', 'like', "%{$term}%");
                  })
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

        $status = (string) $request->input('status', 'all');
        $status = self::LEGACY_STATUS[$status] ?? $status;
        if (in_array($status, ['provisioned', 'configured'], true)) {
            $query->whereHas('profile', fn ($q) => $q->where('lifecycle_status', $status));
        } elseif (isset(self::STATUS_TABS[$status]) && self::STATUS_TABS[$status] !== null) {
            $statuses = self::STATUS_TABS[$status];
            $profiled = array_values(array_diff($statuses, ['unconfigured']));
            $query->where(function ($q) use ($statuses, $profiled) {
                if ($profiled) {
                    $q->whereHas('profile', fn ($p) => $p->whereIn('lifecycle_status', $profiled));
                }
                if (in_array('unconfigured', $statuses, true)) {
                    $q->orWhereDoesntHave('profile');
                }
            });
        } else {
            $status = 'all';
        }

        // Execute query
        $offices = $query->orderBy('name')->paginate(25)->withQueryString();

        // Responsibilities and active staff per office, loaded once for the page.
        $locationIds = $offices->getCollection()->pluck('id');
        $responsibilities = OfficeResponsibility::whereIn('location_id', $locationIds)->get();
        $staff = Schema::hasTable('gov_office_memberships')
            ? DB::table('gov_office_memberships')->whereIn('location_id', $locationIds)->where('status', 'active')
                ->selectRaw('location_id, count(*) as total')->groupBy('location_id')->pluck('total', 'location_id')
            : collect();

        $rolesDictionary = collect();
        foreach ($locationIds as $locId) {
            $officeResps = $responsibilities->where('location_id', $locId);
            $rolesDictionary->put($locId, (object)[
                'primary_approver_id' => $officeResps->where('role_slug', 'primary_approver')->first()?->user_id,
                'storekeeper_id'      => $officeResps->where('role_slug', 'storekeeper')->first()?->user_id,
            ]);
        }

        $offices->getCollection()->transform(fn ($loc) => new OfficeRegistryViewModel(
            $loc, $rolesDictionary->get($loc->id), (int) ($staff[$loc->id] ?? 0)
        ));

        $companies = Company::whereIn('id', (clone $scopeQuery)->whereNotNull('company_id')->select('company_id'))
            ->orderBy('name')->get();
        $districts = $geoService->getAllDistricts();
        $activeStatus = $status;

        return view('govorg::provisioning.index', compact(
            'offices', 'companies', 'districts', 'tabCounts', 'activeStatus',
            'totalOfficesCount', 'operationalCount', 'pendingCount', 'ministriesCount'
        ));
    }

    /**
     * Add office, step 1 searches locations that exist but were never set up (?find=), then the create form.
     */
    public function create(Request $request)
    {
        $this->checkIctOfficerAccess();
        $request->validate(['find' => 'nullable|string|max:100']);

        $jurisdiction = $this->jurisdiction();
        $restrictToHid = $jurisdiction?->geoArea?->hid;
        $scopeArea = $jurisdiction?->geoArea;

        $find = trim((string) $request->input('find', ''));
        $matches = $find === '' ? collect() : Location::doesntHave('profile')
            ->where('name', 'like', '%'.$find.'%')->orderBy('name')->limit(10)->get();

        $companies = Company::orderBy('name')->get();
        $offices = Location::has('profile')->orderBy('name')->get();

        return view('govorg::provisioning.create', compact('companies', 'offices', 'restrictToHid', 'scopeArea', 'find', 'matches'));
    }

    public function checkDuplicate(Request $request)
    {
        $this->checkIctOfficerAccess();
        $companyId = $request->input('company_id');
        $geoAreaId = $request->input('geo_area_id');

        if (empty($companyId) || empty($geoAreaId)) {
            return response()->json([]);
        }
        app(\GovStore\Organization\Services\OfficeAdministration::class)->geography(auth()->user(), (int) $geoAreaId);
        Company::whereNull('deleted_at')->findOrFail((int) $companyId);

        $duplicates = Location::where('company_id', $companyId)
            ->whereHas('profile', function($q) use ($geoAreaId) {
                $q->where('geo_area_id', $geoAreaId);
            })
            ->with(['profile.geoArea', 'company'])
            ->get();

        return response()->json($duplicates->map(fn ($loc) => [
            'id' => $loc->id,
            'name' => $loc->name,
            'geo_name' => $loc->profile->geoArea->en_name ?? null,
            'status' => app()->bound('gs.theme')
                ? app('gs.theme')->status('office_'.($loc->profile->lifecycle_status ?? 'unconfigured'))['label'] : null,
            'url' => route('gov.org.hub.show', $loc->id),
        ])->values());
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
            'office_type' => 'required|in:default,hospital,school,ict_office',
        ], [], $this->attributes());

        $data = $request->all();
        $geoArea = $geoService->getById((int) $data['geo_area_id']);
        if ($geoArea) {
            // Safely resolve parents names, protecting against null `hid` values
            $geoNames = $geoService->resolveParentNames($geoArea->hid ?? '');
            $data['city'] = $geoNames['city'];
            $data['state'] = $geoNames['state'];
        }

        $location = $service->provisionOffice($data, auth()->id());

        return redirect()->route('gov.org.hub.show', $location->id)
            ->with('success', __('organization_labels::office.created', ['name' => $location->name]));
    }

    public function assignAdmin(Request $request, OfficeProvisioningService $service)
    {
        $this->checkIctOfficerAccess();

        $request->validate([
            'location_id' => 'required|integer',
            'office_admin_id' => 'nullable|integer',
        ]);

        $service->assignOfficeAdmin($request->location_id, $request->office_admin_id ?: null, auth()->id());

        return redirect()->back()->with('success', __('organization_labels::orglabel.provisioning_controller_admin_updated'));
    }

    /** Field names in validation messages, in the user's language. */
    public static function attributes(): array
    {
        return [
            'name' => __('organization_labels::office.name'),
            'geo_area_id' => __('organization_labels::office.area'),
            'company_id' => __('organization_labels::office.ministry'),
            'parent_id' => __('organization_labels::office.parent'),
            'office_type' => __('organization_labels::orglabel.office_type_label'),
            'existing_location_id' => __('organization_labels::office.existing_location'),
            'user_id' => __('organization_labels::office.person'),
            'reason' => __('organization_labels::office.reason'),
        ];
    }

    /* ==========================================
       AREA ICT OFFICERS
       ========================================== */

    public function jurisdictionsIndex()
    {
        abort_unless(auth()->user()->isSuperUser(), 403);

        $jurisdictions = IctJurisdiction::with(['user.location', 'geoArea'])->get();
        $users = User::orderBy('first_name')->get();
        $ministryCount = \GovStore\Organization\Models\CompanyAdmin::count();

        return view('govorg::provisioning.jurisdictions', compact('jurisdictions', 'users', 'ministryCount'));
    }

    public function jurisdictionsStore(Request $request)
    {
        abort_unless(auth()->user()->isSuperUser(), 403);

        $request->validate([
            'user_id' => 'required|integer|exists:users,id,deleted_at,NULL',
            'geo_area_id' => 'required|integer|exists:gov_geo_areas,GeoAreaId',
        ], [], $this->attributes() + ['geo_area_id' => __('organization_labels::office.area_manage')]);

        app(\GovStore\Organization\Services\OfficeAdministration::class)->actor((int) $request->user_id);
        $jurisdiction = IctJurisdiction::updateOrCreate(
            ['user_id' => $request->user_id],
            ['geo_area_id' => $request->geo_area_id]
        );
        $jurisdiction->load(['user', 'geoArea']);

        return redirect()->route('gov.org.jurisdictions.index')->with('success', __('organization_labels::office.granted_ict', [
            'name' => $jurisdiction->user?->present()->fullName ?? '',
            'area' => $jurisdiction->geoArea?->en_name ?? '',
        ]));
    }

    public function jurisdictionsDestroy($id)
    {
        abort_unless(auth()->user()->isSuperUser(), 403);

        $jurisdiction = IctJurisdiction::with('user')->findOrFail($id);
        $restore = ['route' => 'gov.org.jurisdictions.store', 'fields' => ['user_id' => $jurisdiction->user_id, 'geo_area_id' => $jurisdiction->geo_area_id]];
        $name = $jurisdiction->user?->present()->fullName ?? '';
        $jurisdiction->delete();

        return redirect()->route('gov.org.jurisdictions.index')
            ->with('success', __('organization_labels::office.removed_ict', ['name' => $name]))
            ->with('gov_org_restore', $restore);
    }

    /**
     * Area search for office forms, limited to the officer's jurisdiction, with each result's parent path.
     */
    public function geoSearch(Request $request, GeoAreaService $service)
    {
        $this->checkIctOfficerAccess();
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $hid = null;
        if (! auth()->user()->isSuperUser()) {
            $hid = IctJurisdiction::with('geoArea')->where('user_id', auth()->id())->first()?->geoArea?->hid;
            abort_unless($hid, 403);
        }
        $bangla = str_starts_with(strtolower(app()->getLocale()), 'bn');
        $results = $service->search($data['q'] ?? '', [], $hid);

        // Ancestors are the hid prefixes of each result ('/25/' and '/25/26/' for '/25/26/72/').
        $prefixes = $results->flatMap(function ($geo) {
            $parts = array_values(array_filter(explode('/', (string) $geo->hid)));
            array_pop($parts);
            $paths = [];
            foreach ($parts as $i => $part) {
                $paths[] = '/'.implode('/', array_slice($parts, 0, $i + 1)).'/';
            }

            return $paths;
        })->unique()->values();
        $ancestors = $prefixes->isEmpty() ? collect() : GeoArea::whereIn('hid', $prefixes)->get()->keyBy('hid');

        return response()->json($results->map(function ($geo) use ($bangla, $ancestors) {
            $parts = array_values(array_filter(explode('/', (string) $geo->hid)));
            array_pop($parts);
            $path = [];
            foreach ($parts as $i => $part) {
                $ancestor = $ancestors->get('/'.implode('/', array_slice($parts, 0, $i + 1)).'/');
                if ($ancestor) {
                    $path[] = $bangla ? $ancestor->bn_name : $ancestor->en_name;
                }
            }
            $type = GeoAreaService::canonicalType($geo->geo_type);

            return [
                'id' => $geo->getKey(), 'text' => $bangla ? $geo->bn_name : $geo->en_name,
                'en_name' => $geo->en_name, 'bn_name' => $geo->bn_name,
                'type' => __('geo_areas::types.'.$type), 'path' => $path,
            ];
        })->values());
    }
}
