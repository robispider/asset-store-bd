<?php

namespace GovStore\Experimentation\Services;

use GovStore\Experimentation\Models\ExperimentRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RecordRegistry
{
    // Explicit domain ownership; shared system schema/master rows are excluded.
    public const TABLES = [
        'companies', 'locations', 'users', 'departments', 'manufacturers', 'suppliers',
        'categories', 'models', 'status_labels', 'depreciations', 'assets', 'consumables',
        'accessories', 'components', 'licenses', 'license_seats', 'maintenances', 'maintenance_types',
        'action_logs', 'custom_fieldsets', 'custom_field_custom_fieldset', 'company_user', 'users_groups',
        'accessories_checkout', 'consumables_users', 'components_assets', 'checkout_acceptances', 'requested_assets',
        'gov_ministries_directory', 'gov_location_profiles', 'gov_ict_jurisdictions', 'gov_company_admins',
        'gov_organization_activity_logs', 'gov_office_memberships', 'gov_office_responsibilities',
        'gov_role_handshakes', 'gov_role_assignments', 'gov_override_audit_logs', 'gov_employee_verification_tokens',
        'gov_user_onboardings', 'gov_tenant_scope_mappings', 'gov_category_governance', 'gov_catalog_collections',
        'gov_catalog_collection_nodes', 'gov_catalog_snipe_mappings', 'gov_catalog_enrichments', 'gov_catalog_synonyms',
        'gov_model_metadata_states', 'gov_profiles', 'gov_profile_capabilities', 'gov_profile_assignments',
        'gov_documents', 'gov_document_items', 'gov_document_item_meta', 'gov_document_references',
        'gov_document_attachments', 'gov_document_timelines', 'gov_inventory_movements', 'gov_asset_registrations',
        'gov_goods_receipts', 'gov_goods_receipt_items', 'gov_goods_issues', 'gov_goods_issue_items',
        'gov_stock_adjustments', 'gov_stock_adjustment_items', 'custom_service_requests',
        'custom_service_request_items', 'custom_service_request_events', 'custom_request_notices', 'custom_item_requests', 'gov_approval_policies',
        'draft_baskets', 'draft_basket_items', 'gov_funding_types', 'gov_initiatives',
        'gov_tracking_operation_units', 'gov_tracking_codes', 'gov_tracking_targets', 'gov_tracking_allocations',
        'gov_tracking_scopes', 'gov_tracking_associations', 'gov_tracking_timeline', 'gov_tracking_fact_deliveries',
        'gov_tracking_projection_caches', 'gov_tracking_documents',
    ];

    public ?ExperimentRun $current = null;

    private array $primaryKeys = [];

    public function created(Model $model): void
    {
        if ($this->current && in_array($model->getTable(), self::TABLES, true)) {
            // Metadata health can inspect other models. Capture only our models' derived state
            // during dependency closure, after their INSERT observer has completed.
            if ($model->getTable() === 'gov_model_metadata_states') {
                return;
            }
            $this->record($this->current, $model->getTable(), $model->getAttributes());
        }
    }

    public function record(ExperimentRun $run, string $table, array|object $row, ?string $logicalKey = null): void
    {
        if (! in_array($table, self::TABLES, true)) {
            throw new RuntimeException('Unregistered experiment entity: '.$table);
        }
        $row = (array) $row;
        $key = $this->key($table, $row);
        $attributes = ['created_at' => now(), 'updated_at' => now()];
        if ($logicalKey !== null) {
            $attributes['logical_key'] = $logicalKey;
        }
        DB::table('gov_experiment_records')->updateOrInsert(
            ['run_id' => $run->id, 'table_name' => $table, 'record_key' => $key],
            $attributes
        );
    }

    public function key(string $table, array|object $row): string
    {
        $values = [];
        $row = (array) $row;
        foreach ($this->keys($table) as $column) {
            if (! array_key_exists($column, $row)) {
                throw new RuntimeException('Missing record identity: '.$table.'.'.$column);
            }
            $values[$column] = (string) $row[$column];
        }

        return json_encode($values, JSON_THROW_ON_ERROR);
    }

    public function keys(string $table): array
    {
        return $this->primaryKeys[$table] ??= $this->resolveKeys($table);
    }

    private function resolveKeys(string $table): array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }

        // Old native pivot tables have no declared primary key.
        return match ($table) {
            'users_groups' => ['user_id', 'group_id'],
            'company_user' => ['company_id', 'user_id'],
            'custom_field_custom_fieldset' => ['custom_field_id', 'custom_fieldset_id'],
            default => throw new RuntimeException('No safe deletion identity for '.$table),
        };
    }

    public function queryKey(string $table, string $key): Builder
    {
        return DB::table($table)->where(json_decode($key, true, 512, JSON_THROW_ON_ERROR));
    }

    public function records(ExperimentRun $run): array
    {
        $records = [];
        foreach (DB::table('gov_experiment_records')->where('run_id', $run->id)->get() as $record) {
            $records[$record->table_name][$record->record_key] = true;
        }

        return array_map(fn ($rows) => array_fill_keys(array_keys($rows), true), $this->rows($records, true));
    }

    public function rows(array $records, bool $identitiesOnly = false): array
    {
        $result = [];
        foreach ($records as $table => $keys) {
            $primary = $this->keys($table);
            foreach (array_chunk(array_keys($keys), 500) as $chunk) {
                $query = DB::table($table);
                if ($identitiesOnly) {
                    $query->select($primary);
                }
                if (count($primary) === 1) {
                    $query->whereIn($primary[0], array_map(fn ($key) => json_decode($key, true)[$primary[0]], $chunk));
                } else {
                    $query->where(function ($query) use ($chunk) {
                        foreach ($chunk as $key) {
                            $query->orWhere(json_decode($key, true));
                        }
                    });
                }
                foreach ($query->get() as $row) {
                    $key = $this->key($table, $row);
                    if (isset($keys[$key])) {
                        $result[$table][$key] = $row;
                    }
                }
            }
        }

        return $result;
    }

    public function counts(ExperimentRun $run): array
    {
        return array_map('count', $this->records($run));
    }

    public function ids(ExperimentRun $run, string $table): array
    {
        return DB::table('gov_experiment_records')->where('run_id', $run->id)->where('table_name', $table)
            ->pluck('record_key')->map(fn ($key) => json_decode($key, true)['id'])->all();
    }
}
