<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Services\TrackingMonitorService;
use GovStore\Tracking\Services\TrackingOfficeAccess;
use Illuminate\Support\Facades\DB;

final class TrackingOfficeController extends Controller
{
    public function __construct(private TrackingOfficeAccess $access, private TrackingMonitorService $monitor) {}

    public function index()
    {
        abort_unless(config('tracking.office_directory_enabled'), 404);
        $ids = $this->access->directoryIds();
        $locations = DB::table('locations')->whereNull('deleted_at')->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('name')->get(['id', 'name', 'company_id']);
        $profiles = DB::table('gov_location_profiles')->whereIn('location_id', $locations->pluck('id'))->get(['location_id', 'geo_area_id', 'office_type'])->keyBy('location_id');
        $programmes = $this->access->programmes()->orderBy('id')->get()->map(fn ($i) => $this->monitor->programme($i));
        $offices = [];
        foreach ($locations as $location) {
            $slices = [];
            $programmeIds = [];
            foreach ($programmes as $p) {
                foreach ($p['offices'] as $office) {
                    if ($office['id'] == $location->id) {
                        $slices = array_merge($slices, $office['codes']);
                        if (collect($office['codes'])->contains(fn ($s) => $s['code']['status'] === 'ACTIVE')) {
                            $programmeIds[] = $p['initiative']->id;
                        }
                    }
                }
            }
            $offices[] = ['id' => (int) $location->id, 'name' => $location->name, 'name_bn' => $location->name_bn ?? '',
                'no_area' => ! ($profiles->get($location->id)->geo_area_id ?? null), 'level' => $profiles->get($location->id)->office_type ?? '',
                'programme_count' => count(array_unique($programmeIds)), 'code_count' => count(array_filter($slices, fn ($s) => $s['code']['status'] === 'ACTIVE'))]
                + $this->monitor->officeSummary($slices);
        }
        usort($offices, fn ($a, $b) => ($b['over'] <=> $a['over']) ?: (($a['received'] == 0 && $a['allocated'] > 0 ? 0 : 1) <=> ($b['received'] == 0 && $b['allocated'] > 0 ? 0 : 1)) ?: ($b['received'] <=> $a['received']));

        return view('govtracking::offices.index', compact('offices', 'programmes'));
    }

    public function all(int $location)
    {
        abort_unless(config('tracking.office_directory_enabled'), 404);
        $programmes = $this->access->programmes()->orderBy('id')->get()
            ->filter(fn ($i) => $this->access->connected($i, $location));
        if (! $this->access->officeKey($location)) {
            if ($first = $programmes->first()) {
                return redirect()->route('gov.tracking.initiatives.offices.show', [$first->id, $location])
                    ->with('tracking_scope_note', __('govtracking::general.monitor.fallback'));
            }
            $ids = $this->access->directoryIds() ?? [];
            $ownOffices = DB::table('locations')->whereIn('id', $ids)->whereNull('deleted_at')->get(['id', 'name']);

            return response()->view('govtracking::offices.denied', compact('ownOffices'), 403);
        }

        return $this->render($location, $programmes->map(fn ($i) => $this->monitor->programme($i))->values()->all());
    }

    public function programme(Initiative $initiative, int $location)
    {
        $this->access->office($initiative, $location);

        return $this->render($location, [$this->monitor->programme($initiative)], $initiative);
    }

    public function code(Initiative $initiative, TrackingCode $trackingCode, int $location)
    {
        $this->access->office($initiative, $location, $trackingCode);

        return $this->render($location, [$this->monitor->programme($initiative)], $initiative, $trackingCode);
    }

