{{--
    x-gs::geo-map — markup shell of the neutral geo view engine (lazy bundle "geo"). The host passes a resolved
    gs-geo-config/1 array and, optionally, the first gs-geo-data/1 payload for first paint. The slot is the
    server-rendered table: the no-JS, print and screen-reader fallback, hidden once the engine mounts.
--}}
@props(['config', 'payload' => null, 'label' => null])
<div {{ $attributes->class('gs-geo') }} id="{{ $config['id'] }}" data-gs-geo="{{ $config['id'] }}" data-gs-lazy="geo"
    role="region" aria-label="{{ $label ?? __('gs-theme::kit.geo_map') }}" @if (! empty($config['locale'])) lang="{{ str_starts_with($config['locale'], 'bn') ? 'bn' : 'en' }}" @endif>
    <script type="application/json" data-gs-geo-config>@json($config)</script>
    @if ($payload)
        <script type="application/json" data-gs-geo-payload>@json($payload)</script>
    @endif
    <div class="gs-geo__fallback" data-gs-geo-fallback>{{ $slot }}</div>
</div>
