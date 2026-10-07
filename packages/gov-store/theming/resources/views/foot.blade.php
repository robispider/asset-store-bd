{{-- gs-theme::foot — inserted by the layout hook before </body>. --}}
@php
    try {
        $gsManager = app('gs.theme');
        $gsJs = $gsManager->asset('js');
        $gsTheme = [
            'key' => $gsManager->key(),
            'label' => $gsManager->theme()->label(),
            'mode' => $gsManager->mode(),
            'classic' => $gsManager->isClassic(),
            'authenticated' => auth()->check(),
            'modeUrl' => auth()->check() ? route('gs-theme.appearance.mode') : null,
            'appearanceUrl' => auth()->check() ? route('gs-theme.appearance') : null,
            'csrf' => csrf_token(),
            'fallbackPalette' => $gsManager->chartFallbackPalette(),
            'brandingNotice' => ! $gsManager->isClassic() && request()->routeIs('settings.branding.index', 'profile'),
            'i18n' => [
                'appearance' => __('gs-theme::appearance.menu_appearance'),
                'brandingNotice' => __('gs-theme::appearance.branding_notice', ['theme' => $gsManager->theme()->label()]),
                'manage' => __('gs-theme::appearance.manage_in_appearance'),
                'unsupported' => __('gs-theme::appearance.unsupported_browser'),
                'dismiss' => __('gs-theme::appearance.kit.dismiss'),
            ],
        ];
    } catch (\Throwable $e) {
        report($e);
        $gsJs = null;
        $gsTheme = null;
    }
@endphp
@if ($gsTheme && $gsJs)
    <script type="application/json" id="gs-theme-config">@json($gsTheme)</script>
    <script src="{{ $gsJs }}" nonce="{{ csrf_token() }}" defer></script>
@endif
