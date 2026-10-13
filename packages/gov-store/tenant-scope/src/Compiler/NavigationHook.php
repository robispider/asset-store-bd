<?php

namespace GovStore\TenantScope\Compiler;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

/**
 * Attaches the GovStore navigation zones to Snipe-IT's layouts/default when Blade compiles it
 * (once; cached in storage/framework/views). Core files are never edited, so upstream merges
 * stay conflict-free. A missing anchor skips only that zone and the page still renders;
 * SnipeItNavContractTest fails loudly when an upstream change moves an anchor.
 */
final class NavigationHook
{
    public const VERSION = 'gov-nav-hook:v1';

    public const TARGET = 'resources/views/layouts/default.blade.php';

    /**
     * zone => [anchor regex, insert 'before' the match | 'after' it].
     * Patterns run against upstream markup; keep them as loose as stays unambiguous.
     */
    public const ANCHORS = [
        // Sidebar: after the Dashboard entry, before the first core section (Assets).
        'work' => ['/(?<=<ul class="sidebar-menu")(?:(?!<\/ul>).)*?\K@can\(\s*\'index\',\s*\\\\App\\\\Models\\\\Asset::class\s*\)/s', 'before'],
        // Sidebar: last entries, after Snipe-IT's Settings, Reports and Requestable items.
        'admin' => ['/<\/ul>(?=\s*<\/section>\s*<!-- \/\.sidebar -->)/', 'before'],
        // User menu: before the divider that precedes Logout.
        'user' => ['/<li class="divider"><\/li>(?=\s*<li>\s*<a href="\{\{\s*route\(\'logout\.get\'\)\s*\}\}")/', 'before'],
        // Navbar: before the user account dropdown.
        'topbar' => ['/<!-- User Account: style can be found in dropdown\.less -->/', 'before'],
        // Top of the page content, before the notifications row.
        'content' => ['/<!-- Notifications -->/', 'before'],
        // Page scripts, before pages push their own.
        'scripts' => ['/@stack\(\'js\'\)/', 'before'],
    ];

    public function __invoke(string $source): string
    {
        $path = str_replace('\\', '/', (string) Blade::getPath());
        if (! Str::endsWith($path, self::TARGET) || str_contains($source, self::VERSION)) {
            return $source;
        }

        return self::apply($source);
    }

    public static function apply(string $source): string
    {
        foreach (self::ANCHORS as $zone => [$pattern, $position]) {
            $include = "@include('govscope::nav.zone', ['zone' => '{$zone}'])\n";
            $source = preg_replace_callback($pattern, fn ($m) => $position === 'before' ? $include.$m[0] : $m[0].$include, $source, 1);
        }

        // Body-end slot before the last </body>.
        if (str_contains($source, '</body>')) {
            $source = Str::replaceLast('</body>', "@include('govscope::nav.zone', ['zone' => 'body-end'])\n</body>", $source);
        }

        // Appended (not prepended) so theming's leading marker stays first. A PHP comment,
        // unlike a Blade comment, survives compilation, so compiled views can be checked.
        return $source.'<?php /* '.self::VERSION.' */ ?>';
    }
}
