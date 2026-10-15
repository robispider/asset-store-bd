{{-- Step chart of the balance through the period, with the reorder level. Colours come from token classes. --}}
@php
    $c = 'storeops::storeops.stock_card.';
    $chart = $card['chart'];
    $summary = __($c.'chart_label', [
        'start' => $card['period']->start->translatedFormat('d M Y'), 'end' => $card['period']->end->translatedFormat('d M Y'),
        'bf' => $card['bf'], 'closing' => $card['closing'], 'low' => $chart['low'], 'high' => $chart['high'],
    ]);
@endphp
<x-gs::box :title="__($c.'chart_title')" icon="fas fa-chart-line">
    <p class="gs-muted">{{ __($c.'chart_subtitle', ['from' => $card['period']->start->translatedFormat('d M Y'), 'to' => $card['period']->end->translatedFormat('d M Y')]) }}</p>
    <figure class="storeops-chart">
        <svg viewBox="-40 -10 860 240" role="img" aria-label="{{ $summary }}" preserveAspectRatio="xMidYMid meet">
            @foreach ($chart['y_ticks'] as $tick)
                <line class="storeops-chart__grid" x1="0" x2="800" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" />
                <text class="storeops-chart__axis" x="-8" y="{{ $tick['y'] + 4 }}" text-anchor="end">{{ $tick['label'] }}</text>
            @endforeach
            @foreach ($chart['x_ticks'] as $tick)
                <text class="storeops-chart__axis" x="{{ $tick['x'] }}" y="222" text-anchor="middle">{{ $tick['label'] }}</text>
            @endforeach
            <path class="storeops-chart__area" d="{{ $chart['area'] }}" />
            <path class="storeops-chart__line" d="{{ $chart['line'] }}" />
            @if ($chart['reorder_y'] !== null)
                <line class="storeops-chart__reorder" x1="0" x2="800" y1="{{ $chart['reorder_y'] }}" y2="{{ $chart['reorder_y'] }}" />
            @endif
            @if ($chart['selected_x'] !== null)
                <line class="storeops-chart__selected" x1="{{ $chart['selected_x'] }}" x2="{{ $chart['selected_x'] }}" y1="0" y2="200" />
            @endif
        </svg>
        <figcaption class="storeops-legend">
            <span><span class="storeops-legend__swatch storeops-legend__swatch--line" aria-hidden="true"></span> {{ __($c.'col_balance') }}</span>
            @if ($chart['reorder_y'] !== null)
                <span><span class="storeops-legend__swatch storeops-legend__swatch--reorder" aria-hidden="true"></span> {{ __($c.'reorder_line', ['level' => $card['reorder']]) }}</span>
            @endif
            @if ($chart['selected_x'] !== null && $card['selected'])
                <span><span class="storeops-legend__swatch storeops-legend__swatch--selected" aria-hidden="true"></span> {{ __($c.'selected_entry', ['document' => $card['selected']['document_number'] ?? '—']) }}</span>
            @endif
        </figcaption>
    </figure>
</x-gs::box>
