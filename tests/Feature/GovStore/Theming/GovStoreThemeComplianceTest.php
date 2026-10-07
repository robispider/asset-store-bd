<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Compliance\ComplianceScanner;
use PHPUnit\Framework\TestCase;

/**
 * Ratchet (§15.3): fails when a file's violation count goes up, when a new file has any
 * violation, or when a count went down but the baseline was not lowered.
 * Tighten with: php artisan gs-theme:compliance --update-baseline
 */
class GovStoreThemeComplianceTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 4).'/packages/gov-store';
    }

    public function test_gov_store_packages_do_not_regress_against_the_baseline(): void
    {
        $baseline = json_decode(file_get_contents($this->root().'/theming/compliance-baseline.json'), true);
        $diff = ComplianceScanner::compare((new ComplianceScanner($this->root()))->scan(), $baseline);

        $this->assertSame([], $diff['new'], 'New files with theme violations (colour literals, <style> blocks or inline colour styles): '.json_encode($diff['new']));
        $this->assertSame([], $diff['increased'], 'Theme violations increased: '.json_encode($diff['increased']));
        $this->assertSame([], $diff['tightenable'], 'Violations went down — lower the baseline: php artisan gs-theme:compliance --update-baseline');
    }

    public function test_theming_package_itself_is_compliant(): void
    {
        $baseline = json_decode(file_get_contents($this->root().'/theming/compliance-baseline.json'), true);
        $this->assertSame([], array_filter(array_keys($baseline), fn ($file) => str_starts_with($file, 'theming/')));
    }

    public function test_scanner_rules(): void
    {
        $scanner = new ComplianceScanner($this->root());
        $this->assertSame(['R1' => 3, 'R2' => 1, 'R3' => 1], $scanner->scanContents(
            '<style>.a{color:#fff;background:rgba(0,0,0,.1)}</style><a href="#add">x</a><div style="border-color: red">y</div>', true));
        $this->assertSame(['R1' => 0, 'R2' => 0, 'R3' => 0], $scanner->scanContents(
            '{{-- #ffffff --}}<div class="gs-box" style="--gs-kv-columns: 2">{{ $x }}</div>', true));
        $this->assertSame(['R1' => 1, 'R2' => 0, 'R3' => 0], $scanner->scanContents('.x { border: 1px solid #ccc; }', false));
    }

    public function test_ratchet_only_tightens(): void
    {
        $baseline = ['a.blade.php' => ['R1' => 5, 'R3' => 2], 'b.css' => ['R1' => 1]];
        $scan = ['a.blade.php' => ['R1' => 3, 'R3' => 4], 'c.js' => ['R1' => 1]];
        $diff = ComplianceScanner::compare($scan, $baseline);
        $this->assertSame(['a.blade.php' => ['R3' => [2, 4]]], $diff['increased']);
        $this->assertSame(['c.js' => ['R1' => 1]], $diff['new']);
        $this->assertSame([5, 3], $diff['tightenable']['a.blade.php']['R1']);
        $this->assertSame([1, 0], $diff['tightenable']['b.css']['R1']);
        $this->assertSame(['a.blade.php' => ['R1' => 3, 'R3' => 2]], ComplianceScanner::tighten($scan, $baseline));
    }
}
