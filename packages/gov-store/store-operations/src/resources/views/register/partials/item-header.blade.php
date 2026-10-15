{{-- Item identity, facts and read-only status for a stock card or asset card. $kind: 'stock' or 'asset'. --}}
@php
    $c = 'storeops::storeops.stock_card.';
    $r = 'storeops::storeops.stock_register.';
    $low = $card && $card['reorder'] !== null && $card['closing_all'] <= $card['reorder'];
    $factList = array_filter([
        __($c.'item_code') => $facts['code'],
        __($r.'col_category') => $facts['category'],
        __($r.'col_type') => __($r.'types.'.$facts['type']),
        __('storeops::storeops.asset_register.manufacturer') => $facts['manufacturer'],
        __($c.'store') => $office['name'],
        __($r.'reorder_level') => $facts['reorder'],
    ], fn ($value) => $value !== null && $value !== '');
    $printQuery = $card ? $card['period']->query() : [];
@endphp
<a class="storeops-back storeops-noprint" href="{{ route('storeops.register.index') }}">
    <i class="fas fa-arrow-left" aria-hidden="true"></i> {{ __($c.'back') }}
</a>
<x-gs::page-header :title="$item->name" :bn="$kind === 'asset' ? 'সম্পদ কার্ড' : 'স্টক কার্ড'"
    :subtitle="$kind === 'asset' ? __('storeops::storeops.asset_register.card_kicker') : __($c.'kicker')">
    <x-slot:meta>
        <ul class="storeops-facts">
            @foreach ($factList as $label => $value)
                <li>{{ $label }} <strong>{{ $value }}</strong></li>
            @endforeach
        </ul>
        <div class="storeops-tags">
            <x-gs::badge tone="secondary"><i class="fas fa-lock" aria-hidden="true"></i> {{ __($c.'read_only') }}</x-gs::badge>
            @if ($kind === 'asset')
                <x-gs::badge tone="info"><i class="fas fa-barcode" aria-hidden="true"></i> {{ __('storeops::storeops.asset_register.serialised') }}</x-gs::badge>
            @endif
            @if ($low)
                <x-gs::badge tone="warning"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ __($c.'below_reorder') }}</x-gs::badge>
            @endif
        </div>
    </x-slot:meta>
    @if ($card)
        <x-slot:actions>
            <a class="btn btn-default storeops-noprint" href="{{ route('storeops.register.kardex.export', array_merge(['type' => $type, 'id' => $item->id], $printQuery)) }}">
                <i class="fas fa-file-csv" aria-hidden="true"></i> {{ __($c.'export') }}</a>
            <a class="btn btn-primary storeops-noprint" target="_blank" rel="noopener"
                href="{{ route('storeops.register.kardex.print', array_merge(['type' => $type, 'id' => $item->id], $printQuery)) }}">
                <i class="fas fa-print" aria-hidden="true"></i> {{ $kind === 'asset' ? __('storeops::storeops.asset_register.print') : __($c.'print') }}</a>
        </x-slot:actions>
    @endif
</x-gs::page-header>
