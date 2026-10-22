<?php

namespace GovStore\GeoAreas\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Loads the bundled geo seed (database/data/geo_areas.csv + seed/) into
 * gov_geo_areas. Inserts missing areas, writes the BBS code columns, fills
 * empty Bangla names and applies the reviewed English fixes. It never deletes
 * a row and never changes GeoAreaId, geo_code, parent_geo_code or hid.
 *
 * See docs/plans/bbs-geocode-adoption-plan.md §5.
 */
final class GeoAreaDatasetSync
{
    public const CODE_COLUMNS = ['bbs_code', 'bbs_code_legacy', 'code_status', 'code_source', 'bbs_attributes'];

    private const CORE_COLUMNS = ['GeoAreaId', 'hid', 'geo_type', 'parent_geo_code', 'geo_code', 'bn_name', 'domain', 'en_name', 'GeoLevel'];

    private const CHUNK = 500;

    private string $dataPath;

    public function __construct(?string $dataPath = null)
    {
        $this->dataPath = rtrim($dataPath ?? dirname(__DIR__).'/database/data', '/\\');
    }

    /**
     * @return array{version: string, admin_inserted: int, codes_written: int, english_fixed: int,
     *               bangla_filled: int, localities_inserted: int, localities_updated: int,
     *               rows_without_code: int, rows_without_code_sample: list<int>, dry_run: bool}
     */
    public function run(bool $dryRun = false, bool $withLocalities = true): array
    {
        // Query listeners (e.g. Ignition's query recorder) keep every statement;
        // 170k bulk rows would exhaust a 128 MB CLI. Detach them for the load.
        $connection = DB::connection();
        $dispatcher = $connection->getEventDispatcher();
        $connection->unsetEventDispatcher();
        try {
            return $this->load($dryRun, $withLocalities);
        } finally {
            if ($dispatcher) {
                $connection->setEventDispatcher($dispatcher);
            }
        }
    }

