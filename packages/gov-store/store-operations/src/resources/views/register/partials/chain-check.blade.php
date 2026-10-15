{{-- Verify, don't just assert: the ledger's own posting rule re-checked on every view. --}}
@php
    $c = 'storeops::storeops.stock_card.';
    $chain = $card['chain'];
@endphp
<x-gs::alert :tone="$chain['ok'] ? 'success' : 'danger'" appearance="card"
    :title="$chain['ok'] ? __($c.'chain_ok_title') : __($c.'chain_broken_title')">
    <p>{{ $chain['ok'] ? trans_choice($c.'chain_ok_text', $chain['entries'], ['count' => $chain['entries']]) : __($c.'chain_broken_text') }}</p>
    <p class="gs-muted">{{ __($c.'checked_at', ['time' => $card['checked_at']->translatedFormat('d M Y, H:i T')]) }}</p>
    <details class="storeops-details">
        <summary>{{ __($c.'chain_what') }}</summary>
        <p>{{ __($c.'chain_explain') }}</p>
    </details>
</x-gs::alert>