    private function render(int $location, array $programmes, ?Initiative $initiative = null, ?TrackingCode $trackingCode = null)
    {
        // Fetch the office only after establishing programme or office access.
        $office = DB::table('locations')->where('id', $location)->whereNull('deleted_at')->first(['id', 'name', 'company_id']);
        abort_unless($office, 404);
        $profile = DB::table('gov_location_profiles')->where('location_id', $location)->first(['location_id', 'geo_area_id', 'office_type', 'office_admin_id', 'geo_area_verified_at']);
        $geo = ($profile->geo_area_id ?? null) ? DB::table('gov_geo_areas')->where('GeoAreaId', $profile->geo_area_id)->first() : null;
        $slices = [];
        foreach ($programmes as $p) {
            foreach ($p['codes'] as $c) {
                if ($trackingCode && $c['id'] !== $trackingCode->id) {
                    continue;
                }
                $slice = $this->monitor->slice($c, $location);
                if ($slice['touch']) {
                    $slices[] = $slice + ['initiative' => $p['initiative']];
                }
            }
        }
        $summary = $this->monitor->officeSummary($slices);
        $canInventory = $this->access->inventoryKey($location, (int) $office->company_id);
        $assets = collect();
        if ($canInventory && $slices) {
            $codeIds = array_column(array_column($slices, 'code'), 'id');
            $ids = DB::table('gov_tracking_associations')->whereIn('tracking_code_id', $codeIds)->where('location_id', $location)
                ->where('status', 'ACTIVE')->where('associatable_type', Asset::class)->pluck('associatable_id');
            $assets = DB::table('assets')->leftJoin('status_labels', 'status_labels.id', '=', 'assets.status_id')
                ->leftJoin('models', 'models.id', '=', 'assets.model_id')->leftJoin('categories', 'categories.id', '=', 'models.category_id')
                ->whereIn('assets.id', $ids)->where('assets.location_id', $location)->where('assets.company_id', $office->company_id)
                ->whereNull('assets.deleted_at')->get(['assets.id', 'assets.asset_tag', 'assets.assigned_to', 'status_labels.archived', 'status_labels.deployable', 'models.category_id', 'categories.name as category_name']);
        }
        $options = [];
        if (config('tracking.office_directory_enabled')) {
            $options[] = ['label' => __('govtracking::general.monitor.all_programmes'),
                'locked' => ! $this->access->officeKey($location), 'reason' => __('govtracking::general.monitor.office_scope_required'),
                'href' => route('gov.tracking.offices.show', $location), 'active' => ! $initiative];
        }
        // Only accessible programmes supply level options. No hidden-programme counts or labels.
        $visible = $this->access->programmes()->orderBy('id')->get()->filter(fn ($i) => $this->access->connected($i, $location));
        foreach ($visible as $i) {
            $options[] = ['label' => $i->title, 'href' => route('gov.tracking.initiatives.offices.show', [$i->id, $location]), 'active' => $initiative?->id === $i->id && ! $trackingCode];
        }
        $reasons = [__('govtracking::general.monitor.programme_required'), $canInventory ? __('govtracking::general.monitor.inventory_allowed') : __('govtracking::general.monitor.inventory_locked')];
        if ($this->access->officeKey($location)) {
            $reasons[] = __('govtracking::general.monitor.office_allowed');
        }
        foreach ($programmes as $p) {
            $reasons[] = __('govtracking::general.monitor.programme_allowed', ['programme' => $p['initiative']->title]);
        }
        $people = DB::table('gov_office_responsibilities')->join('users', 'users.id', '=', 'gov_office_responsibilities.user_id')
            ->where('gov_office_responsibilities.location_id', $location)->whereNull('users.deleted_at')
            ->get(['users.first_name', 'users.last_name', 'gov_office_responsibilities.role_slug']);
        $officeAdmin = ($profile->office_admin_id ?? null) ? DB::table('users')->where('id', $profile->office_admin_id)->whereNull('deleted_at')->first(['first_name', 'last_name']) : null;

        return view('govtracking::offices.show', compact('office', 'profile', 'geo', 'slices', 'summary', 'programmes', 'initiative', 'trackingCode', 'canInventory', 'assets', 'options', 'reasons', 'people', 'officeAdmin'));
    }
}
