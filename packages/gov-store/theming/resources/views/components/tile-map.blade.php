@props(['tiles' => [], 'label' => null])
<div {{ $attributes->class('gs-tile-map') }} role="group" aria-label="{{ $label ?? __('gs-theme::kit.offices') }}">
    @forelse($tiles as $tile)
        @php
            $share = max(0,min(100,($tile['share'] ?? 0)*100));
            $column = max(1,min(6,(int)($tile['x'] ?? 1)));
            $row = max(1,(int)($tile['y'] ?? $loop->iteration));
            $mix = round(12 + $share * .8);
        @endphp
        <a href="{{ $tile['href'] }}" class="gs-tile-map__tile {{ ($tile['share'] ?? null) === null ? 'gs-tile-map__tile--unplanned' : '' }} {{ $share >= 50 ? 'gs-tile-map__tile--strong' : '' }}" style="grid-column: {{ $column }}; grid-row: {{ $row }}; --gs-tile-mix: {{ $mix }}%">
            <strong>{{ $tile['name'] }}</strong>@if($tile['bn'] ?? null)<small lang="bn">{{ $tile['bn'] }}</small>@endif
            <span>{{ ($tile['share'] ?? null) === null ? __('gs-theme::kit.no_target') : number_format($share,0).'%' }}</span>
            @if($tile['alert'] ?? false)<i class="fas fa-flag" aria-hidden="true"></i><span class="gs-sr-only">{{ __('gs-theme::kit.attention') }}</span>@endif
        </a>
    @empty<p>{{ __('gs-theme::kit.empty_offices') }}</p>@endforelse
</div>
