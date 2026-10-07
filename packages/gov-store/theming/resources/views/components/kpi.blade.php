@props(['label', 'value', 'bn' => null, 'delta' => null, 'href' => null, 'icon' => null, 'tone' => 'primary'])
@php
    $direction = $delta === null ? null : (str_starts_with(trim((string) $delta), '-') ? 'down' : 'up');
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} {{ $attributes->class(['gs-kpi', 'gs-kpi--'.$tone => $tone !== 'primary', 'gs-kpi--link' => $href]) }} @if ($href) href="{{ $href }}" @endif>
    <span class="gs-kpi__label">
        @if ($icon)<i class="fas {{ $icon }} gs-kpi__icon" aria-hidden="true"></i>@endif
        {{ $label }}
    </span>
    <span class="gs-kpi__value">{{ $value }}</span>
    @if ($bn)<span class="gs-kpi__bn" lang="bn">{{ $bn }}</span>@endif
    @if ($delta !== null)
        <span class="gs-kpi__delta gs-kpi__delta--{{ $direction }}">
            <i class="fas fa-arrow-{{ $direction === 'down' ? 'down' : 'up' }}" aria-hidden="true"></i>
            {{ $delta }}<span class="gs-sr-only"> {{ __('gs-theme::appearance.kit.delta_'.$direction) }}</span>
        </span>
    @endif
</{{ $tag }}>
