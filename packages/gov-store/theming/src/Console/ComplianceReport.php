<?php

namespace GovStore\Theming\Console;

use GovStore\Theming\Compliance\ComplianceScanner;
use Illuminate\Console\Command;

class ComplianceReport extends Command
{
    protected $signature = 'gs-theme:compliance
        {--update-baseline : Lower the ratchet baseline to the current counts (never raises it)}
        {--init : Write the baseline from the current scan (first adoption only)}
        {--json : Machine-readable output}';

    protected $description = 'Report gov-store theme-compliance violations (colour literals, <style> blocks, inline colour styles) against the ratchet baseline';

    public function handle(): int
    {
        $scanner = new ComplianceScanner(base_path('packages/gov-store'));
        $baselineFile = base_path('packages/gov-store/theming/compliance-baseline.json');
        $baseline = is_file($baselineFile) ? (json_decode((string) file_get_contents($baselineFile), true) ?: []) : [];
        $scan = $scanner->scan();

        if ($this->option('init')) {
            if ($baseline !== []) {
                $this->error('A baseline already exists; use --update-baseline to tighten it.');

                return self::FAILURE;
            }
            $this->write($baselineFile, $scan);
            $this->info('Baseline written with '.count($scan).' file(s).');

            return self::SUCCESS;
        }
        if ($this->option('update-baseline')) {
            $next = ComplianceScanner::tighten($scan, $baseline);
            $this->write($baselineFile, $next);
            $this->info('Baseline tightened: '.$this->total($baseline).' → '.$this->total($next).' violation(s).');
        }

        $diff = ComplianceScanner::compare($scan, $baseline);
        if ($this->option('json')) {
            $this->line(json_encode(['scan' => $scan, 'diff' => $diff, 'total' => $this->total($scan)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $byPackage = [];
            foreach ($scan as $file => $counts) {
                $package = explode('/', $file)[0];
                foreach ($counts as $rule => $count) {
                    $byPackage[$package][$rule] = ($byPackage[$package][$rule] ?? 0) + $count;
                }
            }
            arsort($byPackage);
            $this->table(['Package', 'R1 colour literals', 'R2 <style> blocks', 'R3 inline styles'],
                array_map(fn ($p, $c) => [$p, $c['R1'] ?? 0, $c['R2'] ?? 0, $c['R3'] ?? 0], array_keys($byPackage), $byPackage));
            foreach ($diff['increased'] as $file => $rules) {
                foreach ($rules as $rule => [$allowed, $count]) {
                    $this->error("↑ {$file} {$rule}: {$allowed} → {$count}");
                }
            }
            foreach ($diff['new'] as $file => $rules) {
                $this->error("+ {$file} has new violations: ".json_encode($rules));
            }
            if ($diff['tightenable'] && ! $this->option('update-baseline')) {
                $this->warn(count($diff['tightenable']).' file(s) improved — run `php artisan gs-theme:compliance --update-baseline`.');
            }
        }

        return $diff['increased'] || $diff['new'] ? self::FAILURE : self::SUCCESS;
    }

    private function write(string $file, array $data): void
    {
        file_put_contents($file, json_encode((object) $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function total(array $data): int
    {
        return array_sum(array_map('array_sum', $data));
    }
}
