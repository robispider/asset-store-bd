@extends('layouts/default')

@section('title', __('storeops::storeops.stock_card_title', ['name' => $item->name]))

@section('content')
@php
    $c = 'storeops::storeops.stock_card.';
@endphp
<div class="storeops-theme storeops-card">
    @include('storeops::register.partials.context-bar')
    @include('storeops::register.partials.item-header', ['kind' => 'stock'])

    @if (! $card)
        <x-gs::empty-state icon="fa-building" :title="__('storeops::storeops.stock_register.no_office')" />
    @else
        @include('storeops::register.partials.card-summary')

        <div class="storeops-split">
            <div class="storeops-split__main">
                @include('storeops::register.partials.balance-chart')
            </div>
            <aside class="storeops-split__side">
                @include('storeops::register.partials.stock-outlook')
                @include('storeops::register.partials.chain-check')
            </aside>
        </div>

        <div class="storeops-split" id="ledger">
            <div class="storeops-split__main">
                <x-gs::box :title="__($c.'ledger_title')" icon="fas fa-book">
                    <p class="gs-muted">{{ __($c.'ledger_subtitle') }}</p>
                    @include('storeops::register.partials.period-filter')
                    @include('storeops::register.partials.ledger-table', ['showTags' => false])
                </x-gs::box>
            </div>
            <aside class="storeops-split__side">
                @include('storeops::register.partials.entry-details', ['unit' => $facts['unit']])
            </aside>
        </div>
    @endif
</div>
@endsection
