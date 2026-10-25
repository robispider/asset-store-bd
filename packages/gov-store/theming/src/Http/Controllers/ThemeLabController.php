<?php

namespace GovStore\Theming\Http\Controllers;

use GovStore\Theming\Access\ThemeAccess;
use GovStore\Theming\Build\Contract;
use GovStore\Theming\Build\ThemeValidator;
use GovStore\Theming\Lab\GeoLabFixture;
use GovStore\Theming\Lab\LabFixtures;
use GovStore\Theming\Lab\LabHealth;
use GovStore\Theming\ThemeManager;
use GovStore\Theming\Themes\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Developer tool: review every token, core surface, kit component and pattern of the shipped
 * themes in light and dark. Static fixture data only — safe in production (super admin).
 */
class ThemeLabController extends Controller
{
    public function __construct(private ThemeManager $manager, private ThemeAccess $access) {}

    /** Workspace tabs → the lab sections each one shows ("colour" is the two-mode token table). */
    public const TABS = [
        'overview' => ['shell', 'core'],
        'colour' => [],
        'type' => ['type', 'shape'],
        'components' => ['kit', 'general'],
        'patterns' => ['patterns'],
        'charts' => ['charts'],
        'print' => ['print'],
        'maps' => ['monitoring'],
    ];

    /** Tune panel grouping of ThemeValidator::VARIANTS. */
    public const VARIANT_GROUPS = [
        'shell' => ['header', 'sidebar', 'nav_style', 'nav_icons'],
        'layout' => ['density', 'surfaces', 'radius'],
        'content' => ['table', 'controls', 'type', 'status_style'],
    ];

    /** Workspace for the viewer's own theme. */
    public function index(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $all = $this->manager->repository()->all();

        return $this->workspace($request, $all[$this->manager->key()] ?? reset($all));
    }

    /** Workspace for one theme. The page keeps the viewer's theme; only the preview panels use $theme. */
    public function focus(Request $request, string $theme)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $target = $this->manager->repository()->find($theme);
        abort_unless($target, 404);

