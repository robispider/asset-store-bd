{{-- gs-theme::head — inserted by the layout hook before the admin Custom CSS block (or </head>). --}}
@php
    try {
        $gsTheme = app('gs.theme');
        $gsKey = $gsTheme->key();
        $gsMode = $gsTheme->mode();
        $gsAssets = array_filter([$gsTheme->asset('kit'), $gsTheme->asset('packages')]);
        $gsThemeCss = $gsTheme->themeAsset($gsKey);
        $gsFonts = $gsTheme->preloadFonts();
    } catch (\Throwable $e) {
        report($e);
        $gsAssets = [];
        $gsThemeCss = null;
        $gsFonts = [];
        $gsMode = 'system';
    }
    $gsGuest = ! auth()->check();
@endphp
@foreach ($gsFonts as $gsFont)
    <link rel="preload" href="{{ $gsFont }}" as="font" type="font/woff2" crossorigin>
@endforeach
@foreach ($gsAssets as $gsAsset)
    <link rel="stylesheet" href="{{ $gsAsset }}" data-gs-asset>
@endforeach
@if ($gsThemeCss)
    <link rel="stylesheet" href="{{ $gsThemeCss }}" data-gs-theme-link>
@endif
<script nonce="{{ csrf_token() }}">
(function () {
    // Runs before first paint: resolves `system` (and guests' saved choice) so there is no flash,
    // and keeps Snipe-IT's own toggle (localStorage "theme") in agreement with the saved mode.
    var html = document.documentElement, mode = @json($gsMode), guest = @json($gsGuest), stored = null;
    try { stored = localStorage.getItem('theme'); } catch (e) {}
    if (guest && (stored === 'light' || stored === 'dark')) { mode = stored; }
    var resolve = function (m) { return m === 'system' ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : m; };
    var applied = resolve(mode);
    html.setAttribute('data-theme', applied);
    try { localStorage.setItem('theme', applied); } catch (e) {}
    if (mode === 'system' && window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        var onChange = function () {
            if (html.getAttribute('data-gs-mode') !== 'system') { return; }
            var next = mq.matches ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) {}
        };
        mq.addEventListener ? mq.addEventListener('change', onChange) : mq.addListener(onChange);
    }
})();
</script>
