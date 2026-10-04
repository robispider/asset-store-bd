<?php

namespace GovStore\Experimentation\Services;

use App\Models\User;
use GovStore\Experimentation\Models\ExperimentRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ExperimentWiper
{
    private const PRESERVED = [
        'migrations', 'settings', 'permission_groups', 'permissions', 'custom_fields', 'gov_metadata_field_mappings',
        'gov_geo_areas', 'gov_catalog_nodes', 'gov_catalog_definitions', 'gov_capabilities', 'gov_requirement_definitions',
        'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_resets', 'password_reset_tokens',
        'oauth_clients', 'oauth_personal_access_clients', 'oauth_scopes', 'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring',
        'gov_experiment_runs', 'gov_experiment_records', 'gov_experiment_actions', 'gov_experiment_jobs', 'gov_experiment_failed_jobs',
    ];

    private const COLUMNS = [
        'company_id' => 'companies', 'owner_company_id' => 'companies', 'created_by_company_id' => 'companies',
        'location_id' => 'locations', 'rtd_location_id' => 'locations', 'delivery_location_id' => 'locations',
        'user_id' => 'users', 'created_by' => 'users', 'created_by_user_id' => 'users', 'performed_by' => 'users',
        'office_admin_id' => 'users', 'requested_by' => 'users', 'approved_by' => 'users', 'assigned_by' => 'users',
        'approved_by_user_id' => 'users', 'outgoing_user_id' => 'users', 'incoming_user_id' => 'users', 'creator_user_id' => 'users',
        'uploaded_by' => 'users', 'actor_id' => 'users', 'geo_area_verified_by' => 'users',
        'manager_id' => 'users', 'department_id' => 'departments', 'category_id' => 'categories', 'model_id' => 'models',
        'manufacturer_id' => 'manufacturers', 'supplier_id' => 'suppliers', 'status_id' => 'status_labels',
        'asset_id' => 'assets', 'license_id' => 'licenses', 'accessory_id' => 'accessories', 'consumable_id' => 'consumables',
        'component_id' => 'components', 'profile_id' => 'gov_profiles', 'initiative_id' => 'gov_initiatives',
        'tracking_code_id' => 'gov_tracking_codes', 'funding_type_id' => 'gov_funding_types',
    ];

    // Only these children can become owned through their parent; other links block a scoped wipe.
    private const CHILDREN = [
        'gov_location_profiles' => ['location_id'], 'gov_organization_activity_logs' => ['location_id'],
        'gov_document_items' => ['document_id'], 'gov_document_item_meta' => ['document_item_id'],
        'gov_document_references' => ['document_id'], 'gov_document_attachments' => ['document_id'],
        'gov_document_timelines' => ['document_id'], 'gov_asset_registrations' => ['intake_item_id', 'asset_id'],
        'gov_goods_receipt_items' => ['goods_receipt_id'], 'gov_goods_issue_items' => ['goods_issue_id'],
        'gov_stock_adjustment_items' => ['stock_adjustment_id'], 'license_seats' => ['license_id'],
        'gov_profile_capabilities' => ['profile_id'], 'gov_profile_assignments' => ['profile_id'],
        'gov_model_metadata_states' => ['model_id'], 'custom_field_custom_fieldset' => ['custom_fieldset_id'],
        'custom_service_request_items' => ['request_id'], 'custom_service_request_events' => ['request_id'],
        'draft_basket_items' => ['basket_id'], 'gov_catalog_collection_nodes' => ['collection_id'],
        'gov_tracking_codes' => ['initiative_id'], 'gov_tracking_operation_units' => ['initiative_id'],
        'gov_tracking_targets' => ['tracking_code_id'], 'gov_tracking_allocations' => ['target_id'],
        'gov_tracking_scopes' => ['tracking_code_id'], 'gov_tracking_associations' => ['tracking_code_id'],
        'gov_tracking_fact_deliveries' => ['initiative_id', 'tracking_code_id'],
        'gov_tracking_timeline' => ['initiative_id'], 'gov_tracking_projection_caches' => ['initiative_id'],
        'gov_tracking_documents' => ['tracking_code_id'], 'users_groups' => ['user_id'], 'company_user' => ['user_id'],
        'gov_employee_verification_tokens' => ['user_id'], 'gov_user_onboardings' => ['user_id'],
        'accessories_checkout' => ['accessory_id'], 'consumables_users' => ['consumable_id'],
        'components_assets' => ['component_id'],
    ];

    // Supplement physical foreign keys with known legacy/polymorphic relationships.
    private const RELATIONS = [
        ['models', 'fieldset_id', 'custom_fieldsets', 'id'],
        ['assets', 'model_id', 'models', 'id'], ['assets', 'assigned_to', 'users', 'id', 'assigned_type', User::class],
        ['action_logs', 'item_id', 'assets', 'id', 'item_type', 'App\\Models\\Asset'],
        ['action_logs', 'item_id', 'consumables', 'id', 'item_type', 'App\\Models\\Consumable'],
        ['action_logs', 'item_id', 'accessories', 'id', 'item_type', 'App\\Models\\Accessory'],
        ['action_logs', 'item_id', 'components', 'id', 'item_type', 'App\\Models\\Component'],
        ['action_logs', 'item_id', 'licenses', 'id', 'item_type', 'App\\Models\\License'],
        ['action_logs', 'item_id', 'users', 'id', 'item_type', User::class],
        ['action_logs', 'item_id', 'locations', 'id', 'item_type', 'App\\Models\\Location'],
        ['gov_inventory_movements', 'stockable_id', 'consumables', 'id', 'stockable_type', 'consumable'],
        ['gov_inventory_movements', 'stockable_id', 'accessories', 'id', 'stockable_type', 'accessory'],
        ['gov_inventory_movements', 'stockable_id', 'components', 'id', 'stockable_type', 'component'],
        ['gov_inventory_movements', 'document_id', 'gov_documents', 'id', 'document_type', 'GovStore\\StoreOperations\\Models\\Document'],
        ['gov_inventory_movements', 'document_id', 'gov_goods_issues', 'id', 'document_type', 'GovStore\\StoreOperations\\Models\\GoodsIssue'],
        ['gov_goods_issues', 'reference_id', 'custom_service_requests', 'id', 'reference_type', 'GovStore\\CustomRequests\\Models\\Request'],
        ['gov_tenant_scope_mappings', 'scope_id', 'companies', 'id', 'scope_type', 'company'],
        ['gov_tenant_scope_mappings', 'scope_id', 'locations', 'id', 'scope_type', 'location'],
        ['gov_approval_policies', 'target_id', 'categories', 'id', 'target_type', 'category'],
        ['gov_category_governance', 'category_id', 'categories', 'id'],
        ['gov_catalog_snipe_mappings', 'category_id', 'categories', 'id'],
        ['custom_service_requests', 'requested_by', 'users', 'id'],
        ['draft_baskets', 'user_id', 'users', 'id'],
        ['draft_basket_items', 'basket_id', 'draft_baskets', 'id'],
        ['custom_service_request_items', 'request_id', 'custom_service_requests', 'id'],
        ['custom_service_request_events', 'request_id', 'custom_service_requests', 'id'],
        ['gov_documents', 'location_id', 'locations', 'id'],
        ['gov_goods_issues', 'location_id', 'locations', 'id'],
        ['maintenances', 'asset_id', 'assets', 'id'],
    ];

    public function __construct(private RecordRegistry $registry, private ExperimentAccess $access) {}

    public function preview(ExperimentRun $run, string $scope, User $actor): array
    {
        $this->access->authorize($actor);
        if (! in_array($scope, ['dataset', 'database'], true)) {
            throw new RuntimeException('Invalid wipe scope.');
        }
        if ($scope === 'database') {
            $this->access->databaseReset();
        }
        $tables = array_column(Schema::getTables(DB::connection()->getDatabaseName()), 'name');
        $records = $scope === 'dataset' ? $this->registry->records($run) : $this->databaseRecords($tables, $actor);
        $relations = $this->relations($tables);
        $blockers = [];
        if ($scope === 'database') {
            foreach ($tables as $table) {
                if (in_array($table, RecordRegistry::TABLES, true) || in_array($table, self::PRESERVED, true)) {
                    continue;
                }
                if (DB::table($table)->exists()) {
                    $blockers[$table] = 'Reset needs an explicit adapter for populated table: '.$table;
                }
            }
        }
        // Closure captures legitimate children created by package listeners/manual experiment workflows.
        do {
            $changed = false;
            unset($rows);
            $rows = $this->registry->rows($records);
            foreach ($relations as $relation) {
                [$child, $column, $parent, $parentColumn] = $relation;
                if (empty($records[$parent])) {
                    continue;
                }
                $parentValues = [];
                foreach ($rows[$parent] ?? [] as $row) {
                    if ($row) {
                        $parentValues[] = $row->{$parentColumn};
                    }
                }
                foreach (array_chunk(array_unique($parentValues), 500) as $values) {
                    $query = DB::table($child)->whereIn($column, $values);
                    if (isset($relation[4])) {
                        $query->where($relation[4], $relation[5]);
                    }
                    foreach ($query->get() as $row) {
                        try {
                            $key = $this->registry->key($child, $row);
                        } catch (RuntimeException $e) {
                            $blockers[$child.'.'.$column] = $child.': unregistered dependent rows';

                            continue;
                        }
                        if (isset($records[$child][$key])) {
                            continue;
                        }
                        $isChild = in_array($column, self::CHILDREN[$child] ?? [], true);
                        $isActivity = in_array($child, ['action_logs', 'gov_inventory_movements', 'gov_tenant_scope_mappings', 'gov_approval_policies', 'gov_category_governance', 'gov_catalog_snipe_mappings'], true);
                        if ($scope === 'database' && (($child === 'users' && (int) $row->id === $actor->id)
                            || (in_array($child, ['company_user', 'users_groups'], true) && (int) $row->user_id === $actor->id))) {
                            continue;
                        }
                        if ($isChild || $isActivity) {
                            $records[$child][$key] = true;
                            $changed = true;
                        } else {
                            $blockers[$child.'.'.$key] = $child.': an unrelated record references this experiment';
                        }
                    }
                }
            }
        } while ($changed);
        // A row may first appear as a dependency and become owned through another parent later.
        foreach ($records as $table => $keys) {
            foreach (array_keys($keys) as $key) {
                unset($blockers[$table.'.'.$key]);
            }
        }
        if ($scope === 'dataset') {
            foreach ($relations as $relation) {
                [$child, $column, $parent, $parentColumn] = $relation;
                if (! in_array($child, ['gov_profile_assignments', 'gov_tracking_associations'], true) || ! isset($relation[4])) {
                    continue;
                }
                $ownedTargets = array_map(fn ($row) => (string) $row->{$parentColumn}, $rows[$parent] ?? []);
                foreach ($rows[$child] ?? [] as $key => $row) {
                    if ($row->{$relation[4]} === $relation[5] && ! in_array((string) $row->{$column}, $ownedTargets, true)) {
                        $blockers[$child.'.'.$key] = $child.': an experiment relationship targets an unrelated record';
                    }
                }
            }
        }
        // A preserved global standard/policy must never be removed by closure.
        foreach ($records as $table => $keys) {
            if (! in_array($table, RecordRegistry::TABLES, true)) {
                $blockers[$table] = 'Unknown dependency: '.$table;
            }
        }
        $counts = array_map('count', $records);
        ksort($counts);
        $hashRecords = $records;
        ksort($hashRecords);
        foreach ($hashRecords as &$keys) {
            ksort($keys);
        }
        unset($keys);
        unset($rows);
        $hash = hash_init('sha256');
        hash_update($hash, json_encode([$run->id, $scope, DB::connection()->getDatabaseName(), array_values($blockers)], JSON_THROW_ON_ERROR));
        foreach ($hashRecords as $table => $keys) {
            hash_update($hash, json_encode($table, JSON_THROW_ON_ERROR));
            foreach (array_chunk(array_keys($keys), 250) as $chunk) {
                $batch = $this->registry->rows([$table => array_fill_keys($chunk, true)]);
                foreach ($chunk as $key) {
                    hash_update($hash, json_encode([$key, $batch[$table][$key] ?? null], JSON_THROW_ON_ERROR));
                }
                unset($batch);
            }
        }

        return ['scope' => $scope, 'counts' => $counts, 'records' => $records, 'relations' => $relations,
            'blockers' => array_values($blockers), 'confirmation' => $scope === 'database' ? config('govstore-experiments.installation') : $run->label,
            'fingerprint' => hash_final($hash)];
    }

    private function databaseRecords(array $tables, User $actor): array
    {
        $records = [];
        foreach (RecordRegistry::TABLES as $table) {
            if (! in_array($table, $tables, true)) {
                continue;
            }
            // System policy definitions and shared master references survive reset.
            if (in_array($table, ['gov_profiles', 'gov_profile_capabilities', 'gov_profile_assignments', 'custom_fieldsets', 'custom_field_custom_fieldset', 'gov_ministries_directory', 'gov_catalog_enrichments', 'gov_catalog_synonyms'], true)) {
                continue;
            }
            $query = DB::table($table);
            if ($table === 'users') {
                $query->where('id', '!=', $actor->id);
            }
            if (in_array($table, ['users_groups', 'company_user'], true)) {
                $query->where('user_id', '!=', $actor->id);
            }
            foreach ($query->get() as $row) {
                $records[$table][$this->registry->key($table, $row)] = true;
            }
        }
        // Include fixture-owned policy/setup rows while retaining unowned system defaults.
        foreach (DB::table('gov_experiment_records')->get() as $record) {
            if (! in_array($record->table_name, $tables, true)) {
                continue;
            }
            if ($record->table_name === 'users' && (int) (json_decode($record->record_key, true)['id'] ?? 0) === $actor->id) {
                continue;
            }
            if ($this->registry->queryKey($record->table_name, $record->record_key)->exists()) {
                $records[$record->table_name][$record->record_key] = true;
            }
        }

        return $records;
    }

    private function relations(array $tables): array
    {
        $relations = [];
        foreach ($tables as $table) {
            if (str_starts_with($table, 'gov_experiment_')) {
                continue;
            }
            foreach (Schema::getForeignKeys($table) as $fk) {
                if (count($fk['columns']) === 1) {
                    $relations[] = [$table, $fk['columns'][0], $fk['foreign_table'], $fk['foreign_columns'][0]];
                } elseif (in_array($fk['foreign_table'], RecordRegistry::TABLES, true)) {
                    throw new RuntimeException('Composite foreign key requires a wipe adapter: '.$table);
                }
            }
        }
        foreach (self::RELATIONS as $relation) {
            if (in_array($relation[0], $tables, true) && in_array($relation[2], $tables, true)
                && Schema::hasColumn($relation[0], $relation[1])) {
                $relations[] = $relation;
            }
        }
        foreach ($tables as $table) {
            if (str_starts_with($table, 'gov_experiment_') || in_array($table, self::PRESERVED, true)) {
                continue;
            }
            $columns = Schema::getColumnListing($table);
            foreach (self::COLUMNS as $column => $parent) {
                if (in_array($column, $columns, true) && in_array($parent, $tables, true)) {
                    $relations[] = [$table, $column, $parent, 'id'];
                }
            }
            if (in_array($table, ['companies', 'locations'], true) && in_array('parent_id', $columns, true)) {
                $relations[] = [$table, 'parent_id', $table, 'id'];
            }
        }
        foreach (['consumable' => 'consumables', 'accessory' => 'accessories', 'component' => 'components', 'assetmodel' => 'models', 'asset_model' => 'models', 'asset' => 'assets', 'license' => 'licenses', 'category' => 'categories'] as $alias => $parent) {
            $class = match ($alias) {
                'assetmodel', 'asset_model' => 'App\\Models\\AssetModel', default => 'App\\Models\\'.ucfirst($alias)
            };
            foreach ([$alias, $class] as $type) {
                foreach ([['gov_inventory_movements', 'stockable'], ['gov_goods_issue_items', 'stockable'], ['custom_service_request_items', 'requested'], ['draft_basket_items', 'requested'], ['gov_document_items', 'product'], ['gov_tracking_associations', 'associatable'], ['gov_tenant_scope_mappings', 'reference'], ['gov_approval_policies', 'target'], ['gov_profile_assignments', 'target']] as [$child, $prefix]) {
                    if (in_array($child, $tables, true) && Schema::hasColumn($child, $prefix.'_id')) {
                        $relations[] = [$child, $prefix.'_id', $parent, 'id', $prefix.'_type', $type];
                    }
                }
            }
        }
        foreach (['GovStore\\StoreOperations\\Models\\Document' => 'gov_documents', 'GovStore\\StoreOperations\\Models\\GoodsIssue' => 'gov_goods_issues'] as $type => $parent) {
            foreach (['gov_document_references', 'gov_document_attachments', 'gov_document_timelines'] as $child) {
                if (in_array($child, $tables, true)) {
                    $relations[] = [$child, 'document_id', $parent, 'id', 'document_type', $type];
                }
            }
        }

        return array_unique($relations, SORT_REGULAR);
    }

    public function wipe(ExperimentRun $run, string $scope, User $actor, string $fingerprint): array
    {
        $preview = $this->preview($run, $scope, $actor);
        if (! hash_equals($preview['fingerprint'], $fingerprint) || $preview['blockers']) {
            throw new RuntimeException(__('experiments::ui.changed_preview'));
        }
        $files = $this->ownedFiles($preview['records']);
        $runIds = $scope === 'database' ? ExperimentRun::pluck('id')->all() : [$run->id];
        foreach ($runIds as $runId) {
            if (! Str::isUuid($runId)) {
                throw new RuntimeException('Invalid fixture storage identity.');
            }
            // Also clean files written by a unit whose database transaction rolled back.
            foreach (Storage::disk('local')->allFiles('gov-experiments/'.$runId.'/documents') as $path) {
                $files[] = ['disk' => 'local', 'path' => $path];
            }
        }
        $files = array_values(array_unique($files, SORT_REGULAR));
        DB::transaction(function () use ($run, $scope, $actor, $preview, $files) {
            // Lock the selected rows before the final dependency and content check.
            foreach ($preview['records'] as $table => $keys) {
                foreach (array_chunk(array_keys($keys), 500) as $chunk) {
                    DB::table($table)->where(function ($query) use ($chunk) {
                        foreach ($chunk as $key) {
                            $query->orWhere(json_decode($key, true));
                        }
                    })->lockForUpdate()->get();
                }
            }
            $fresh = $this->preview($run, $scope, $actor);
            if ($fresh['blockers'] || ! hash_equals($fresh['fingerprint'], $preview['fingerprint'])) {
                throw new RuntimeException(__('experiments::ui.changed_preview'));
            }
            $backup = $scope === 'database' ? app(ExperimentBackup::class)->create($run) : null;
            if ($scope === 'database') {
                // Keep the protected actor usable after their experiment organization disappears.
                DB::table('users')->where('id', $actor->id)->update(['company_id' => null, 'location_id' => null, 'department_id' => null, 'manager_id' => null, 'created_by' => null]);
                DB::table('company_user')->where('user_id', $actor->id)->delete();
            }
            foreach ($this->deletionLayers($fresh) as $layer) {
                foreach ($layer as $table => $keys) {
                    foreach (array_chunk($keys, 500) as $chunk) {
                        DB::table($table)->where(function ($query) use ($chunk) {
                            foreach ($chunk as $key) {
                                $query->orWhere(json_decode($key, true));
                            }
                        })->delete();
                    }
                }
            }
            DB::table('gov_experiment_records')->where('run_id', $run->id)->delete();
            $run->update(['status' => 'wiped', 'phase' => 'wiped', 'wiped_at' => now(), 'error' => null]);
            DB::table('gov_experiment_actions')->insert(['run_id' => $run->id, 'actor_id' => $actor->id, 'action' => 'wipe', 'scope' => $scope,
                'details' => json_encode(['counts' => $preview['counts'], 'backup' => $backup, 'attachment_cleanup' => $files]), 'created_at' => now(), 'updated_at' => now()]);
            if ($scope === 'database') {
                DB::table('gov_experiment_records')->delete();
                ExperimentRun::where('id', '!=', $run->id)->whereNull('wiped_at')->update(['status' => 'wiped', 'wiped_at' => now()]);
            }
        });
        $this->cleanupFiles($run, $actor);

        return $preview['counts'];
    }

    public function cleanupFiles(ExperimentRun $run, User $actor): void
    {
        $this->access->authorize($actor);
        if (! $run->wiped_at) {
            throw new RuntimeException('File cleanup requires a completed wipe.');
        }
        $action = DB::table('gov_experiment_actions')->where('run_id', $run->id)->where('action', 'wipe')->latest('id')->first();
        $failed = [];
        foreach (json_decode($action?->details ?? '{}', true)['attachment_cleanup'] ?? [] as $file) {
            if ($file['disk'] !== 'local' || ! str_starts_with($file['path'], 'gov-experiments/') || str_contains($file['path'], '..')) {
                throw new RuntimeException('Unexpected fixture file path.');
            }
            try {
                if (Storage::disk('local')->exists($file['path']) && ! Storage::disk('local')->delete($file['path'])) {
                    $failed[] = $file['path'];
                }
            } catch (\Throwable $e) {
                $failed[] = $file['path'];
            }
        }
        $run->update(['error' => $failed ? 'Business records wiped; retry attachment cleanup ('.count($failed).' files).' : null]);
    }

    private function deletionLayers(array $preview): array
    {
        $rows = $this->registry->rows($preview['records']);
        $nodes = $parents = $children = [];
        foreach ($rows as $table => $records) {
            foreach ($records as $key => $row) {
                $id = $table.'|'.$key;
                $nodes[$id] = [$table, $key];
                $children[$id] = [];
            }
        }
        foreach ($preview['relations'] as $relation) {
            [$child, $column, $parent, $parentColumn] = $relation;
            $parentIndex = [];
            foreach ($rows[$parent] ?? [] as $key => $row) {
                if ($row->{$parentColumn} !== null) {
                    $parentIndex[(string) $row->{$parentColumn}][] = $parent.'|'.$key;
                }
            }
            foreach ($rows[$child] ?? [] as $key => $row) {
                if (isset($relation[4]) && $row->{$relation[4]} !== $relation[5]) {
                    continue;
                }
                foreach ($parentIndex[(string) ($row->{$column} ?? '')] ?? [] as $parentId) {
                    $childId = $child.'|'.$key;
                    if ($childId === $parentId) {
                        continue;
                    }
                    $children[$parentId][$childId] = true;
                    $parents[$childId][$parentId] = true;
                }
            }
        }
        $layers = [];
        while ($nodes) {
            $layer = $remove = [];
            foreach ($nodes as $id => [$table, $key]) {
                if (empty($children[$id])) {
                    $layer[$table][] = $key;
                    $remove[] = $id;
                }
            }
            if (! $remove) {
                throw new RuntimeException('Wipe stopped at a dependency cycle. No deletion was committed.');
            }
            foreach ($remove as $id) {
                unset($nodes[$id]);
                foreach (array_keys($parents[$id] ?? []) as $parentId) {
                    unset($children[$parentId][$id]);
                }
            }
            $layers[] = $layer;
        }

        return $layers;
    }

    private function ownedFiles(array $records): array
    {
        $files = [];
        foreach (['gov_document_attachments' => ['file_path', 'local'], 'gov_tracking_codes' => ['order_pdf_path', 'local']] as $table => [$column, $disk]) {
            foreach (array_keys($records[$table] ?? []) as $key) {
                $row = $this->registry->queryKey($table, $key)->first();
                $path = $row->{$column} ?? null;
                // Seed attachments are always in their dedicated owned storage tree.
                if ($path && str_starts_with($path, 'gov-experiments/') && ! str_contains($path, '..')) {
                    $files[] = ['disk' => $disk, 'path' => $path];
                }
            }
        }

        return $files;
    }
}
