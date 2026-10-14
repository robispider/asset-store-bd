<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Build\FontRegistry;
use GovStore\Theming\Build\ThemeValidator;
use GovStore\Theming\Themes\ThemeRepository;
use Illuminate\Support\Facades\Blade;

/** The workflow kit (readiness, role slot, action row, choice card, trail), office statuses and Civic Ledger. */
class WorkflowKitComponentsTest extends ThemingTestCase
{
    public function test_readiness_counts_only_required_items_and_offers_the_fix(): void
    {
        $html = Blade::render('<x-gs::readiness :items="$items" />', ['items' => [
            ['label' => 'Administrator', 'value' => 'Mahbub Alam', 'done' => true],
            ['label' => 'Storekeeper', 'done' => false, 'action' => ['label' => 'Choose', 'href' => '/office/1/roles']],
            ['label' => 'Second approver', 'done' => false, 'optional' => true],
        ]]);

        $this->assertStringContainsString(__('gs-theme::appearance.kit.readiness_count', ['done' => 1, 'total' => 2]), $html);
        $this->assertStringContainsString('aria-valuenow="1"', $html);
        $this->assertStringContainsString('aria-valuemax="2"', $html);
        $this->assertMatchesRegularExpression('/<a[^>]*href="\/office\/1\/roles"[^>]*>\s*Choose/', $html);
        $this->assertStringContainsString('gs-readiness__item--optional', $html);
        $this->assertStringNotContainsString('gs-readiness--complete', $html);
    }

    public function test_readiness_is_complete_and_readonly_without_actions(): void
    {
        $html = Blade::render('<x-gs::readiness readonly :items="$items" />', ['items' => [
            ['label' => 'Administrator', 'done' => true],
            ['label' => 'Storekeeper', 'done' => false, 'action' => ['label' => 'Choose', 'href' => '/x']],
        ]]);
        $this->assertStringNotContainsString('href="/x"', $html);

        $complete = Blade::render('<x-gs::readiness :items="[[\'label\' => \'A\', \'done\' => true]]" />');
        $this->assertStringContainsString('gs-readiness--complete', $complete);
        $this->assertStringContainsString('gs-tone-success', $complete);
    }

    public function test_role_slot_says_in_words_when_a_required_role_is_missing(): void
    {
        $empty = Blade::render('<x-gs::role-slot role="Storekeeper" required action-label="Choose" action-href="/pick" />');
        $this->assertStringContainsString('gs-role--empty gs-role--required', $empty);
        $this->assertStringContainsString(__('gs-theme::appearance.kit.needed'), $empty);
        $this->assertMatchesRegularExpression('/<a[^>]*href="\/pick"/', $empty);

        $filled = Blade::render('<x-gs::role-slot role="Approver" person="Farhana Akter" meta="RMO" />');
        $this->assertStringContainsString('Farhana Akter', $filled);
        $this->assertStringContainsString('gs-avatar', $filled);
        $this->assertStringNotContainsString('gs-role--empty', $filled);
    }

    public function test_action_row_explains_why_an_action_is_unavailable(): void
    {
        $html = Blade::render('<x-gs::action-row id="close" title="Close office" unavailable="Return all items first."><x-slot:actions><button disabled aria-describedby="close-why">Close</button></x-slot:actions></x-gs::action-row>');
        $this->assertStringContainsString('id="close-why"', $html);
        $this->assertStringContainsString('Return all items first.', $html);
        $this->assertStringContainsString('gs-action-row--unavailable', $html);
    }

    public function test_choice_card_keeps_a_native_labelled_input(): void
    {
        $html = Blade::render('<x-gs::choice-card name="office_type" value="hospital" label="Hospital" description="Health complexes" checked />');
        $this->assertMatchesRegularExpression('/<label[^>]*for="office_type-hospital"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="radio"[^>]*id="office_type-hospital"[^>]*name="office_type"[^>]*value="hospital"[^>]*checked/', $html);
        $this->assertStringContainsString('aria-describedby="office_type-hospital-desc"', $html);
    }

    public function test_trail_separators_are_hidden_from_assistive_technology(): void
    {
        $html = Blade::render('<x-gs::trail :items="[[\'label\' => \'Dhaka Division\', \'bn\' => \'ঢাকা বিভাগ\'], \'Savar Upazila\']" />');
        $this->assertSame(2, substr_count($html, 'gs-trail__part'));
        $this->assertStringContainsString('lang="bn"', $html);
        $this->assertMatchesRegularExpression('/gs-trail__sep" aria-hidden="true"/', $html);
    }

    public function test_office_lifecycle_statuses_have_icons_and_labels_in_both_languages(): void
    {
        foreach (['unconfigured', 'provisioned', 'configured', 'operational', 'suspended'] as $state) {
            $entry = app('gs.theme')->status('office_'.$state);
            $this->assertNotSame('fa-circle', $entry['icon'], $state);
            foreach (['en-US', 'bn-BD'] as $locale) {
                $key = 'gs-theme::appearance.status.office_'.$state;
                $this->assertNotSame($key, __($key, [], $locale), "{$state} [{$locale}]");
            }
        }
    }

    public function test_civic_ledger_is_a_valid_two_mode_theme(): void
    {
        $themes = new ThemeRepository(base_path('packages/gov-store/theming/themes'));
        $theme = $themes->find('civic-ledger');
        $this->assertNotNull($theme);
        $this->assertSame('lg', $theme->variants['radius']);

        $validator = new ThemeValidator($themes, new FontRegistry(base_path('packages/gov-store/theming/fonts')));
        $errors = collect($validator->validate('civic-ledger'))->where('level', 'error');
        $this->assertCount(0, $errors, $errors->pluck('message')->implode("\n"));
    }
}
