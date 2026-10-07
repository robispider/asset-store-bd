<?php

namespace GovStore\Theming\Http\Controllers;

use GovStore\Theming\Access\ThemeAccess;
use GovStore\Theming\Models\ThemePreference;
use GovStore\Theming\ThemeManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppearanceController extends Controller
{
    public function __construct(private ThemeManager $manager, private ThemeAccess $access) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $context = $this->manager->context();
        $resolver = $this->manager->resolver();
        $preference = $resolver->preference((int) $user->id) ?? ['theme' => null, 'mode' => 'system'];
        $enforced = $resolver->enforced($context['company'], $context['location']);
        $inherited = $resolver->inheritedDefault($context['company'], $context['location']);
        $tags = [];
        foreach ([['office', $context['location']], ['company', $context['company']], ['organization', null]] as [$scope, $id]) {
            if ($scope !== 'organization' && ! $id) {
                continue;
            }
            if ($assignment = $resolver->assignment($scope, $id)) {
                $tags[$assignment['theme']][] = $scope;
            }
        }

        return view('gs-theme::appearance.index', [
            'themes' => $this->manager->repository()->published(),
            'current' => $this->manager->key(),
            'preference' => $preference,
            'enforced' => $enforced,
            'inherited' => $inherited,
            'tags' => $tags,
            'canChoose' => $this->access->allows($user, 'theming.appearance.self') && ! $enforced,
            'canAssign' => $this->access->manageableScopes($user) !== [],
            'canViewLab' => $this->access->canViewLab($user),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'theme' => ['nullable', 'string', Rule::in(array_keys($this->manager->repository()->published()))],
            'mode' => ['required', Rule::in(ThemePreference::MODES)],
            'reset' => ['nullable', 'boolean'],
        ]);
        $preference = ThemePreference::firstOrNew(['user_id' => $user->id]);
        $preference->mode = $data['mode'];
        $wantsTheme = $request->boolean('reset') ? null : ($data['theme'] ?? null);
        if ($wantsTheme !== $preference->theme) {
            $context = $this->manager->context();
            if (! $this->access->allows($user, 'theming.appearance.self')
                || $this->manager->resolver()->enforced($context['company'], $context['location'])) {
                return back()->withErrors(['theme' => trans('gs-theme::appearance.theme_locked')]);
            }
            $preference->theme = $wantsTheme;
        }
        $preference->save();
        $this->manager->forget();

        return redirect()->route('gs-theme.appearance')->with('success', trans('gs-theme::appearance.saved'));
    }

    /** Called by gs-theme.js when the Snipe-IT light/dark toggle is used. */
    public function mode(Request $request)
    {
        $data = $request->validate(['mode' => ['required', Rule::in(ThemePreference::MODES)]]);
        $preference = ThemePreference::firstOrNew(['user_id' => $request->user()->id]);
        $preference->mode = $data['mode'];
        $preference->save();

        return response()->json(['mode' => $preference->mode]);
    }
}
