{{-- How long the stock lasts at the rate sections draw it. Adjustments and transfers are not demand. --}}
@php
    $c = 'storeops::storeops.stock_card.';
    $o = $card['outlook'];
    $tone = ['out' => 'danger', 'reorder' => 'warning', 'ok' => 'success'][$o['state']];
@endphp
<x-gs::box :title="__($c.'outlook_title')" icon="fas fa-binoculars">
    <p><x-gs::badge :tone="$tone">{{ __($c.'outlook_states.'.$o['state']) }}</x-gs::badge></p>
    <div class="storeops-outlook">
        <div><span class="storeops-outlook__value">{{ $card['closing_all'] }}</span><span class="gs-muted">{{ __($c.'in_store') }}</span></div>
        <div aria-hidden="true" class="storeops-outlook__sep">≈</div>
        <div>
            <span class="storeops-outlook__value">{{ $o['weeks_left'] === null ? '—' : __($c.'weeks_short', ['weeks' => $o['weeks_left']]) }}</span>
            <span class="gs-muted">{{ __($c.'at_issue_rate') }}</span>
        </div>
    </div>
    <p>
        {{ __($c.'outlook_text', ['issued' => $o['issued_90'], 'per_week' => $o['per_week']]) }}
        @if ($card['reorder'] !== null) {{ __($c.'reorder_is', ['level' => $card['reorder']]) }} @endif
    </p>
</x-gs::box>
