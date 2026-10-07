<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Build\ThemeValidator;
use GovStore\Theming\Themes\ThemeRepository;
use Illuminate\Support\Facades\Blade;

/** The general-purpose UI kit (§14.2a) and the layout variants added with the Lavender theme. */
class GeneralKitComponentsTest extends ThemingTestCase
{
    public function test_lavender_uses_the_new_variants_and_writes_them_to_html(): void
    {
        $theme = (new ThemeRepository(base_path('packages/gov-store/theming/themes')))->find('lavender');

        $attributes = $theme->variantAttributes();
        $this->assertSame('floating', $attributes['data-gs-header']);
        $this->assertSame('pill', $attributes['data-gs-nav-style']);
        $this->assertSame('elevated', $attributes['data-gs-surfaces']);
        $this->assertSame('filled', $attributes['data-gs-controls']);
        $this->assertSame('light', $attributes['data-gs-sidebar']);
    }

    public function test_every_theme_resolves_the_new_variants_through_inheritance(): void
    {
        foreach ((new ThemeRepository(base_path('packages/gov-store/theming/themes')))->all() as $key => $theme) {
            $this->assertContains($theme->variants['nav_style'] ?? null, ThemeValidator::VARIANTS['nav_style'], $key);
            $this->assertContains($theme->variants['controls'] ?? null, ThemeValidator::VARIANTS['controls'], $key);
        }
    }

    public function test_buttons_render_tones_shapes_links_and_loading(): void
    {
        $html = Blade::render('<x-gs::button tone="danger" outline pill size="sm" icon="fa-trash">Delete</x-gs::button>');
        $this->assertMatchesRegularExpression('/<button[^>]*type="button"/', $html);
        $this->assertStringContainsString('class="gs-btn gs-btn--danger gs-btn--outline gs-btn--pill gs-btn--sm"', $html);
        $this->assertStringContainsString('fa-trash', $html);

        $link = Blade::render('<x-gs::button href="/hardware">Assets</x-gs::button>');
        $this->assertMatchesRegularExpression('/<a[^>]*href="\/hardware"/', $link);
        $this->assertStringNotContainsString('<button', $link);

        $loading = Blade::render('<x-gs::button loading>Saving</x-gs::button>');
        $this->assertStringContainsString('aria-busy="true"', $loading);
        $this->assertStringContainsString('disabled', $loading);
        $this->assertStringContainsString('gs-spinner', $loading);
        $this->assertStringContainsString('aria-hidden="true"', $loading); // the button text already says what is happening

        $group = Blade::render('<x-gs::button-group label="View"><x-gs::button>A</x-gs::button></x-gs::button-group>');
        $this->assertMatchesRegularExpression('/role="group"\s+aria-label="View"/', $group);
    }

    public function test_badge_is_separate_from_status_badge(): void
    {
        $html = Blade::render('<x-gs::badge tone="danger" floating label="unread">99+</x-gs::badge>');
        $this->assertStringContainsString('gs-tag gs-tag--danger gs-tag--pill gs-tag--floating', $html);
        $this->assertStringContainsString('<span class="gs-sr-only"> unread</span>', $html);
        $this->assertStringNotContainsString('gs-badge', $html);
    }

    public function test_spinner_and_progress_are_accessible(): void
    {
        $spinner = Blade::render('<x-gs::spinner type="dots" tone="success" />');
        $this->assertStringContainsString('role="status"', $spinner);
        $this->assertStringContainsString(__('gs-theme::appearance.kit.loading'), $spinner);
        $this->assertSame(3, substr_count($spinner, '<span></span>'));

        $progress = Blade::render('<x-gs::progress label="Stock counted" :value="7" :max="12" />');
        $this->assertStringContainsString('role="progressbar"', $progress);
        $this->assertStringContainsString('aria-valuenow="7"', $progress);
        $this->assertStringContainsString('aria-valuemax="12"', $progress);
        $this->assertStringContainsString('width: 58%', $progress);
        $this->assertStringContainsString('>58%<', $progress);
    }

