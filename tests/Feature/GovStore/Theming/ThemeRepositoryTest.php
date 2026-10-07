<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Build\FontRegistry;
use GovStore\Theming\Themes\ThemeRepository;

class ThemeRepositoryTest extends ThemingTestCase
{
    public function test_shipped_themes_are_discovered_and_published(): void
    {
        $repo = new ThemeRepository(base_path('packages/gov-store/theming/themes'));
        $this->assertSame(['default', 'digital-blue', 'executive-neutral', 'institutional-green'], array_keys($repo->all()));
        $this->assertSame(array_keys($repo->all()), array_keys($repo->published()));
        $this->assertSame([], $repo->loadErrors());
        $this->assertSame('Institutional Green', $repo->find('institutional-green')->label('en-US'));
        $this->assertSame('প্রাতিষ্ঠানিক সবুজ', $repo->find('institutional-green')->label('bn-BD'));
    }

    public function test_extends_merges_tokens_by_path_variants_and_fonts_shallow_and_overrides_parent_first(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'child', [
            'extends' => 'institutional-green',
            'variants' => ['table' => 'ledger'],
            'fonts' => ['mono' => 'system-mono'],
        ], ['seed' => ['$type' => 'color', 'primary' => ['$value' => '#7A1F5C']]], [], "@layer gs-theme { .gs-box { border-width: 2px; } }");
        file_put_contents($dir.'/institutional-green/overrides.css', "@layer gs-theme { .gs-kpi { gap: 2px; } }");
        $repo = new ThemeRepository($dir);
        $child = $repo->find('child');
        $fonts = new FontRegistry(base_path('packages/gov-store/theming/fonts'));

        $this->assertSame(['child', 'institutional-green', 'default'], $child->chain);
        $this->assertSame('ledger', $child->variants['table']);
        $this->assertSame('compact', $child->variants['density']); // from institutional-green
        $this->assertSame('system-mono', $child->fonts['mono']);
        $this->assertSame('noto-sans', $child->fonts['sans']);
        $light = $child->tokens('light', $fonts);
        $this->assertSame('#7A1F5C', $light['color.primary']);            // child seed flows through default's reference
        $this->assertSame('#F7F9F8', $light['color.bg']);                 // parent semantic kept
        $this->assertLessThan(strpos($child->overridesCss, '.gs-box'), strpos($child->overridesCss, '.gs-kpi'));
        // label / description / previews never inherit
        $this->assertSame('Child', $child->label('en-US'));
    }

    public function test_dark_mode_starts_from_light_tree_with_dark_files_on_top(): void
    {
        $repo = new ThemeRepository(base_path('packages/gov-store/theming/themes'));
        $fonts = new FontRegistry(base_path('packages/gov-store/theming/fonts'));
        $dark = $repo->find('institutional-green')->tokens('dark', $fonts);
        $this->assertSame('#0E1511', $dark['color.bg']);
        $this->assertSame('38px', $dark['size.row-h']);          // dimensions inherited from the light tree
        $this->assertSame('#4ADE80', $dark['color.primary']);    // dark seed
        $this->assertSame('#103A22', $dark['header.bg']);        // dark file wins over light reference
        $this->assertSame('#052E16', $dark['color.on-primary']);
    }

    public function test_depth_and_cycle_errors(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'a', ['extends' => 'b']);
        $this->writeTheme($dir, 'b', ['extends' => 'a']);
        $this->writeTheme($dir, 'l1', ['extends' => 'default']);
        $this->writeTheme($dir, 'l2', ['extends' => 'l1']);
        $this->writeTheme($dir, 'l3', ['extends' => 'l2']);
        $this->writeTheme($dir, 'l4', ['extends' => 'l3']);
        $this->writeTheme($dir, 'orphan', ['extends' => 'nope']);
        $repo = new ThemeRepository($dir);
        $errors = $repo->loadErrors();
        $this->assertStringContainsString('cycle', $errors['a'][0]);
        $this->assertStringContainsString('depth', $errors['l4'][0]);
        $this->assertArrayNotHasKey('l3', $errors);
        $this->assertStringContainsString('unknown theme', $errors['orphan'][0]);
        $this->assertNull($repo->find('a'));
    }

    public function test_status_filtering(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'drafty', ['status' => 'draft']);
        $this->writeTheme($dir, 'old', ['status' => 'deprecated']);
        $repo = new ThemeRepository($dir);
        $this->assertNotNull($repo->find('drafty'));
        $this->assertFalse($repo->isSelectable('drafty'));
        $this->assertFalse($repo->isSelectable('old'));
        $this->assertTrue($repo->isSelectable('digital-blue'));
        $this->assertFalse($repo->isSelectable('missing'));
    }
}
