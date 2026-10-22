<?php

namespace GovStore\GeoView\Console;

use GovStore\GeoAreas\Services\BoundarySet;
use GovStore\GeoView\Config\GeoViewConfigResolver;
use GovStore\GeoView\Contracts\GeoDataSource;
use GovStore\GeoView\Registry\GeoPresetRegistry;
use GovStore\GeoView\Registry\GeoSourceRegistry;
use GovStore\GeoView\Support\JsonSchema;
use Illuminate\Console\Command;
use Throwable;

/**
 * Checks presets, sources, the boundary set and the contracts (plan §8, §13: "doctor clean in CI").
 */
class GeoViewDoctorCommand extends Command
{
    protected $signature = 'geo-view:doctor {--json : Machine-readable report}';

    protected $description = 'Validate geo view presets, data sources, boundary set and contract self-checks';

    public function handle(GeoSourceRegistry $sources, GeoPresetRegistry $presets, GeoViewConfigResolver $resolver, BoundarySet $boundaries): int
    {
        $report = [];
        $add = function (string $level, string $subject, string $message) use (&$report) {
            $report[] = compact('level', 'subject', 'message');
        };

        foreach (['gs-geo-config.schema.json', 'gs-geo-data.schema.json'] as $contract) {
            try {
                JsonSchema::contract($contract);
                $add('ok', $contract, 'parses');
            } catch (Throwable $e) {
                $add('error', $contract, $e->getMessage());
            }
        }

        if (! $boundaries->exists()) {
            $add('error', 'boundaries', "boundary set {$boundaries->version()} not found");
        } else {
            $published = public_path(trim((string) config('geo-areas.boundary_url'), '/').'/'.$boundaries->version().'/manifest.json');
            $add(is_file($published) ? 'ok' : 'warning', 'boundaries', $boundaries->version().(is_file($published)
                ? ' published' : ' not published — run php artisan geo-areas:publish-boundaries'));
        }

        foreach ($sources->keys() as $key) {
            try {
                $source = $sources->get($key);
                if ($source->key() !== $key) {
                    $add('error', "source {$key}", "key() returns {$source->key()}");
                }
                if (! in_array($source->grain(), [GeoDataSource::GRAIN_OFFICE, GeoDataSource::GRAIN_AREA], true)) {
                    $add('error', "source {$key}", 'unknown grain '.$source->grain());
                }
                $add('ok', "source {$key}", get_class($source).' · '.$source->grain().' · ability '.$source->ability());
            } catch (Throwable $e) {
                $add('error', "source {$key}", $e->getMessage());
            }
        }

        foreach ($presets->all() as $key => $preset) {
            try {
                $config = $resolver->resolve($key, ['id' => 'doctor-'.substr(preg_replace('/[^a-z0-9-]/', '-', $key), 0, 30)])['config'];
                $sourceKey = $config['source']['key'] ?? null;
                if ($sourceKey && ! $sources->has($sourceKey)) {
                    $add('error', "preset {$key}", "source {$sourceKey} is not registered");
                } elseif (($config['source']['mode'] ?? 'remote') === 'function') {
                    // A function source bypasses server scope (plan §7.3): only for data the page already rendered.
                    $add('warning', "preset {$key}", 'uses a function source; check it only shows data already on the page');
                } else {
                    $add('ok', "preset {$key}", "intent {$config['intent']}, source {$sourceKey}");
                }
            } catch (Throwable $e) {
                $add('error', "preset {$key}", $e->getMessage());
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['', 'Check', 'Result'], array_map(fn ($r) => [['ok' => '✓', 'warning' => '!', 'error' => '✗'][$r['level']], $r['subject'], $r['message']], $report));
        }

        return array_filter($report, fn ($r) => $r['level'] === 'error') ? self::FAILURE : self::SUCCESS;
    }
}
