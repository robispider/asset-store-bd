<?php

namespace GovStore\AssetTagging\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gates G1–G6. An office is enabled only when every gate passes; G1 and G4 are re-checked at every posting.
 * Each gate is ['key' => 'G1', 'passed' => bool, 'label' => …, 'detail' => …].
 */
class EnablementGate
{
    public function __construct(private OfficeTagFacts $facts) {}

    /** G1: native auto-increment is on and the prefix is non-empty and does not start with a digit. */
    public function nativeSequenceReady(?Setting $settings = null): bool
    {
        $settings ??= Setting::getSettings();
        $prefix = (string) ($settings?->auto_increment_prefix ?? '');

        return $settings && (string) $settings->auto_increment_assets === '1' && $prefix !== '' && ! ctype_digit($prefix[0]);
    }

    /** G2: the bridge has its unique keys and no duplicate tags or assets. */
    public function bridgeClean(): bool
    {
        if (! Schema::hasColumn('gov_asset_registrations', 'format_version')) {
            return false;
        }
        $indexes = collect(Schema::getIndexes('gov_asset_registrations'))->pluck('name')->all();
        if (array_diff(['registration_asset_tag', 'registration_source_unit', 'registration_asset'], $indexes)) {
            return false;
        }

        return ! DB::table('gov_asset_registrations')->select('asset_tag')->groupBy('asset_tag')->havingRaw('count(*) > 1')->exists();
    }

    /** Legacy 18-digit asset tags (soft-deleted included) that were not issued as Govt. Tags. */
    public function legacyClashes(): int
    {
        return count($this->legacyClashTags());
    }

    /** @return string[] */
    public function legacyClashTags(): array
    {
        $issued = Schema::hasColumn('gov_asset_registrations', 'format_version')
            ? DB::table('gov_asset_registrations')->where('format_version', 'fd18-v1')->pluck('asset_tag')->flip() : collect();

        return DB::table('assets')->whereRaw('LENGTH(asset_tag) = 18')->pluck('asset_tag')
            ->filter(fn ($tag) => ctype_digit((string) $tag) && ! $issued->has($tag))->values()->all();
    }

    public function experimentOffice(int $officeId): bool
    {
        return Schema::hasTable('gov_experiment_records') && DB::table('gov_experiment_records')->where('table_name', 'locations')
            ->where('record_key', json_encode(['id' => (string) $officeId]))->exists();
    }

    /** G6 rehearsal (config asset-tagging.rehearsal): experiment offices may issue, never in production. */
    public function rehearsal(): bool
    {
        return (bool) config('asset-tagging.rehearsal') && ! app()->isProduction();
    }

    /** Installation gates G1, G2, G3, G5. */
    public function installation(): array
    {
        $clashes = $this->legacyClashes();

        return [
            $this->gate('G1', $this->nativeSequenceReady()),
            $this->gate('G2', $this->bridgeClean()),
            $this->gate('G3', $clashes === 0 || filled(config('asset-tagging.legacy_reviewed_at')), ['count' => $clashes]),
            $this->gate('G5', (bool) config('asset-tagging.single_issuer')),
        ];
    }

    /** Office gates G4 and G6. */
    public function office(int $officeId): array
    {
        return [
            $this->gate('G4', $this->facts->complete($officeId)),
            $this->experimentOffice($officeId) && $this->rehearsal()
                ? ['key' => 'G6', 'passed' => true, 'label' => __('asset-tagging::tagging.gates.G6.label'), 'detail' => __('asset-tagging::tagging.gates.G6.rehearsal')]
                : $this->gate('G6', ! $this->experimentOffice($officeId)),
        ];
    }

    public function all(int $officeId): array
    {
        $gates = array_merge($this->installation(), $this->office($officeId));
        usort($gates, fn ($a, $b) => strcmp($a['key'], $b['key']));

        return $gates;
    }

    public function passes(int $officeId): bool
    {
        return collect($this->all($officeId))->every(fn ($gate) => $gate['passed']);
    }

    /** The ★ gates re-checked at every posting: G1 and G4. Returns the failing keys. */
    public function postingFailures(int $officeId): array
    {
        return array_values(array_filter([
            $this->nativeSequenceReady() ? null : 'G1',
            $this->facts->complete($officeId) ? null : 'G4',
        ]));
    }

    private function gate(string $key, bool $passed, array $replace = []): array
    {
        return ['key' => $key, 'passed' => $passed, 'label' => __('asset-tagging::tagging.gates.'.$key.'.label', $replace),
            'detail' => __('asset-tagging::tagging.gates.'.$key.'.'.($passed ? 'ok' : 'todo'), $replace)];
    }
}
