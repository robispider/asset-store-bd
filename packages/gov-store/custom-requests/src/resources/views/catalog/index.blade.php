@extends('layouts/default')

@section('title', __('requestlabels::requests.catalog_title'))

{{--
    Request items: search first, kind tabs with counts, quick-add shortcuts, then the items as cards or a
    list. The draft panel beside it is always current and says how the draft will be sent; on phones it
    becomes a bar at the bottom. Nothing is sent from this page. Expects GovRequestController::catalog.
--}}
@php
    $r = 'requestlabels::requests.';
    $type = $filters['type'] ?? '';
@endphp

@section('content')
<div class="cr cr-shop" data-cr-catalog>
    <x-gs::page-header :title="__($r.'catalog_title')" :subtitle="__($r.'catalog_subtitle_v2')" />
    <p class="cr-context">
        <i class="fas fa-building" aria-hidden="true"></i>
        <span>{!! __($r.'catalog_context', ['office' => '<strong>'.e($office).'</strong>', 'name' => e($presenter->name((int) auth()->id()))]) !!}</span>
    </p>

    @include('govstore::partials.errors')

    <div class="cr-shop__layout">
        <div class="cr-shop__main">
            <form method="GET" action="{{ route('gov.requests.catalog') }}" class="cr-finder" role="search" data-cr-live="#cr-results">
                <div class="cr-finder__search">
                    <label for="cr-q" class="gs-sr-only">{{ __($r.'catalog_search_label') }}</label>
                    <i class="fas fa-magnifying-glass cr-finder__icon" aria-hidden="true"></i>
                    <input type="search" id="cr-q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control cr-finder__input" autocomplete="off"
                        placeholder="{{ __($r.'catalog_search_placeholder_v2') }}" maxlength="200" aria-keyshortcuts="/">
                    <kbd class="cr-finder__key" aria-hidden="true">/</kbd>
                    <x-gs::button type="submit" tone="light" outline class="cr-finder__submit" data-cr-live-submit>{{ __($r.'catalog_search_button') }}</x-gs::button>
                </div>
                <div class="cr-finder__filters">
                    <fieldset class="cr-kinds">
                        <legend class="gs-sr-only">{{ __($r.'catalog_type_label') }}</legend>
                        @foreach (['' => 'catalog_type_all', 'consumable' => 'catalog_type_consumable', 'accessory' => 'catalog_type_accessory', 'asset_model' => 'catalog_type_asset_model'] as $value => $label)
                            <label class="cr-kind">
                                <input type="radio" name="type" value="{{ $value }}" @checked($type === $value)>
                                <span>{{ __($r.$label) }} <span class="cr-kind__count" data-cr-type-count="{{ $value }}">{{ $counts[$value] ?? 0 }}</span></span>
                            </label>
                        @endforeach
                    </fieldset>
                    <label for="cr-category" class="gs-sr-only">{{ __($r.'catalog_category_label') }}</label>
                    <select id="cr-category" name="category_id" class="form-control cr-finder__select">
                        <option value="">{{ __($r.'catalog_category_all') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <label class="cr-check" for="cr-free">
                        <input type="checkbox" id="cr-free" name="free" value="1" @checked(! empty($filters['free']))>
                        <span>{{ __($r.'catalog_only_free') }}</span>
                    </label>
                </div>
            </form>

            @if ($usual['items'])
                <div class="cr-usuals" data-cr-usuals>
                    <span class="cr-usuals__label">{{ __($r.($usual['personal'] ? 'catalog_usual_mine' : 'catalog_usual_office')) }}</span>
                    @foreach ($usual['items'] as $quick)
                        @php
                            $quickLine = $inDraft[$quick['type'].':'.$quick['id']] ?? null;
                        @endphp
                        <span class="cr-usual {{ $quickLine ? 'cr-usual--in' : '' }}">
                            <span>{{ $quick['name'] }}</span>
                            @if ($quickLine)
                                <span class="cr-pill cr-pill--ok"><i class="fas fa-check" aria-hidden="true"></i> {{ $quickLine->qty }}</span>
                            @else
                                <form method="POST" action="{{ route('gov.requests.basket.add') }}" class="cr-inline-form" data-cr-add data-cr-quick>
                                    @csrf
                                    <input type="hidden" name="item_type" value="{{ $quick['type'] }}">
                                    <input type="hidden" name="item_id" value="{{ $quick['id'] }}">
                                    <input type="hidden" name="qty" value="1">
                                    <input type="hidden" name="context" value="catalog">
                                    <button type="submit" class="cr-usual__add" aria-label="{{ __($r.'catalog_add_label', ['item' => $quick['name']]) }}"><i class="fas fa-plus" aria-hidden="true"></i></button>
                                </form>
                            @endif
                        </span>
                    @endforeach
                </div>
            @endif

            <div id="cr-results" class="cr-results" aria-live="polite" aria-busy="false">
                @include('govstore::catalog.results')
            </div>
        </div>

        <aside class="cr-shop__aside" id="cr-drawer" data-cr-drawer aria-label="{{ __($r.'draft_title') }}">
            @include('govstore::basket.partials.drawer')
        </aside>
    </div>
    <div class="cr-toast" data-cr-toast role="status" aria-live="polite" hidden></div>
</div>
@endsection
