{{--
    Request items results, also returned alone for live search: the count, the Cards / List switch, the
    items and the pages. The kind counts ride along in a <template> so the tabs stay true while searching.
    Expects $catalogItems, $inDraft, $rules, $filters, $view, $counts, $presenter.
--}}
@php
    $r = 'requestlabels::requests.';
    $q = trim($filters['q'] ?? '');
    $filtered = $q !== '' || ! empty($filters['type']) || ! empty($filters['category_id']) || ! empty($filters['free']);
@endphp
<div class="cr-listbar">
    <p class="cr-results-count" data-cr-results-count>
        @if ($q !== '')
            {{ trans_choice($r.'catalog_results_for', $catalogItems->total(), ['count' => $catalogItems->total(), 'q' => $q]) }}
        @else
            {{ trans_choice($r.'catalog_results', $catalogItems->total(), ['count' => $catalogItems->total()]) }}
        @endif
    </p>
    <div class="cr-viewswitch" role="group" aria-label="{{ __($r.'view_label') }}">
        @foreach (['grid' => ['fa-table-cells-large', 'view_cards'], 'list' => ['fa-list', 'view_list']] as $mode => [$icon, $label])
            <a href="{{ request()->fullUrlWithQuery(['view' => $mode, 'page' => null]) }}" class="cr-viewswitch__option" data-cr-view="{{ $mode }}"
                @if ($view === $mode) aria-current="true" @endif><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ __($r.$label) }}</a>
        @endforeach
    </div>
</div>

@if ($catalogItems->isEmpty())
    <x-gs::empty-state icon="fa-magnifying-glass" :title="$q !== '' ? __($r.'catalog_empty_title', ['q' => $q]) : __($r.'catalog_empty_all')">
        @if ($filtered)
            {{ __($r.'catalog_empty_body') }}
            <x-slot:actions><x-gs::button :href="route('gov.requests.catalog')" tone="light" outline>{{ __($r.'catalog_show_all') }}</x-gs::button></x-slot:actions>
        @endif
    </x-gs::empty-state>
@else
    <ul class="cr-goods cr-goods--{{ $view === 'list' ? 'list' : 'grid' }}" aria-label="{{ __($r.'catalog_list_label') }}" data-cr-goods>
        @foreach ($catalogItems as $item)
            @include('govstore::catalog.partials.item')
        @endforeach
    </ul>
    <div class="cr-pages">{{ $catalogItems->links() }}</div>
@endif
<template data-cr-counts>{{ json_encode($counts) }}</template>