    public function test_accordion_items_share_the_exclusive_group_name(): void
    {
        $html = Blade::render('<x-gs::accordion name="faq"><x-gs::accordion-item title="One" open>A</x-gs::accordion-item><x-gs::accordion-item title="Two">B</x-gs::accordion-item></x-gs::accordion>');
        $this->assertSame(2, substr_count($html, 'name="faq"'));
        $this->assertSame(1, substr_count($html, ' open'));
        $this->assertStringContainsString('<summary class="gs-accordion__summary">', $html);
    }

    public function test_tabs_wire_aria_between_tabs_and_panels(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-gs::tabs id="t1" :tabs="[['key' => 'a', 'label' => 'First'], ['key' => 'b', 'label' => 'Second']]" active="b">
                <x-gs::tab-panel for="a">Panel A</x-gs::tab-panel>
                <x-gs::tab-panel for="b">Panel B</x-gs::tab-panel>
            </x-gs::tabs>
            BLADE);
        $this->assertMatchesRegularExpression('/id="t1-tab-b"\s+aria-controls="t1-panel-b" aria-selected="true" tabindex="0"/', $html);
        $this->assertMatchesRegularExpression('/id="t1-tab-a"\s+aria-controls="t1-panel-a" aria-selected="false" tabindex="-1"/', $html);
        $this->assertMatchesRegularExpression('/id="t1-panel-a" aria-labelledby="t1-tab-a"\s+tabindex="0"\s+hidden/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="t1-panel-b"[^>]*hidden/', $html);
    }

    public function test_kpi_layouts_sparkline_and_progress(): void
    {
        $html = Blade::render('<x-gs::kpi label="Traffic" value="13,000" tone="success" layout="icon-left" icon="fa-users" :spark="[1, 3, 2]" :progress="40" caption="Last 24h" />');
        $this->assertStringContainsString('gs-kpi--success', $html);
        $this->assertStringContainsString('gs-kpi--icon-left', $html);
        $this->assertStringContainsString('gs-kpi__icon--lg', $html);
        $this->assertStringContainsString('<polyline points="0,28 50,4 100,16"', $html);
        $this->assertStringContainsString('width: 40%', $html);
        $this->assertStringContainsString('Last 24h', $html);

        $plain = Blade::render('<x-gs::kpi label="Assets" value="12" icon="fa-box" />');
        $this->assertStringNotContainsString('<svg', $plain);
        $this->assertStringNotContainsString('gs-kpi__icon--lg', $plain);
    }

    public function test_card_tile_list_avatar_and_alert_appearances(): void
    {
        $card = Blade::render('<x-gs::card header="Featured" title="Title" align="center">Body<x-slot:actions><a href="#">Go</a></x-slot:actions></x-gs::card>');
        $this->assertStringContainsString('gs-card gs-card--center', $card);
        $this->assertStringContainsString('<div class="gs-card__header">Featured</div>', $card);
        $this->assertStringContainsString('gs-card__actions', $card);

        $tile = Blade::render('<x-gs::tile filled title="Backups" meta="Total: 32" icon="fa-database" href="#b" />');
        $this->assertMatchesRegularExpression('/<a[^>]*gs-tile gs-tone-primary gs-tile--filled gs-tile--link/', $tile);

        $avatar = Blade::render('<x-gs::avatar name="Rahima Akter" />');
        $this->assertStringContainsString('RA', $avatar);
        $this->assertStringContainsString('aria-hidden="true"', $avatar);

        $list = Blade::render('<x-gs::list><x-gs::list-item title="Toner" subtitle="Consumable" avatar="Md Karim"><x-slot:meta>412</x-slot:meta></x-gs::list-item></x-gs::list>');
        $this->assertStringContainsString('role="list"', $list);
        $this->assertStringContainsString('MK', $list);
        $this->assertStringContainsString('<div class="gs-list__meta">412</div>', $list);

        $alert = Blade::render('<x-gs::alert tone="danger" appearance="card">Oops</x-gs::alert>');
        $this->assertStringContainsString('gs-alert gs-alert--danger gs-alert--card', $alert);
        $this->assertStringNotContainsString('gs-alert--tinted', Blade::render('<x-gs::alert>Hi</x-gs::alert>'));
    }
}
