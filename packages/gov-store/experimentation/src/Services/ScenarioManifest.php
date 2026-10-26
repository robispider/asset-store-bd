<?php

namespace GovStore\Experimentation\Services;

use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\Metadata\Services\MetadataValueStore;
use GovStore\Theming\ThemeManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Versioned promises, independent of the number of rows produced by a run. */
class ScenarioManifest
{
    public const VERSION = 'bd-government-v2';

    public const MODULES = ['organization', 'office-membership', 'user-onboarding', 'tenant-scope',
        'classification', 'metadata', 'asset-tagging', 'store-operations', 'custom-requests',
        'tracking', 'committee', 'geo-areas', 'geo-view', 'theming', 'experimentation'];

    public const REQUEST_RECIPES = ['pending', 'primary', 'two_stage', 'auto', 'rejected',
        'reduced', 'partial', 'complete', 'acknowledged', 'withdrawn', 'closed', 'threshold'];

    public function requestTargets(): array
    {
        return ['asset_model' => self::REQUEST_RECIPES,
            'consumable' => [...self::REQUEST_RECIPES, 'return', 'substitution'],
            'accessory' => [...self::REQUEST_RECIPES, 'return', 'substitution'], 'mixed_policy_requests' => 2];
    }

    public static function current(ExperimentRun $run): bool
    {
        return ($run->report['scenario_version'] ?? null) === self::VERSION;
    }

    public function preflight(): void
    {
        $columns = [
            'gov_location_profiles' => ['office_type', 'office_level', 'lifecycle_status'],
            'gov_geo_areas' => ['bbs_code', 'code_status', 'bbs_attributes'],
            'gov_metadata_values' => ['subject_type', 'subject_id', 'namespace', 'revision'],
            'gov_finance_items' => ['gov_code', 'status'],
            'gov_asset_tag_sequences' => ['namespace', 'scope_key', 'last_value'],
            'gov_asset_registrations' => ['unit_index', 'format_version', 'gov_code', 'tag_snapshot'],
            'custom_service_requests' => ['office_id', 'received_at', 'return_document_id', 'submission_batch', 'resolved_reason'],
            'draft_baskets' => ['office_id'],
            'gov_approval_policies' => ['threshold_qty', 'threshold_value'],
            'gov_reference_owners' => ['reference_type', 'reference_id', 'owner_type', 'owner_id'],
            'gov_documents' => ['drafted_by', 'posted_by', 'supplier_id', 'destination_location_id', 'adjustment_reason'],
            'gov_document_notices' => ['document_id'],
            'gov_tracking_codes' => ['activated_at', 'activation_is_approximate'],
            'gov_tracking_deliveries' => ['movement_id', 'document_item_id', 'receipt_snapshot'],
            'gov_tracking_associations' => ['delivery_id'],
            'gov_committees' => ['owner_location_id', 'status'],
            'gs_theme_preferences' => ['user_id', 'theme', 'mode'],
            'gs_theme_assignments' => ['scope_type', 'scope_id'],
        ];
        foreach ($columns as $table => $required) {
            if (! Schema::hasTable($table) || ! Schema::hasColumns($table, $required)) {
                throw new RuntimeException('Sample data requires the current package schema: '.$table.'. Apply its targeted migrations first.');
            }
        }
        foreach (Schema::getTables(DB::connection()->getDatabaseName()) as $table) {
            $name = $table['name'];
            if ((str_starts_with($name, 'gov_') || str_starts_with($name, 'gs_theme_'))
                && ! in_array($name, [...RecordRegistry::TABLES, ...ExperimentWiper::PRESERVED], true)) {
                throw new RuntimeException('Sample data needs an explicit ownership or preservation adapter for '.$name.'.');
            }
        }
        foreach (['gov_asset_registrations' => ['registration_asset_tag', 'registration_source_unit', 'registration_asset'],
            'gov_catalog_snipe_mappings' => ['catalog_mapping_category'], 'gov_metadata_values' => ['metadata_subject']] as $table => $required) {
            $names = array_column(Schema::getIndexes($table), 'name');
            if (array_diff($required, $names)) {
                throw new RuntimeException('Sample data requires the current uniqueness protections on '.$table.'.');
            }
        }
        $metadata = app(MetadataValueStore::class);
        foreach (['asset-tagging.codelists', 'asset-tagging.office', 'asset-tagging.enablement', 'asset-tagging.default'] as $namespace) {
            if (! $metadata->registered($namespace)) {
                throw new RuntimeException('Required metadata package integration is unavailable: '.$namespace.'.');
            }
        }
        if (! app(ThemeManager::class)->repository()->published()) {
            throw new RuntimeException('Publish a bundled theme before generating sample data.');
        }
        $manifest = json_decode(file_get_contents(base_path('packages/gov-store/geo-areas/src/database/data/seed/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        $country = DB::table('gov_geo_areas')->where('geo_type', 'country')->value('bbs_attributes');
        if ((json_decode((string) $country, true)['dataset_version'] ?? null) !== $manifest['version']) {
            throw new RuntimeException('Install the current versioned Geo Areas dataset before generating sample data.');
        }
    }

    /** Short aliases are allocated under ExperimentManager's installation admission lock. */
    public function alias(): string
    {
        $number = DB::table('gov_experiment_runs')->count() + 1;
        do {
            $alias = 'D'.strtoupper(base_convert((string) $number++, 10, 36));
        } while (DB::table('gov_experiment_runs')->where('report->display_id', $alias)->exists());

        return $alias;
    }

    public function name(string $name, string $alias): string
    {
        $name = preg_replace('/\s*·\s*'.preg_quote($alias, '/').'(?:-\d+)?/u', '', $name);
        $name = trim(preg_replace('/(?<![\pL\pN])'.preg_quote($alias, '/').'(?![\pL\pN])/u', '', $name));
        $name = preg_replace('/\s*\(পরীক্ষামূলক\s*\)/u', '', $name);
        $ordinal = '';
        if (preg_match('/\s+(\d+)$/u', $name, $match)) {
            $ordinal = '-'.str_pad($match[1], 2, '0', STR_PAD_LEFT);
            $name = preg_replace('/\s+\d+$/u', '', $name);
        }

        return mb_substr(trim($name), 0, 170).' · '.$alias.$ordinal;
    }
}
