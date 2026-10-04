<?php

namespace GovStore\Experimentation\Services;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Services\ScopeValidatorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ExperimentVerifier
{
    public function __construct(private RecordRegistry $records, private ExperimentWiper $wiper) {}

    public function preflight(): void
    {
        foreach (['gov_geo_areas', 'gov_office_memberships', 'gov_office_responsibilities', 'gov_location_profiles', 'gov_catalog_nodes',
            'gov_model_metadata_states', 'gov_metadata_field_mappings', 'gov_profile_assignments', 'gov_documents', 'custom_service_requests', 'gov_tracking_codes'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Required package schema is missing: '.$table.'. Apply that package\'s existing setup first.');
            }
        }
    }

    public function verify(ExperimentRun $run, User $actor): array
    {
        // Capture package query-builder inserts by their strictly owned domain relationships.
        $preview = $this->wiper->preview($run, 'dataset', $actor);
        foreach ($preview['records'] as $table => $keys) {
            foreach (array_keys($keys) as $key) {
                $row = $this->records->queryKey($table, $key)->first();
                if ($row) {
                    $this->records->record($run, $table, $row);
                }
            }
        }
        $counts = $this->records->counts($run);
        $size = $run->report['profile_counts'] ?? config('govstore-experiments.profiles.'.$run->profile);
        $checks = ['counts' => true, 'ownership' => ! $preview['blockers'], 'home_offices' => true, 'office_roles' => true,
            'geography' => true, 'requests' => true, 'stock' => true, 'tracking' => true, 'catalog' => true, 'boundaries' => true];
        foreach (['locations' => 'offices', 'users' => 'users', 'assets' => 'assets', 'consumables' => 'consumables', 'accessories' => 'accessories',
            'components' => 'components', 'models' => 'models', 'categories' => 'categories', 'licenses' => 'licenses', 'gov_documents' => 'documents',
            'custom_service_requests' => 'requests', 'draft_baskets' => 'baskets', 'gov_initiatives' => 'initiatives', 'gov_tracking_codes' => 'codes', 'maintenances' => 'maintenance'] as $table => $key) {
            if (($counts[$table] ?? 0) !== $size[$key]) {
                $checks['counts'] = false;
            }
        }
        $users = DB::table('users')->whereIn('id', $this->records->ids($run, 'users'))->get();
        $offices = DB::table('locations')->whereIn('id', $this->records->ids($run, 'locations'))->get()->keyBy('id');
        foreach ($users as $user) {
            if ($user->location_id) {
                if (! isset($offices[$user->location_id]) || (int) $user->company_id !== (int) $offices[$user->location_id]->company_id
                    || ! DB::table('gov_office_memberships')->where('user_id', $user->id)->where('location_id', $user->location_id)->where('is_home_office', true)->exists()) {
                    $checks['home_offices'] = false;
                }
            }
        }
        $expectedReady = $size['offices'] - ($run->profile === 'quick' ? 0 : 4);
        $ready = 0;
        foreach ($offices as $office) {
            $profile = DB::table('gov_location_profiles')->where('location_id', $office->id)->first();
            $geo = $profile ? DB::table('gov_geo_areas')->where('GeoAreaId', $profile->geo_area_id)->first() : null;
            if (! $geo || ! $geo->hid || ! preg_match('~/\d+/~', $geo->hid)) {
                $checks['geography'] = false;
            }
            $roles = DB::table('gov_office_responsibilities')->where('location_id', $office->id)->get();
            foreach ($roles as $role) {
                if (! DB::table('gov_office_memberships')->where('user_id', $role->user_id)->where('location_id', $office->id)->exists()) {
                    $checks['office_roles'] = false;
                }
            }
            if ($profile?->office_admin_id && $roles->pluck('role_slug')->unique()->count() === 3) {
                $ready++;
            }
        }
        $checks['office_roles'] = $checks['office_roles'] && $ready === $expectedReady;
        foreach ($offices->take($expectedReady) as $office) {
            foreach (['consumables' => 12, 'accessories' => 8, 'components' => 4] as $table => $minimum) {
                if (DB::table($table)->whereIn('id', $this->records->ids($run, $table))->where('location_id', $office->id)->where('qty', '>', 0)->count() < $minimum) {
                    $checks['catalog'] = false;
                }
            }
            $stockNames = DB::table('assets')->join('models', 'models.id', '=', 'assets.model_id')->whereIn('assets.id', $this->records->ids($run, 'assets'))
                ->where('assets.location_id', $office->id)->where('assets.requestable', 1)->whereNull('assets.assigned_to')->pluck('models.name')->implode(' ');
            if (! str_contains($stockNames, 'Desktop Computers') || ! str_contains($stockNames, 'Office Chairs')) {
                $checks['catalog'] = false;
            }
            $member = User::withoutGlobalScopes()->whereIn('id', $this->records->ids($run, 'users'))->where('location_id', $office->id)->first();
            if (! $member) {
                $checks['boundaries'] = false;

                continue;
            }
            app(ActorContext::class)->run($member, Location::withoutGlobalScopes()->findOrFail($office->id), function () use ($run, $office, &$checks) {
                foreach (['assets' => Asset::class, 'consumables' => Consumable::class, 'accessories' => Accessory::class, 'components' => Component::class] as $table => $class) {
                    if ($class::whereIn('id', $this->records->ids($run, $table))->where('location_id', '!=', $office->id)->exists()) {
                        $checks['boundaries'] = false;
                    }
                }
            });
        }
        foreach (DB::table('custom_service_requests')->whereIn('id', $this->records->ids($run, 'custom_service_requests'))->get() as $request) {
            $requester = $users->firstWhere('id', $request->requested_by);
            if (! $requester || (int) $requester->location_id !== (int) $request->office_id) {
                $checks['requests'] = false;
            }
        }
        foreach (DB::table('assets')->whereIn('id', $this->records->ids($run, 'assets'))->get() as $asset) {
            if (! isset($offices[$asset->location_id]) || (int) $asset->company_id !== (int) $offices[$asset->location_id]->company_id) {
                $checks['stock'] = false;
            }
        }
        foreach (['consumables', 'accessories', 'components'] as $table) {
            foreach (DB::table($table)->whereIn('id', $this->records->ids($run, $table))->get() as $item) {
                $movements = DB::table('gov_inventory_movements')->where('stockable_type', ['consumables' => 'consumable', 'accessories' => 'accessory', 'components' => 'component'][$table])->where('stockable_id', $item->id)->get();
                $balance = $movements->where('movement_type', 'IN')->sum('quantity') - $movements->where('movement_type', 'OUT')->sum('quantity');
                if ($balance < 0 || (int) $item->qty !== (int) $balance || ! isset($offices[$item->location_id]) || (int) $item->company_id !== (int) $offices[$item->location_id]->company_id) {
                    $checks['stock'] = false;
                }
            }
        }
        foreach ($this->records->ids($run, 'gov_tracking_codes') as $id) {
            $code = TrackingCode::with('initiative')->findOrFail($id);
            foreach (DB::table('gov_tracking_allocations')->join('gov_tracking_targets', 'gov_tracking_targets.id', '=', 'gov_tracking_allocations.target_id')->where('tracking_code_id', $id)->select('gov_tracking_allocations.*')->get() as $allocation) {
                if (! app(ScopeValidatorService::class)->validateExecutionScope($code, $allocation->location_id)['is_valid']) {
                    $checks['tracking'] = false;
                }
            }
            $allowed = DB::table('gov_tracking_scopes')->where('tracking_code_id', $id)->where('dimension', 'PARTICIPANTS')->where('target_type', 'SpecificLocations')->pluck('target_id')->all();
            $outside = $offices->first(fn ($office) => ! in_array($office->id, $allowed));
            if ($outside && app(ScopeValidatorService::class)->validateExecutionScope($code, $outside->id)['is_valid']) {
                $checks['boundaries'] = false;
            }
        }

        return ['passed' => ! in_array(false, $checks, true), 'checks' => $checks, 'counts' => $counts,
            'limitations' => ['limit_current_packages', 'limit_laptop', 'limit_bulk_fulfillment', 'limit_metadata', 'limit_native_audit', 'limit_committee']];
    }
}
