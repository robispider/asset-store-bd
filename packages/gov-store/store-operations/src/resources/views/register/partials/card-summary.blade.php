{{-- Brought forward + received − out = closing, for the chosen period. Callers may rename the closing tile. --}}
@php
    $c = 'storeops::storeops.stock_card.';
    $closingLow = $card['reorder'] !== null && $card['closing'] <= $card['reorder'];
    $closingLabel = $closingLabel ?? __($c.'closing');
    $closingCaption = $closingCaption ?? ($closingLow
        ? __($c.'closing_low', ['level' => $card['reorder']])
        : __($c.'on_date', ['date' => $card['period']->end->translatedFormat('d M Y')]));
@endphp
<div class="gs-kpis">
    <x-gs::kpi :label="__($c.'brought_forward')" :value="$card['bf']" icon="fa-flag"
        :caption="__($c.'on_date', ['date' => $card['period']->start->translatedFormat('d M Y')])" />
    <x-gs::kpi :label="__($c.'received')" :value="'+'.$card['in']" icon="fa-arrow-down" tone="success"
        :caption="trans_choice($c.'receipts_in_period', $card['n_in'], ['count' => $card['n_in']])" />
    <x-gs::kpi :label="__($c.'out_total')" :value="'−'.$card['out']" icon="fa-arrow-up" tone="muted"
        :caption="trans_choice($c.'entries_in_period', $card['n_out'], ['count' => $card['n_out']])" />
    <x-gs::kpi :label="$closingLabel" :value="$card['closing']" icon="fa-scale-balanced" :tone="$closingLow ? 'warning' : 'info'"
        :caption="$closingCaption" />
</div>
