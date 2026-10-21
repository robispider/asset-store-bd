<?php

namespace GovStore\Theming\Http\Controllers;

use GovStore\Theming\Access\ThemeAccess;
use GovStore\Theming\Build\Contract;
use GovStore\Theming\Build\ThemeValidator;
use GovStore\Theming\Lab\GeoLabFixture;
use GovStore\Theming\Lab\LabFixtures;
use GovStore\Theming\ThemeManager;
use Illuminate\Http\Request;

/**
 * Developer tool: review every token, core surface, kit component and pattern of the shipped
 * themes in light and dark. Static fixture data only — safe in production (super admin).
 */
class ThemeLabController extends Controller
{
    public function __construct(private ThemeManager $manager, private ThemeAccess $access) {}

    public function index(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $all = $this->manager->repository()->all();
        $selected = array_values(array_intersect((array) $request->query('themes', array_keys($all)), array_keys($all))) ?: array_keys($all);
        $themes = array_intersect_key($all, array_flip($selected));

        return view('gs-theme::lab.matrix', [
            'all' => $all,
            'themes' => $themes,
            'validation' => app(ThemeValidator::class)->validateAll(),
            'fixtures' => new LabFixtures,
            'contract' => Contract::GROUPS,
            'fonts' => $this->manager->fonts(),
        ]);
    }

    public function focus(Request $request, string $theme)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }
        $target = $this->manager->repository()->find($theme);
        abort_unless($target, 404);
        // The real shell renders through the (never saved) preview override.
        if ($request->query('gs_preview') !== $theme) {
            return redirect()->route('gs-theme.lab.focus', array_merge($request->query(), [
                'theme' => $theme,
                'gs_preview' => $theme,
                'gs_mode' => in_array($request->query('mode'), ['light', 'dark', 'system'], true) ? $request->query('mode') : 'light',
            ]));
        }
        $overrides = array_intersect_key((array) $request->query('gs_variant', []), ThemeValidator::VARIANTS);
        $overrides = array_filter($overrides, fn ($value, $name) => in_array($value, ThemeValidator::VARIANTS[$name], true), ARRAY_FILTER_USE_BOTH);

        return view('gs-theme::lab.focus', [
            'theme' => $target,
            'all' => $this->manager->repository()->all(),
            'mode' => $request->query('gs_mode', 'light'),
            'overrides' => $overrides,
            'variants' => array_merge($target->variants, $overrides),
            'options' => ThemeValidator::VARIANTS,
            'validation' => [$target->key => app(ThemeValidator::class)->validate($target->key)],
            'fixtures' => new LabFixtures,
            'contract' => Contract::GROUPS,
            'fonts' => $this->manager->fonts(),
            'bengali' => $request->boolean('bn'),
        ]);
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
