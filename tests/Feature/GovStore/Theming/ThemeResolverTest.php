<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Models\ThemeAssignment;
use GovStore\Theming\Models\ThemePreference;
use GovStore\Theming\ThemeManager;
use GovStore\Theming\Themes\ThemeResolver;
use Illuminate\Http\Request;

class ThemeResolverTest extends ThemingTestCase
{
    private function assign(string $scope, ?int $id, string $theme, bool $enforced = false): void
    {
        ThemeAssignment::create(['scope_type' => $scope, 'scope_id' => $id, 'theme' => $theme, 'enforced' => $enforced]);
        ThemeResolver::forgetAssignment($scope, $id);
    }

    private function resolve(?object $user, ?int $company = self::COMPANY, ?int $location = self::LOCATION, ?array $preview = null): array
    {
        return app(ThemeResolver::class)->resolve($user, $company, $location, $preview);
    }

    public function test_config_default_then_classic(): void
    {
        $user = $this->actor();
        $this->assertSame(['institutional-green', 'config'], [$this->resolve($user)['key'], $this->resolve($user)['source']]);
        config(['gs-theme.default' => 'missing-theme']);
        $this->assertSame(['default', 'default'], [$this->resolve($user)['key'], $this->resolve($user)['source']]);
    }

    public function test_bottom_up_defaults_office_beats_company_beats_organisation(): void
    {
        $user = $this->actor();
        $this->assign('organization', null, 'executive-neutral');
        $this->assertSame('organization', $this->resolve($user)['source']);
        $this->assign('company', self::COMPANY, 'digital-blue');
        $this->assertSame(['digital-blue', 'company'], [$this->resolve($user)['key'], $this->resolve($user)['source']]);
        $this->assign('office', self::LOCATION, 'default');
        $this->assertSame(['default', 'office'], [$this->resolve($user)['key'], $this->resolve($user)['source']]);
    }

    public function test_user_preference_beats_defaults_but_not_enforcement(): void
    {
        $user = $this->actor();
        ThemePreference::create(['user_id' => $user->id, 'theme' => 'executive-neutral', 'mode' => 'dark']);
        $this->assign('office', self::LOCATION, 'digital-blue');
        $this->assertSame(['executive-neutral', 'user', 'dark'], [$this->resolve($user)['key'], $this->resolve($user)['source'], $this->resolve($user)['mode']]);

        $this->assign('company', self::COMPANY, 'institutional-green', true);
        $resolved = $this->resolve($user);
        $this->assertSame(['institutional-green', 'enforced', 'company'], [$resolved['key'], $resolved['source'], $resolved['enforced_by']]);
        $this->assertSame('dark', $resolved['mode']); // scopes assign themes, never modes
    }

    public function test_top_down_enforcement_organisation_beats_office(): void
    {
        $user = $this->actor();
        $this->assign('office', self::LOCATION, 'digital-blue', true);
        $this->assertSame('office', $this->resolve($user)['enforced_by']);
        $this->assign('organization', null, 'executive-neutral', true);
        $this->assertSame(['executive-neutral', 'organization'], [$this->resolve($user)['key'], $this->resolve($user)['enforced_by']]);
    }

    public function test_working_office_switch_changes_default_unless_user_chose(): void
    {
        $user = $this->actor();
        $this->assign('office', self::LOCATION, 'digital-blue');
        $this->assign('office', 11, 'executive-neutral');
        $this->assertSame('digital-blue', $this->resolve($user, self::COMPANY, self::LOCATION)['key']);
        $this->assertSame('executive-neutral', $this->resolve($user, self::COMPANY, 11)['key']);
        ThemePreference::create(['user_id' => $user->id, 'theme' => 'default']);
        $this->assertSame('default', $this->resolve($user, self::COMPANY, 11)['key']);
    }

    public function test_guests_get_organisation_then_config(): void
    {
        $this->assign('office', self::LOCATION, 'digital-blue', true);
        $this->assertSame(['institutional-green', 'config'], [$this->resolve(null)['key'], $this->resolve(null)['source']]);
        $this->assign('organization', null, 'executive-neutral');
        $this->assertSame('executive-neutral', $this->resolve(null)['key']);
        $this->assertSame('system', $this->resolve(null)['mode']);
    }

