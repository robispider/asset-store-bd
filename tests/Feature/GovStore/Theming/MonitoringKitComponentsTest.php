<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Lab\LabFixtures;
use Illuminate\Support\Facades\Blade;

class MonitoringKitComponentsTest extends ThemingTestCase
{
    public function test_pace_tick_and_accessible_actual_are_independent_and_clamped(): void
    {
        $html = Blade::render('<x-gs::pace-bar :value="140" :expected="35" label="Delivery" />');
        $this->assertStringContainsString('aria-valuenow="100"', $html);
        $this->assertStringContainsString('left: 35%', $html);
        $unknown = Blade::render('<x-gs::pace-bar />');
        $this->assertStringNotContainsString('role="progressbar"', $unknown);
    }

    public function test_compact_pace_keeps_the_full_explanation_and_default_caption_in_both_languages(): void
    {
        foreach (['en-US', 'bn-BD'] as $locale) {
            app()->setLocale($locale);
            $full = __('gs-theme::kit.expected').' 5.2% · '.__('gs-theme::kit.points', ['points' => '-2.9']);
            $compact = Blade::render('<x-gs::pace-bar compact :value="2.3" :expected="5.2" />');
            $this->assertStringContainsString('title="'.$full.'"', $compact);
            $this->assertStringContainsString('aria-label="'.$full.'"', $compact);
            $this->assertStringContainsString('5.2% · -2.9 pp</small>', $compact);
            $this->assertStringContainsString($full.'</small>', Blade::render('<x-gs::pace-bar :value="2.3" :expected="5.2" />'));
        }
    }

    public function test_all_monitoring_patterns_compile_and_retain_non_colour_status_and_table_alternatives(): void
    {
        $f = (new LabFixtures)->monitoring();
        $templates = [
            '<x-gs::attention-list :items="$attention" />',
            '<x-gs::tile-map :tiles="$tiles" />',
            '<x-gs::price-strip :prices="$prices" />',
            '<x-gs::lifecycle :steps="$steps" />',
            '<x-gs::status-callout status="stalled" verdict="No receipt" next="Call the head" forecast="2027-06-30" />',
            '<x-gs::scope-why :reasons="[\'Programme membership\']" />',
            '<x-gs::level-switch :options="[[\'label\'=>\'All programmes\',\'locked\'=>true,\'reason\'=>\'Office scope required\']]" />',
        ];
        foreach ($templates as $template) {
            $html = Blade::render($template, $f);
            $this->assertStringNotContainsString('<x-gs::', $html);
            $this->assertStringNotContainsString('gs-theme::', $html);
        }
        $prices = Blade::render('<x-gs::price-strip :prices="$prices" />', $f);
        $this->assertStringContainsString('<table>', $prices);
        $this->assertStringContainsString('role="img"', $prices);
        $this->assertStringContainsString('data-status="stalled"', Blade::render($templates[4]));
    }

    public function test_scope_panels_escape_untrusted_names_and_locked_levels_have_no_link(): void
    {
        $html = Blade::render('<x-gs::scope-why :reasons="$reasons" />', ['reasons' => ['<script>bad()</script>']]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $locked = Blade::render('<x-gs::level-switch :options="$options" />', ['options' => [['label' => 'All', 'locked' => true, 'reason' => 'Office access required', 'href' => '/hidden']]]);
        $this->assertStringNotContainsString('href="/hidden"', $locked);
        $this->assertStringContainsString('Office access required', $locked);
    }

    public function test_new_kit_translations_have_matching_english_and_bangla_keys(): void
    {
        $en = require base_path('packages/gov-store/theming/lang/en-US/kit.php');
        $bn = require base_path('packages/gov-store/theming/lang/bn-BD/kit.php');
        $this->assertSame(array_keys($en), array_keys($bn));
        $this->assertSame(array_keys($en['severity']), array_keys($bn['severity']));
    }
}
