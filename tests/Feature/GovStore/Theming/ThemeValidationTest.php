<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Build\FontRegistry;
use GovStore\Theming\Build\ThemeValidator;
use GovStore\Theming\Themes\ThemeRepository;

class ThemeValidationTest extends ThemingTestCase
{
    private function validate(string $dir, string $key): array
    {
        return (new ThemeValidator(new ThemeRepository($dir), new FontRegistry(base_path('packages/gov-store/theming/fonts'))))->validate($key);
    }

    private function checks(array $issues, string $level = 'error'): array
    {
        return array_values(array_unique(array_map(fn ($i) => $i['check'], array_filter($issues, fn ($i) => $i['level'] === $level))));
    }

    public function test_every_shipped_theme_passes_in_both_modes(): void
    {
        $validator = app(ThemeValidator::class);
        foreach ($validator->validateAll() as $key => $issues) {
            $this->assertFalse(ThemeValidator::hasErrors($issues), $key.': '.json_encode(array_filter($issues, fn ($i) => $i['level'] === 'error')));
            $this->assertNotContains('dark-authored', $this->checks($issues, 'warning'), $key.' still has a scaffold dark mode');
        }
    }

    public function test_a_good_child_theme_passes(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'civic-teal', ['extends' => 'institutional-green'],
            ['seed' => ['$type' => 'color', 'primary' => ['$value' => '#115E59']]],
            ['seed' => ['$type' => 'color', 'primary' => ['$value' => '#5EEAD4']]]);
        $this->assertSame([], $this->checks($this->validate($dir, 'civic-teal')));
    }

    public function test_schema_failures(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'bad', ['key' => 'other', 'version' => 'one', 'status' => 'live', 'variants' => ['table' => 'spreadsheet', 'wobble' => 'yes'], 'colour' => 1, 'label' => ['en-US' => 'Bad']]);
        $messages = implode("\n", array_column($this->validate($dir, 'bad'), 'message'));
        foreach (['key must equal', 'semver', 'status must be', 'variant table must be', 'unknown variant [wobble]', 'unknown theme.json key [colour]', 'label requires [bn-BD]'] as $expected) {
            $this->assertStringContainsString($expected, $messages);
        }
    }

    public function test_missing_mode_file_fails(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'nodark', [], [], null);
        $this->assertContains('modes', $this->checks($this->validate($dir, 'nodark')));
    }

    public function test_unresolved_contract_token_and_bad_reference_fail(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'root', ['extends' => null], ['color' => ['$type' => 'color', 'text' => ['$value' => '{color.nothing}']]], []);
        $checks = $this->checks($this->validate($dir, 'root'));
        $this->assertContains('contract', $checks);
        $this->assertContains('references', $checks);
        $this->assertContains('schema', $checks); // only default may extend null
    }

    public function test_contrast_failure_in_either_mode(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'pale', [], [], ['color' => ['$type' => 'color', 'text-muted' => ['$value' => '#4A4E52']]]);
        $issues = $this->validate($dir, 'pale');
        $contrast = array_values(array_filter($issues, fn ($i) => $i['check'] === 'contrast'));
        $this->assertNotEmpty($contrast);
        $this->assertSame('dark', $contrast[0]['mode']);
        $this->assertStringContainsString('color.text-muted on color.surface', $contrast[0]['message']);
    }

    public function test_fonts_must_exist_and_include_bengali(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'fonty', ['fonts' => ['sans' => 'comic-sans', 'bengali' => 'inter']]);
        $messages = implode("\n", array_column($this->validate($dir, 'fonty'), 'message'));
        $this->assertStringContainsString('unknown font key [comic-sans]', $messages);
        $this->assertStringContainsString('must use a Bengali font', $messages);
    }

    public function test_overrides_css_lint(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'loud', [], [], [], '.box { color: #ff0000 !important; }');
        $messages = implode("\n", array_column(array_filter($this->validate($dir, 'loud'), fn ($i) => $i['check'] === 'overrides'), 'message'));
        $this->assertStringContainsString('!important', $messages);
        $this->assertStringContainsString('literal colours', $messages);
        $this->assertStringContainsString('@layer gs-theme', $messages);
        $this->assertStringContainsString('selector [.box]', $messages);
    }

    public function test_previews_required_only_for_published(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'nopic', [], [], [], null, false);
        $this->writeTheme($dir, 'nopic-draft', ['status' => 'draft'], [], [], null, false);
        $this->assertContains('previews', $this->checks($this->validate($dir, 'nopic')));
        $this->assertNotContains('previews', $this->checks($this->validate($dir, 'nopic-draft')));
    }

    public function test_scaffold_dark_mode_is_an_error_when_published_and_a_warning_as_draft(): void
    {
        $dir = $this->copyShippedThemes();
        $scaffold = ['$extensions' => ['gs.scaffold' => true], 'color' => ['$type' => 'color', 'bg' => ['$value' => '#DDDDDD'], 'surface' => ['$value' => '#EEEEEE'], 'text' => ['$value' => '#111111'], 'text-muted' => ['$value' => '#333333']]];
        $this->writeTheme($dir, 'scaffolded', [], [], $scaffold);
        $this->writeTheme($dir, 'scaffolded-draft', ['status' => 'draft'], [], $scaffold);
        $this->assertContains('dark-authored', $this->checks($this->validate($dir, 'scaffolded')));
        $this->assertContains('dark-authored', $this->checks($this->validate($dir, 'scaffolded-draft'), 'warning'));
        $this->assertNotContains('dark-authored', $this->checks($this->validate($dir, 'scaffolded-draft')));
    }

    public function test_make_command_scaffolds_a_draft_with_marked_dark_mode(): void
    {
        $dir = $this->copyShippedThemes();
        $this->useThemesPath($dir);
        $this->artisan('gs-theme:make', ['key' => 'civic-teal', '--from' => 'institutional-green'])->assertSuccessful();
        $manifest = json_decode(file_get_contents($dir.'/civic-teal/theme.json'), true);
        $dark = json_decode(file_get_contents($dir.'/civic-teal/tokens.dark.json'), true);
        $this->assertSame('draft', $manifest['status']);
        $this->assertSame('compact', $manifest['variants']['density']);
        $this->assertTrue($dark['$extensions']['gs.scaffold']);
        $issues = $this->validate($dir, 'civic-teal');
        $this->assertFalse(ThemeValidator::hasErrors($issues), json_encode($issues));
        $this->assertContains('dark-authored', $this->checks($issues, 'warning'));
    }
}
