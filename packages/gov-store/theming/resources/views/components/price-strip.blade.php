@props(['prices' => [], 'label' => null])
@php
    $points = array_values($prices['points'] ?? []);
    $min = $prices['min'] ?? 0; $max = $prices['max'] ?? 0; $range = ($max-$min) ?: 1;
    $average = $prices['average'] ?? null;
@endphp
<div {{ $attributes->class('gs-price-strip') }}>
    <svg viewBox="0 0 320 52" role="img" aria-label="{{ $label ?? __('gs-theme::kit.prices') }}">
        <line x1="12" y1="24" x2="308" y2="24" class="gs-price-strip__axis" />
        @if($average !== null)<line x1="{{ 12+296*($average-$min)/$range }}" y1="6" x2="{{ 12+296*($average-$min)/$range }}" y2="42" class="gs-price-strip__average" />@endif
        @foreach($points as $point)<circle cx="{{ 12+296*($point-$min)/$range }}" cy="24" r="5" tabindex="0"><title>{{ number_format($point,2) }}</title></circle>@endforeach
    </svg>
    <dl class="gs-monitor-inline-kv"><div><dt>{{ __('gs-theme::kit.min') }}</dt><dd>{{ $average === null ? '—' : number_format($min,2) }}</dd></div><div><dt>{{ __('gs-theme::kit.average') }}</dt><dd>{{ $average === null ? '—' : number_format($average,2) }}</dd></div><div><dt>{{ __('gs-theme::kit.max') }}</dt><dd>{{ $average === null ? '—' : number_format($max,2) }}</dd></div><div><dt>{{ __('gs-theme::kit.spread') }}</dt><dd>{{ ($prices['spread'] ?? null) === null ? '—' : number_format($prices['spread']*100,1).'%' }}</dd></div></dl>
    <details><summary>{{ __('gs-theme::kit.table') }}</summary><table><thead><tr><th>{{ __('gs-theme::kit.prices') }}</th></tr></thead><tbody>@foreach($points as $point)<tr><td>{{ number_format($point,2) }}</td></tr>@endforeach</tbody></table></details>
</div>