        return $this->workspace($request, $target);
    }

    public function compare(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $all = $this->manager->repository()->all();
        $picked = array_values(array_intersect((array) $request->query('themes', []), array_keys($all))) ?: array_slice(array_keys($all), 0, 3);

        return view('gs-theme::lab.compare', [
            'all' => $all,
            'picked' => $picked,
            'mode' => $this->option($request, 'mode', ['light', 'dark', 'both']),
            'validation' => app(ThemeValidator::class)->validateAll(),
            'fixtures' => new LabFixtures,
        ]);
    }

    public function health(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $all = $this->manager->repository()->all();
        $validation = app(ThemeValidator::class)->validateAll();
        $report = (new LabHealth($this->manager->fonts()))->report($all, $validation);
        $mode = $this->option($request, 'mode', LabHealth::MODES);
        $theme = isset($all[$request->query('theme')]) ? $request->query('theme') : null;
        // Open on the first failure (in the requested theme when given), else the first cell.
        $selected = null;
        foreach ($report['modes'][$mode]['rows'] as $row) {
            foreach ($row['cells'] as $key => $cell) {
                if ((! $theme || $key === $theme) && ! $cell['pass']) {
                    $selected = $mode.'|'.$key.'|'.$row['slug'];
                    break 2;
                }
            }
        }
        $first = $report['modes'][$mode]['rows'][0]['slug'] ?? null;

        return view('gs-theme::lab.health', [
            'all' => $all,
            'report' => $report,
            'validation' => $validation,
            'mode' => $mode,
            'show' => $this->option($request, 'show', ['all', 'problems']),
            'selected' => $selected ?? ($first ? $mode.'|'.($theme ?? array_key_first($all)).'|'.$first : null),
        ]);
    }

    private function workspace(Request $request, Theme $theme)
    {
        $overrides = array_intersect_key((array) $request->query('variant', []), ThemeValidator::VARIANTS);
        $overrides = array_filter($overrides, fn ($value, $name) => in_array($value, ThemeValidator::VARIANTS[$name], true) && $value !== ($theme->variants[$name] ?? null), ARRAY_FILTER_USE_BOTH);
        $validation = app(ThemeValidator::class)->validateAll();
        $fonts = $this->manager->fonts();
        $health = new LabHealth($fonts);
        $shell = Route::has('hardware.index') ? route('hardware.index') : url('/');

        return view('gs-theme::lab.workspace', [
            'theme' => $theme,
            'all' => $this->manager->repository()->all(),
            'validation' => $validation,
            'issues' => $validation[$theme->key] ?? [],
            'summary' => $health->summary($theme, $validation[$theme->key] ?? []),
            'fontNames' => $health->fontNames($theme),
            'tokenValues' => ['light' => $theme->tokens('light', $fonts), 'dark' => $theme->tokens('dark', $fonts)],
            'overrides' => $overrides,
            'variants' => array_merge($theme->variants, $overrides),
            'options' => ThemeValidator::VARIANTS,
            'groups' => self::VARIANT_GROUPS,
            'tabs' => self::TABS,
            'state' => [
                'mode' => $this->option($request, 'mode', ['light', 'dark', 'split']),
                'section' => $this->option($request, 'section', array_keys(self::TABS)),
                'width' => $this->option($request, 'width', ['desktop', 'tablet', 'phone']),
                'tokens' => $request->boolean('tokens'),
            ],
            'bengali' => $request->boolean('bn'),
            'shellUrl' => $shell,
            'fixtures' => new LabFixtures,
            'contract' => Contract::GROUPS,
            'fonts' => $fonts,
        ]);
    }

    /** A whitelisted query value; the first allowed value is the default. */
    private function option(Request $request, string $name, array $allowed): string
    {
        $value = $request->query($name);

        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    public function geoChildren(Request $request, int $area)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $file = GeoLabFixture::childrenFile($area);
        abort_unless($file, 404);

        return response()->json($file, 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    public function geoData(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $v = $request->validate(['area' => ['required', 'integer'], 'layer' => ['nullable', 'string', 'max:30'], 'states' => ['nullable', 'boolean'], 'fallback' => ['nullable', 'string', 'max:200'], 'level' => ['nullable', 'integer', 'in:2,3']]);
        $layer = $v['layer'] ?? explode(',', $v['fallback'] ?? 'progress')[0];
        $payload = GeoLabFixture::payload((int) $v['area'], $layer, (bool) ($v['states'] ?? false), app()->getLocale(), 'lab.synthetic', isset($v['level']) ? (int) $v['level'] : null);
        abort_unless($payload, 404);

        return response()->json($payload, 200, ['Cache-Control' => 'private, no-cache']);
    }

    public function geoFlat(Request $request, int $level)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $file = GeoLabFixture::flatFile($level);
        abort_unless($file, 404);

        return response()->json($file, 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    public function geoSearch(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $v = $request->validate(['q' => ['required', 'string', 'max:60']]);

        return response()->json(GeoLabFixture::search($v['q'], app()->getLocale()));
    }

    public function geoDetails(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $v = $request->validate(['area' => ['required', 'integer'], 'layer' => ['nullable', 'string', 'max:30'], 'fallback' => ['nullable', 'string', 'max:200']]);
        $layer = $v['layer'] ?? explode(',', $v['fallback'] ?? 'progress')[0];

        return response()->json(GeoLabFixture::details((int) $v['area'], app()->getLocale(), $layer));
    }

    /** Lab demo configs for one panel: progress with every state; compare and pick outside compact panels. */
    public static function geoDemos(string $panel, bool $compact, bool $bengali): array
    {
        $locale = $bengali ? 'bn-BD' : app()->getLocale();
        $strings = trans('gs-theme::kit.geo', [], $locale);
        $endpoints = [
            'boundary' => '/'.trim(parse_url(url('gov/theme-lab/geo-fixture/'.GeoLabFixture::VERSION), PHP_URL_PATH), '/'),
            'data' => parse_url(route('gs-theme.lab.geo.data'), PHP_URL_PATH),
            'details' => parse_url(route('gs-theme.lab.geo.details'), PHP_URL_PATH),
            'search' => parse_url(route('gs-theme.lab.geo.search'), PHP_URL_PATH),
        ];
        $base = 'geo-'.substr(preg_replace('/[^a-z0-9-]/', '', str_replace('gs-panel-', '', $panel)), 0, 30);
        $demos = [['config' => GeoLabFixture::config($base.'-progress', 'progress', $endpoints, $locale, is_array($strings) ? $strings : [], ['url_state' => ! $compact]), 'title' => 'progress']];
        if (! $compact) {
            $demos[] = ['config' => GeoLabFixture::config($base.'-compare', 'compare', $endpoints, $locale, is_array($strings) ? $strings : []), 'title' => 'compare'];
            $demos[] = ['config' => GeoLabFixture::config($base.'-pick', 'pick', $endpoints, $locale, is_array($strings) ? $strings : []), 'title' => 'pick'];
        }

        return $demos;
    }

    private function guard(Request $request)
    {
        abort_unless(config('gs-theme.lab_enabled'), 404);
        if (! $this->access->canViewLab($request->user())) {
            return $this->deny($request, 'theming.lab.view');
        }
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        return null;
    }
}
