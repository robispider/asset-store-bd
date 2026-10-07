{{--
    layout: stack (default) · icon-left (big outline icon beside the figure) · centered (icon over label and value).
    spark: array of numbers drawn as an inline sparkline; progress: 0–100 usage bar; caption: small line underneath.
--}}
@props(['label', 'value', 'bn' => null, 'delta' => null, 'href' => null, 'icon' => null, 'tone' => 'primary',
    'layout' => 'stack', 'spark' => null, 'progress' => null, 'caption' => null])
@php
    $direction = $delta === null ? null : (str_starts_with(trim((string) $delta), '-') ? 'down' : 'up');
    $tag = $href ? 'a' : 'div';
    $points = null;
    if (is_array($spark) && count($spark) > 1) {
        [$min, $max] = [min($spark), max($spark)];
        $range = ($max - $min) ?: 1;
        $step = 100 / (count($spark) - 1);
        $points = collect(array_values($spark))->map(fn ($v, $i) => round($i * $step, 2).','.round(28 - (($v - $min) / $range) * 24, 2))->implode(' ');
    }
@endphp
<{{ $tag }} {{ $attributes->class(['gs-kpi', 'gs-kpi--'.$tone => $tone !== 'primary', 'gs-kpi--link' => $href, 'gs-kpi--'.$layout => $layout !== 'stack']) }} @if ($href) href="{{ $href }}" @endif>
    @if ($icon && $layout !== 'stack')<i class="fas {{ $icon }} gs-kpi__icon gs-kpi__icon--lg" aria-hidden="true"></i>@endif
    <span class="gs-kpi__main">
        <span class="gs-kpi__label">
            @if ($icon && $layout === 'stack')<i class="fas {{ $icon }} gs-kpi__icon" aria-hidden="true"></i>@endif
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
        @if ($points)
            <svg class="gs-kpi__spark" viewBox="-2 0 104 32" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                <polyline points="{{ $points }}" fill="none" vector-effect="non-scaling-stroke" />
            </svg>
        @endif
        @if ($progress !== null)
            <span class="gs-kpi__progress" aria-hidden="true"><span style="width: {{ max(0, min(100, (float) $progress)) }}%"></span></span>
        @endif
        @if ($caption)<span class="gs-kpi__caption">{{ $caption }}</span>@endif
    </span>
</{{ $tag }}>
