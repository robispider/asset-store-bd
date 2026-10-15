<?php

namespace GovStore\TenantScope\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Navbar language toggle (EN | বাং). Saves the choice as the user's Snipe-IT language
 * (users.locale), which core CheckLocale already applies on every request and device; the
 * profile page's language field writes the same column.
 */
class LocaleController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('govstore-navigation.languages', [])))],
        ])['locale'];

        // Quietly: a display preference, not an audited profile change, and it must not fail
        // on Snipe-IT's whole-model validation of unrelated user fields.
        $request->user()->forceFill(['locale' => $locale])->saveQuietly();

        return redirect()->back(fallback: url('/'));
    }
}
