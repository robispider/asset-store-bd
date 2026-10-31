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

    public function preflight(?ExperimentRun $run = null): void
    {
        if ($run && ScenarioManifest::current($run)) {
            app(ScenarioManifest::class)->preflight();
        }
        foreach (['gov_geo_areas', 'gov_office_memberships', 'gov_office_responsibilities', 'gov_location_profiles', 'gov_catalog_nodes',
            'gov_model_metadata_states', 'gov_metadata_field_mappings', 'gov_profile_assignments', 'gov_documents',
            'gov_store_ledger_openings', 'gov_store_document_sequences', 'custom_service_requests', 'gov_tracking_codes'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Required package schema is missing: '.$table.'. Apply that package\'s existing setup first.');
            }
        }
    }

    public function verify(ExperimentRun $run, User $actor, bool $captureOwnership = true): array
    {
        // Capture package query-builder inserts by their strictly owned domain relationships.
        $preview = $this->wiper->preview($run, 'dataset', $actor);
        if ($captureOwnership) {
            $registered = [];
            foreach (DB::table('gov_experiment_records')->where('run_id', $run->id)->get(['table_name', 'record_key']) as $record) {
                $registered[$record->table_name][$record->record_key] = true;
            }
            foreach ($preview['records'] as $table => $keys) {
                foreach (array_keys($keys) as $key) {
                    if (! isset($registered[$table][$key])) {
                        $row = $this->records->queryKey($table, $key)->first();
                        if ($row) {
                            $this->records->record($run, $table, $row);
                        }
                    }
                }
            }
            unset($registered);
        }
        $counts = $this->records->counts($run);
        $size = $run->report['profile_counts'] ?? config('govstore-experiments.profiles.'.$run->profile);
        $size['assets'] += $run->report['tag_rehearsal']['issued_assets'] ?? 0;
        if (ScenarioManifest::current($run)) {
            $targets = $run->report['request_recipe_targets'] ?? app(ScenarioManifest::class)->requestTargets();
            $size['requests'] += count($targets['asset_model']) + count($targets['consumable']) + count($targets['accessory']) + $targets['mixed_policy_requests'];
        }
        $expectedReady = $size['offices'] - ($run->profile === 'quick' ? 0 : 4);
        // Older resumed datasets retain their immutable component receipts and existing extra documents.
        $retainedDocuments = collect($run->report['units'] ?? [])->filter(fn ($id, $key) => $id
            && (str_starts_with($key, 'opening-components-') || str_starts_with($key, 'extra-document-')))->count();
        $size['documents'] = max($size['documents'], $expectedReady * 3 + $retainedDocuments);
        $ownershipBlockers = array_filter($preview['blockers'], fn ($blocker) => ! (isset($run->report['tag_rehearsal'])
            && $blocker === 'gov_asset_registrations: issued asset tags are permanent'));
        $checks = ['counts' => true, 'ownership' => ! $ownershipBlockers, 'home_offices' => true, 'office_roles' => true,
            'geography' => true, 'requests' => true, 'stock' => true, 'ledger_openings' => true,
            'tracking' => true, 'catalog' => true, 'boundaries' => true];
        foreach (['locations' => 'offices', 'users' => 'users', 'assets' => 'assets', 'consumables' => 'consumables', 'accessories' => 'accessories',
            'components' => 'components', 'models' => 'models', 'categories' => 'categories', 'licenses' => 'licenses', 'gov_documents' => 'documents',
            'custom_service_requests' => 'requests', 'draft_baskets' => 'baskets', 'gov_initiatives' => 'initiatives', 'gov_tracking_codes' => 'codes', 'maintenances' => 'maintenance'] as $table => $key) {
            $derived = ScenarioManifest::current($run) && in_array($table, ['gov_documents', 'custom_service_requests', 'consumables'], true);
            if ($derived ? ($counts[$table] ?? 0) < $size[$key] : ($counts[$table] ?? 0) !== $size[$key]) {
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
            $opening = DB::table('gov_store_ledger_openings')->where('location_id', $office->id)->first();
            $document = $opening ? DB::table('gov_documents')->where('id', $opening->document_id)->first() : null;
            if (! $opening || ! $document || $document->type !== 'opening' || $document->status !== 'POSTED'
                || (int) $document->location_id !== (int) $office->id || (int) $document->company_id !== (int) $office->company_id
                || (int) $document->posted_by !== (int) $opening->opened_by
                || ! in_array($opening->opened_by, $users->pluck('id')->all())
                || ! in_array($document->id, $this->records->ids($run, 'gov_documents'), true)) {
                $checks['ledger_openings'] = false;
            }
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
                $class = ['consumables' => Consumable::class, 'accessories' => Accessory::class, 'components' => Component::class][$table];
                $native = $class::withoutGlobalScopes()->find($item->id);
                if ($balance < 0 || ! $native || (int) $native->numRemaining() !== (int) $balance || ! isset($offices[$item->location_id]) || (int) $item->company_id !== (int) $offices[$item->location_id]->company_id) {
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

        if (ScenarioManifest::current($run)) {
            $checks = array_merge($checks, app(WorkflowVerifier::class)->verify($run, $actor));
        }

        return ['passed' => ! in_array(false, $checks, true), 'checks' => $checks, 'counts' => $counts,
            'version' => ScenarioManifest::VERSION, 'verified_at' => now()->toIso8601String(), 'stale' => ! ScenarioManifest::current($run),
            'wipe_blockers' => $preview['blockers'],
            'limitations' => ['limit_current_packages', 'limit_laptop', 'limit_metadata', 'limit_tag_rehearsal', 'limit_serial_ledger']];
    }
}