    private function load(bool $dryRun, bool $withLocalities): array
    {
        $manifest = $this->verifiedManifest();
        $migrated = Schema::hasColumn('gov_geo_areas', 'bbs_code');
        if (! $migrated && ! $dryRun) {
            throw new RuntimeException('gov_geo_areas has no geocode columns yet: run php artisan migrate first.');
        }
        $codes = $this->readCodes();
        $core = $this->readCore();
        $existing = DB::table('gov_geo_areas')->where('GeoAreaId', '<', 1000000)
            ->get(['GeoAreaId', 'en_name', 'bn_name', ...($migrated ? self::CODE_COLUMNS : [])])->keyBy('GeoAreaId');
        $now = now();

        $report = ['version' => $manifest['version'], 'admin_inserted' => 0, 'codes_written' => 0, 'english_fixed' => 0,
            'bangla_filled' => 0, 'localities_inserted' => 0, 'localities_updated' => 0, 'dry_run' => $dryRun];

        $rows = [];
        $englishFixes = $banglaFills = [];
        foreach ($core as $id => $row) {
            $code = $codes[$id] ?? throw new RuntimeException("Seed has no code row for GeoAreaId {$id}.");
            $current = $existing->get($id);
            if (! $current) {
                $report['admin_inserted']++;
            } else {
                if ($this->codesDiffer($current, $code)) {
                    $report['codes_written']++;
                }
                if (self::isJunkEnglish((string) $current->en_name) && $current->en_name !== $row['en_name']) {
                    $englishFixes[$id] = $row['en_name'];
                }
                if (trim((string) $current->bn_name) === '' && $row['bn_name'] !== '') {
                    $banglaFills[$id] = $row['bn_name'];
                }
            }
            if ($row['geo_type'] === 'country' && $current && ! empty($current->bbs_attributes)) {
                // Keep the recorded dataset version until this run records its own.
                $kept = array_intersect_key(json_decode($current->bbs_attributes, true) ?: [],
                    array_flip(['dataset_version', 'dataset_sha256']));
                $code['bbs_attributes'] = json_encode(array_merge(json_decode((string) $code['bbs_attributes'], true) ?: [], $kept),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $rows[] = $row + $code + ['created_at' => $now, 'updated_at' => $now];
        }
        $report['english_fixed'] = count($englishFixes);
        $report['bangla_filled'] = count($banglaFills);

        if (! $dryRun) {
            DB::transaction(function () use ($rows, $englishFixes, $banglaFills, $now) {
                foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                    // Inserts missing areas; for existing ones only the code columns change.
                    DB::table('gov_geo_areas')->upsert($chunk, ['GeoAreaId'], [...self::CODE_COLUMNS, 'updated_at']);
                }
                foreach ($englishFixes as $id => $name) {
                    DB::table('gov_geo_areas')->where('GeoAreaId', $id)->update(['en_name' => $name, 'updated_at' => $now]);
                }
                foreach ($banglaFills as $id => $name) {
                    DB::table('gov_geo_areas')->where('GeoAreaId', $id)->update(['bn_name' => $name, 'updated_at' => $now]);
                }
            });
        }

        if ($withLocalities) {
            [$report['localities_inserted'], $report['localities_updated']] = $this->syncLocalities($dryRun, $now);
        }

        if (! $dryRun) {
            $this->recordVersion($manifest);
            // Name fixes keep the row count, so the tree cache key alone would not notice them.
            app(GeoTree::class)->forget();
        }
        $missing = DB::table('gov_geo_areas')->where(fn ($q) => $q->whereNull('bbs_code')->orWhereNull('code_status'));
        $report['rows_without_code'] = $dryRun ? 0 : (clone $missing)->count();
        $report['rows_without_code_sample'] = $dryRun ? [] : $missing->orderBy('GeoAreaId')->limit(20)->pluck('GeoAreaId')->map(fn ($id) => (int) $id)->all();

        return $report;
    }

    /** The dataset version recorded by the last sync, or null. */
    public function installedVersion(): ?string
    {
        if (! Schema::hasColumn('gov_geo_areas', 'bbs_attributes')) {
            return null;
        }
        $attributes = DB::table('gov_geo_areas')->where('geo_type', 'country')->value('bbs_attributes');

        return $attributes ? (json_decode($attributes, true)['dataset_version'] ?? null) : null;
    }

    /** Same rule as tools/build_geo_seed.py (plan decision D2). */
    public static function isJunkEnglish(string $name): bool
    {
        return $name === '' || $name === 'Www' || preg_match('/^\d+no/i', $name) === 1 || str_ends_with($name, ' Distric');
    }

    private function verifiedManifest(): array
    {
        $manifest = json_decode((string) @file_get_contents($this->dataPath.'/seed/manifest.json'), true);
        if (! is_array($manifest) || empty($manifest['sha256'])) {
            throw new RuntimeException('Geo seed manifest is missing; run tools/build_geo_seed.py.');
        }
        foreach ($manifest['sha256'] as $file => $hash) {
            $path = $this->dataPath.'/'.$file;
            // geo_areas.csv is a git text file (LF or CRLF by platform); it is hashed as CRLF.
            $actual = is_file($path) ? ($file === 'geo_areas.csv' ? $this->crlfHash($path) : hash_file('sha256', $path)) : '';
            if (! hash_equals($hash, $actual)) {
                throw new RuntimeException("Geo seed file {$file} does not match manifest.json; rebuild the seed.");
            }
        }

        return $manifest;
    }

    private function crlfHash(string $path): string
    {
        $text = (string) file_get_contents($path);

        return hash('sha256', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $text)));
    }

    /** @return array<int, array<string, mixed>> */
    private function readCore(): array
    {
        $rows = [];
        $handle = fopen($this->dataPath.'/geo_areas.csv', 'r');
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $row[0] = preg_replace('/^\x{FEFF}/u', '', $row[0] ?? '');
            if (count($row) < 9 || ! is_numeric($row[0])) {
                continue;
            }
            $hid = trim($row[1]);
            $domain = trim($row[6]);
            $rows[(int) $row[0]] = [
                'GeoAreaId' => (int) $row[0],
                'hid' => ($hid === '' || $hid === '?') ? null : $hid,
                'geo_type' => trim($row[2]),
                'parent_geo_code' => $row[3] !== '' ? (int) $row[3] : null,
                'geo_code' => (int) $row[4],
                'bn_name' => trim($row[5]),
                'domain' => ($domain === '' || $domain === '?') ? null : $domain,
                'en_name' => trim($row[7]),
                'GeoLevel' => (int) $row[8],
            ];
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<int, array<string, string|null>> */
    private function readCodes(): array
    {
        $codes = [];
        $handle = fopen($this->dataPath.'/seed/geo_area_codes.csv', 'r');
        $header = fgetcsv($handle, 0, ',', '"', '');
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $r = array_combine($header, $row);
            $codes[(int) $r['GeoAreaId']] = $this->codeColumns($r);
        }
        fclose($handle);

        return $codes;
    }

    private function codeColumns(array $r): array
    {
        return [
            'bbs_code' => $r['bbs_code'],
            'bbs_code_legacy' => $r['bbs_code_legacy'] !== '' ? $r['bbs_code_legacy'] : null,
            'code_status' => $r['code_status'],
            'code_source' => $r['code_source'],
            'bbs_attributes' => $r['bbs_attributes'] !== '' ? $r['bbs_attributes'] : null,
        ];
    }

    private function codesDiffer(object $current, array $code): bool
    {
        foreach (['bbs_code', 'bbs_code_legacy', 'code_status', 'code_source'] as $column) {
            if ((string) ($current->{$column} ?? '') !== (string) $code[$column]) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: int, 1: int} inserted, updated */
    private function syncLocalities(bool $dryRun, $now): array
    {
        // Counted, not listed: 168k ids would not fit a 128 MB CLI. Locality ids
        // are fixed by the seed and never reused, so before/after counts are exact.
        $before = DB::table('gov_geo_areas')->where('GeoAreaId', '>=', 1000000)->count();
        $total = 0;
        $handle = gzopen($this->dataPath.'/seed/geo_area_localities.csv.gz', 'r');
        $header = fgetcsv($handle, 0, ',', '"', '');
        $chunk = [];
        $flush = function () use (&$chunk, $dryRun) {
            if ($chunk && ! $dryRun) {
                DB::table('gov_geo_areas')->upsert($chunk, ['GeoAreaId'], [...self::CODE_COLUMNS, 'updated_at']);
            }
            $chunk = [];
        };
        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $r = array_combine($header, $row);
                $total++;
                $chunk[] = [
                    'GeoAreaId' => (int) $r['GeoAreaId'],
                    'hid' => $r['hid'] !== '' ? $r['hid'] : null,
                    'geo_type' => $r['geo_type'],
                    'parent_geo_code' => (int) $r['parent_geo_code'],
                    'geo_code' => (int) $r['geo_code'],
                    'bn_name' => $r['bn_name'],
                    'domain' => null,
                    'en_name' => $r['en_name'],
                    'GeoLevel' => (int) $r['GeoLevel'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ] + $this->codeColumns($r);
                if (count($chunk) >= self::CHUNK) {
                    $flush();
                }
            }
            $flush();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            gzclose($handle);
        }
        $inserted = $dryRun ? max(0, $total - $before)
            : DB::table('gov_geo_areas')->where('GeoAreaId', '>=', 1000000)->count() - $before;

        return [$inserted, $total - $inserted];
    }

    private function recordVersion(array $manifest): void
    {
        $country = DB::table('gov_geo_areas')->where('geo_type', 'country')->first(['GeoAreaId', 'bbs_attributes']);
        if (! $country) {
            return;
        }
        $attributes = json_decode((string) $country->bbs_attributes, true) ?: [];
        $attributes['dataset_version'] = $manifest['version'];
        $attributes['dataset_sha256'] = $manifest['sha256'];
        DB::table('gov_geo_areas')->where('GeoAreaId', $country->GeoAreaId)
            ->update(['bbs_attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
