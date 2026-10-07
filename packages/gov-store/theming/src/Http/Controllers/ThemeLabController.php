<?php

namespace GovStore\Theming\Http\Controllers;

use GovStore\Theming\Access\ThemeAccess;
use GovStore\Theming\Build\Contract;
use GovStore\Theming\Build\ThemeValidator;
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
