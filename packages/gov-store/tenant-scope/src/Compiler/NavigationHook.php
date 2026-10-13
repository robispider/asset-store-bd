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
    public const VERSION = 'gov-nav-hook:v2';

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
        // Navbar, left: after the logo block (the working-office chip).
        'context' => ['/<!-- Navbar Right Menu -->/', 'before'],
        // Navbar: before the user account dropdown.
        'topbar' => ['/<!-- User Account: style can be found in dropdown\.less -->/', 'before'],
        // Top of the page content, before the notifications row.
        'content' => ['/<!-- Notifications -->/', 'before'],
        // Page scripts, before pages push their own.
        'scripts' => ['/@stack\(\'js\'\)/', 'before'],
    ];

    /**
     * Core navbar blocks that are switched at render time by config, not deleted: the original
     * markup stays in the compiled view behind the condition, so flipping the flag restores
     * stock Snipe-IT without recompiling.
     *
     * name => [regex for the whole block, 'replace' with a zone | 'wrap', config flag, zone|null]
     */
    public const REWRITES = [
        // Navbar "Create New" (Asset, License, …): replaced by the gov "＋ New" menu.
        'core-create' => [
            '/@can\(\'admin\'\)\s*<li class="dropdown user-menu"[^>]*>\s*<a [^>]*class="dropdown-toggle"[^>]*>\s*\{\{\s*trans\(\'general\.create\'\)\s*\}\}.*?<\/ul>\s*<\/li>\s*@endcan/s',
            'replace', 'govstore-navigation.topbar.replace_core_create', 'actions',
        ],
        // Navbar icon shortcuts (Assets … Users), up to the asset-tag search box.
        'core-shortcuts' => [
            '/@can\(\'index\',\s*\\\\App\\\\Models\\\\Asset::class\)\s*<li aria-hidden="true"\{!! \(request\(\)->is\(\'hardware\*\'\).*?(?=@can\(\'index\',\s*\\\\App\\\\Models\\\\Asset::class\)\s*<li>\s*<form)/s',
            'wrap', 'govstore-navigation.topbar.hide_core_shortcuts', null,
        ],
        // User menu "Requested assets" (Snipe-IT's request flow; gov uses My requests).
        'core-requested' => [
            '/@can\(\'viewRequestable\',\s*\\\\App\\\\Models\\\\Asset::class\)\s*<li \{!! \(request\(\)->is\(\'account\/requested\'\).*?@endcan/s',
            'wrap', 'govstore-navigation.user_menu.hide_core_requested', null,
        ],
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
        // Rewrites first: they must see upstream markup, before zones are inserted next to it.
        foreach (self::REWRITES as [$pattern, $mode, $flag, $zone]) {
            if (preg_match_all($pattern, $source) !== 1) {
                continue; // ambiguous or moved upstream: keep stock markup (contract test fails)
            }
            $source = preg_replace_callback($pattern, function ($m) use ($mode, $flag, $zone) {
                return $mode === 'replace'
                    ? "@if(config('{$flag}', true))\n@include('govscope::nav.zone', ['zone' => '{$zone}'])\n@else\n{$m[0]}\n@endif"
                    : "@unless(config('{$flag}', true))\n{$m[0]}\n@endunless\n";
            }, $source, 1);
        }

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