    public function test_allow_user_choice_off_ignores_preference(): void
    {
        $user = $this->actor();
        ThemePreference::create(['user_id' => $user->id, 'theme' => 'executive-neutral', 'mode' => 'light']);
        config(['gs-theme.allow_user_choice' => false]);
        $resolved = $this->resolve($user);
        $this->assertSame('institutional-green', $resolved['key']);
        $this->assertSame('light', $resolved['mode']);
    }

    public function test_draft_deprecated_and_removed_themes_fall_through(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'drafty', ['status' => 'draft']);
        $this->writeTheme($dir, 'old', ['status' => 'deprecated']);
        $this->useThemesPath($dir);
        $user = $this->actor();
        ThemePreference::create(['user_id' => $user->id, 'theme' => 'drafty']);
        $this->assign('office', self::LOCATION, 'old');
        $this->assign('company', self::COMPANY, 'removed-in-release', true);
        $this->assign('organization', null, 'digital-blue');
        $this->assertSame(['digital-blue', 'organization'], [$this->resolve($user)['key'], $this->resolve($user)['source']]);
        // Lab preview may show a draft.
        $preview = $this->resolve($user, preview: ['key' => 'drafty', 'mode' => 'dark']);
        $this->assertSame(['drafty', 'preview', 'dark'], [$preview['key'], $preview['source'], $preview['mode']]);
    }

    public function test_preview_query_is_honoured_only_with_lab_ability(): void
    {
        $this->actor('employee', 5);
        app()->instance('request', Request::create('/gov/appearance', 'GET', ['gs_preview' => 'executive-neutral', 'gs_mode' => 'dark']));
        $manager = app(ThemeManager::class);
        $manager->forget();
        $this->assertSame('institutional-green', $manager->key());

        $this->actor('superuser', 6);
        $manager->forget();
        $this->assertSame(['executive-neutral', 'dark'], [$manager->key(), $manager->mode()]);
        $this->assertStringContainsString('data-skin="executive-neutral"', $manager->htmlAttributes());
        $this->assertStringContainsString('data-theme="dark"', $manager->htmlAttributes());
        $this->assertStringContainsString('data-gs-table="ledger"', $manager->htmlAttributes());
        $this->assertSame(0, ThemePreference::count()); // previews are never saved
    }

    public function test_assignments_are_cached_and_busted_on_forget(): void
    {
        $user = $this->actor();
        $this->assign('office', self::LOCATION, 'digital-blue');
        $this->assertSame('digital-blue', $this->resolve($user)['key']);
        ThemeAssignment::query()->update(['theme' => 'executive-neutral']);
        $this->assertSame('digital-blue', $this->resolve($user)['key']); // cached
        ThemeResolver::forgetAssignment('office', self::LOCATION);
        $this->assertSame('executive-neutral', $this->resolve($user)['key']);
    }

    public function test_html_attributes_carry_skin_mode_and_variants_and_never_throw(): void
    {
        $user = $this->actor();
        ThemePreference::create(['user_id' => $user->id, 'theme' => 'digital-blue', 'mode' => 'system']);
        $html = app(ThemeManager::class)->htmlAttributes();
        $this->assertStringContainsString('data-skin="digital-blue"', $html);
        $this->assertStringContainsString('data-theme="light"', $html);
        $this->assertStringContainsString('data-gs-mode="system"', $html);
        $this->assertStringContainsString('data-gs-status-style="chip"', $html);
        $this->assertStringContainsString('data-gs-nav-icons="shown"', $html);

        \Illuminate\Support\Facades\Schema::drop('gs_theme_preferences');
        \Illuminate\Support\Facades\Schema::drop('gs_theme_assignments');
        app(ThemeManager::class)->forget();
        $this->assertStringContainsString('data-skin="institutional-green"', app(ThemeManager::class)->htmlAttributes());
    }
}
