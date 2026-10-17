<?php

namespace GovStore\AssetTagging\Console\Commands;

use App\Models\Setting;
use GovStore\AssetTagging\Services\EnablementGate;
use GovStore\Classification\Services\ProductTypeCommodity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only report before any office is enabled. Nothing is merged, relabelled or written. */
class PreflightCommand extends Command
{
    protected $signature = 'asset-tags:preflight {--json : Print the report as JSON}';

    protected $description = 'Read-only asset tagging preflight: schema, bridge duplicates, UNSPSC per type, legacy clashes, native settings, experiment offices.';

    public function handle(EnablementGate $gate, ProductTypeCommodity $commodity): int
    {
        $report = [
            'schema' => $this->schema(),
            'bridge_duplicate_tags' => $this->duplicates('asset_tag'),
            'bridge_duplicate_assets' => $this->duplicates('asset_id'),
            'product_types_with_several_unspsc' => $commodity->categoriesWithSeveralCodes(),
            'unique_category_index' => $commodity->hasUniqueIndex(),
            'legacy_18_digit_tags' => $gate->legacyClashTags(),
            'native_settings' => $this->native($gate),
            'experiment_offices' => Schema::hasTable('gov_experiment_records')
                ? DB::table('gov_experiment_records')->where('table_name', 'locations')->pluck('record_key')
                    ->map(fn ($key) => (int) (json_decode($key, true)['id'] ?? 0))->unique()->values()->all() : [],
            'installation_gates' => collect($gate->installation())->mapWithKeys(fn ($g) => [$g['key'] => $g['passed']])->all(),
        ];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }
        $this->info('Schema');
        foreach ($report['schema'] as $check => $ok) {
            $this->line(sprintf('  [%s] %s', $ok ? 'ok' : '--', $check));
        }
        $this->info('Bridge');
        $this->line('  duplicate tags: '.count($report['bridge_duplicate_tags']).'; duplicate assets: '.count($report['bridge_duplicate_assets']));
        $this->info('Product types with several UNSPSC codes: '.count($report['product_types_with_several_unspsc']).
            ' (unique index '.($report['unique_category_index'] ? 'applied' : 'not applied').')');
        $this->info('Legacy 18-digit tags not issued as Govt. Tags (soft-deleted included): '.count($report['legacy_18_digit_tags']));
        foreach (array_slice($report['legacy_18_digit_tags'], 0, 20) as $tag) {
            $this->line('  '.$tag);
        }
        $this->info('Native settings: '.json_encode($report['native_settings']));
        $this->info('Experiment offices: '.(implode(', ', $report['experiment_offices']) ?: 'none'));
        foreach ($report['installation_gates'] as $key => $passed) {
            $this->line(sprintf('  %s %s', $key, $passed ? 'passes' : 'fails'));
        }

        return self::SUCCESS;
    }

    private function schema(): array
    {
        $indexes = Schema::hasTable('gov_asset_registrations') ? collect(Schema::getIndexes('gov_asset_registrations'))->pluck('name')->all() : [];

        return [
            'gov_metadata_values table' => Schema::hasTable('gov_metadata_values'),
            'gov_finance_items table' => Schema::hasTable('gov_finance_items'),
            'gov_asset_tag_sequences table' => Schema::hasTable('gov_asset_tag_sequences'),
            'bridge evidence columns' => Schema::hasColumn('gov_asset_registrations', 'tag_snapshot'),
            'bridge unique tag' => in_array('registration_asset_tag', $indexes, true),
            'bridge unique source unit' => in_array('registration_source_unit', $indexes, true),
            'bridge unique asset' => in_array('registration_asset', $indexes, true),
        ];
    }

    private function duplicates(string $column): array
    {
        return Schema::hasTable('gov_asset_registrations')
            ? DB::table('gov_asset_registrations')->select($column, DB::raw('count(*) as total'))->groupBy($column)
                ->havingRaw('count(*) > 1')->pluck('total', $column)->all() : [];
    }

    private function native(EnablementGate $gate): array
    {
        $settings = Setting::getSettings();

        return ['auto_increment' => (string) $settings?->auto_increment_assets === '1', 'prefix' => $settings?->auto_increment_prefix,
            'zerofill' => (int) $settings?->zerofill_count, 'next' => (int) $settings?->next_auto_tag_base, 'g1' => $gate->nativeSequenceReady($settings)];
    }
}
