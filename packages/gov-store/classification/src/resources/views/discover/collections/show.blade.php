@extends('layouts/default')
@section('title', $collection->display_name)

@section('content')
@php
    $isBangla = app()->getLocale() === 'bn-BD';
    $total = $collection->nodes->count();
    $remaining = count($unadoptedCodes);
    $percent = $total > 0 ? round($adoptedCount / $total * 100) : 0;
    $formatCode = function ($code) {
        return implode(' ', str_split((string) $code, 2));
    };
@endphp
<div class="classification-theme gs-discover gs-collection">
    @include('gov-classification::discover.partials.nav', ['active' => 'collections'])

    <a href="{{ route('gov.catalog.discover.collections') }}" class="gs-discover-link gs-discover-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ __('classification::discover.detail.back') }}</a>

    <div class="gs-collection-hero">
        <div class="gs-collection-hero__intro">
            <span class="gs-collection-hero__icon" aria-hidden="true"><i class="{{ $collection->icon ?: 'fas fa-layer-group' }}"></i></span>
            <p class="gs-collection-eyebrow">{{ __('classification::discover.detail.eyebrow') }}</p>
            <h1 id="collection-title" class="gs-collection-hero__title">{{ $collection->display_name }}</h1>
            @if (! $isBangla && $collection->name_bn)
                <p class="gs-collection-hero__alt" lang="bn">{{ $collection->name_bn }}</p>
            @endif
            <p class="gs-collection-hero__desc">{{ $collection->display_description ?: __('classification::discover.detail.lede') }}</p>
            <ul class="gs-collection-meta">
                <li>{{ trans_choice('classification::discover.detail.item_count', $total, ['count' => number_format($total)]) }}</li>
                <li>{{ trans_choice('classification::discover.detail.groups_count', $groups->count(), ['count' => $groups->count()]) }}</li>
            </ul>
        </div>

        <aside class="gs-collection-decide" aria-labelledby="collection-progress-label">
            <p class="gs-collection-decide__label" id="collection-progress-label">{{ __('classification::discover.detail.your_catalog') }}</p>
            <p class="gs-collection-figure">
                <strong>{{ number_format($adoptedCount) }}</strong>
                <span>{{ __('classification::discover.detail.of_total', ['total' => number_format($total)]) }}</span>
                <em>{{ $percent }}%</em>
            </p>
            <div class="gs-collection-track" role="progressbar" aria-labelledby="collection-progress-label" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $adoptedCount }}">
                <span class="gs-collection-track__bar" style="width: {{ $percent }}%"></span>
            </div>

            <div class="gs-collection-decide__action">
                @if ($remaining === 0)
                    <div class="gs-collection-callout gs-collection-callout--success">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        <p>{{ __('classification::discover.detail.complete') }} <a href="{{ route('gov.catalog.my_catalog.index') }}">{{ __('classification::discover.detail.open_catalog') }}</a></p>
                    </div>
                @elseif ($canAdopt)
                    <x-gs::button size="lg" icon="fa-plus" class="gs-collection-decide__cta" onclick="adoptRemaining()">
                        {{ __($adoptedCount === 0 ? 'classification::discover.detail.add_all' : 'classification::discover.detail.add_remaining', ['count' => number_format($remaining)]) }}
                    </x-gs::button>
                    <p class="gs-collection-hint"><i class="fas fa-info-circle" aria-hidden="true"></i> {{ __('classification::discover.detail.add_hint') }}</p>
                @else
                    <div class="gs-collection-callout gs-collection-callout--info">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <p>{{ __('classification::discover.detail.no_permission') }}</p>
                    </div>
                @endif
            </div>

            <div class="gs-collection-does">
                <h2 class="gs-collection-does__title">{{ __('classification::discover.detail.what_title') }}</h2>
                <ul>
                    @foreach (__('classification::discover.detail.what') as $point)
                        <li><span class="gs-collection-tick" aria-hidden="true"><i class="fas fa-check"></i></span> <span>{{ $point }}</span></li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </div>

    @if ($groups->count() > 1)
        <section class="gs-collection-groups" aria-labelledby="groups-title">
            <h2 id="groups-title" class="gs-collection-h2">{{ __('classification::discover.detail.browse') }}</h2>
            <div class="gs-collection-groups__grid" role="group" aria-labelledby="groups-title">
                <button type="button" class="gs-collection-group-card" data-group="all" aria-pressed="true">
                    <span class="gs-collection-tile" aria-hidden="true"><i class="fas fa-th-large"></i></span>
                    <span class="gs-collection-group-card__text">
                        <span class="gs-collection-group-card__name">{{ __('classification::discover.detail.all_groups') }}</span>
                        <span class="gs-collection-group-card__sub">{{ trans_choice('classification::discover.detail.groups_count', $groups->count(), ['count' => $groups->count()]) }}</span>
                    </span>
                    <span class="gs-collection-group-card__foot">
                        <span class="gs-collection-group-card__nums"><span>{{ trans_choice('classification::discover.detail.item_count', $total, ['count' => number_format($total)]) }}</span><span>{{ __('classification::discover.detail.added_count', ['count' => number_format($adoptedCount)]) }}</span></span>
                        <span class="gs-collection-track gs-collection-track--sm" aria-hidden="true"><span class="gs-collection-track__bar" style="width: {{ $percent }}%"></span></span>
                    </span>
                </button>
                @foreach ($groups as $key => $group)
                    <button type="button" class="gs-collection-group-card" data-group="{{ $key }}" data-label="{{ $group['label'] }}" aria-pressed="false">
                        <span class="gs-collection-tile" aria-hidden="true"><i class="fas {{ $group['icon'] }}"></i></span>
                        <span class="gs-collection-group-card__text">
                            <span class="gs-collection-group-card__name">{{ $group['label'] }}</span>
                            @if ($group['code'])
                                <span class="gs-collection-group-card__sub gs-discover-mono">{{ $group['code'] }}</span>
                            @endif
                        </span>
                        <span class="gs-collection-group-card__foot">
                            <span class="gs-collection-group-card__nums"><span>{{ trans_choice('classification::discover.detail.item_count', $group['total'], ['count' => $group['total']]) }}</span><span>{{ __('classification::discover.detail.added_count', ['count' => $group['adopted']]) }}</span></span>
                            <span class="gs-collection-track gs-collection-track--sm" aria-hidden="true"><span class="gs-collection-track__bar" style="width: {{ round($group['adopted'] / max(1, $group['total']) * 100) }}%"></span></span>
                        </span>
                    </button>
                @endforeach
            </div>
        </section>
    @endif

    <section class="gs-collection-list" aria-labelledby="members-title">
        <div class="gs-collection-toolbar">
            <div class="gs-collection-toolbar__title">
                <h2 id="members-title" class="gs-collection-h2" data-all="{{ __('classification::discover.detail.list_title') }}">{{ __('classification::discover.detail.list_title') }}</h2>
                <p class="gs-discover-muted" id="members-showing" aria-live="polite" data-template="{{ __('classification::discover.detail.showing', ['shown' => ':shown', 'total' => ':total']) }}">{{ __('classification::discover.detail.showing', ['shown' => $total, 'total' => $total]) }}</p>
            </div>
            <div class="gs-discover-input gs-collection-search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <label for="member-filter" class="gs-sr-only">{{ __('classification::discover.detail.search_label') }}</label>
                <input id="member-filter" type="search" autocomplete="off" placeholder="{{ __('classification::discover.detail.search') }}">
            </div>
            <div class="gs-collection-seg" role="group" aria-label="{{ __('classification::discover.detail.status_label') }}">
                <button type="button" data-status-filter="all" aria-pressed="true">{{ __('classification::discover.detail.status_all') }} <span class="gs-collection-seg__n" data-count="all">{{ $total }}</span></button>
                <button type="button" data-status-filter="not-adopted" aria-pressed="false">{{ __('classification::discover.detail.status_not') }} <span class="gs-collection-seg__n" data-count="not-adopted">{{ $remaining }}</span></button>
                <button type="button" data-status-filter="adopted" aria-pressed="false">{{ __('classification::discover.detail.status_adopted') }} <span class="gs-collection-seg__n" data-count="adopted">{{ $adoptedCount }}</span></button>
            </div>
        </div>

        <div class="gs-collection-colhead" id="members-head">
            <span>
                @if ($canAdopt && $remaining > 0)
                    <input type="checkbox" class="gs-collection-check" id="member-select-all" aria-label="{{ __('classification::discover.detail.select_all') }}">
                @endif
            </span>
            <span>{{ __('classification::discover.detail.col_item') }}</span>
            <span>{{ __('classification::discover.detail.col_code') }}</span>
            <span>{{ __('classification::discover.detail.col_type') }}</span>
            <span>{{ __('classification::discover.detail.col_status') }}</span>
        </div>

        @foreach ($groups as $key => $group)
            <div class="gs-collection-group" data-group-block="{{ $key }}">
                <div class="gs-collection-group__head">
                    <span class="gs-collection-tile gs-collection-tile--sm" aria-hidden="true"><i class="fas {{ $group['icon'] }}"></i></span>
                    <div class="gs-collection-group__title">
                        <h3 class="gs-collection-group__name" id="group-{{ $key }}">{{ $group['label'] }}
                            @if ($group['code'])
                                <span class="gs-collection-group__code gs-discover-mono">{{ $group['code'] }}</span>
                            @endif
                        </h3>
                        <p class="gs-discover-muted">{{ __('classification::discover.detail.group_progress', ['adopted' => $group['adopted'], 'total' => $group['total']]) }}</p>
                    </div>
                    @if ($group['adopted'] === $group['total'])
                        <span class="gs-collection-group__done"><i class="fas fa-check" aria-hidden="true"></i> {{ __('classification::discover.detail.group_done') }}</span>
                    @elseif ($canAdopt)
                        <x-gs::button tone="secondary" outline size="sm" data-adopt-group="{{ $key }}">
                            {{ __($group['adopted'] === 0 ? 'classification::discover.detail.group_add_all' : 'classification::discover.detail.group_add_remaining', ['count' => $group['total'] - $group['adopted']]) }}
                        </x-gs::button>
                    @endif
                </div>
                <ul class="gs-collection-rows" aria-labelledby="group-{{ $key }}">
                    @foreach ($group['members'] as $pivot)
                        @php
                            $titleEn = $pivot->catalogNode?->title_en ?? $pivot->code;
                            $titleBn = $pivot->catalogNode?->enrichment?->title_bn;
                            $primary = $isBangla && $titleBn ? $titleBn : $titleEn;
                            $secondary = $isBangla ? ($titleBn ? $titleEn : null) : $titleBn;
                        @endphp
                        <li class="gs-collection-row" data-group="{{ $key }}" data-code="{{ $pivot->code }}" data-status="{{ $pivot->is_adopted ? 'adopted' : 'not-adopted' }}"
                            data-filter-text="{{ \Illuminate\Support\Str::lower($pivot->code.' '.$formatCode($pivot->code).' '.$titleEn.' '.$titleBn) }}">
                            <div class="gs-collection-row__check">
                                @if ($canAdopt && ! $pivot->is_adopted)
                                    <input type="checkbox" class="gs-collection-check member-checkbox" id="member-{{ $pivot->code }}" value="{{ $pivot->code }}">
                                @endif
                            </div>
                            <div class="gs-collection-row__name">
                                <label @if ($canAdopt && ! $pivot->is_adopted) for="member-{{ $pivot->code }}" @endif>
                                    <span class="gs-collection-row__primary" @if ($isBangla && $titleBn) lang="bn" @endif>{{ $primary }}</span>
                                    @if ($secondary)
                                        <span class="gs-collection-row__secondary" @if (! $isBangla) lang="bn" @endif>{{ $secondary }}</span>
                                    @endif
                                </label>
                            </div>
                            <div class="gs-collection-row__meta">
                                <span class="gs-collection-row__code gs-discover-mono">{{ $formatCode($pivot->code) }}</span>
                                <span>
                                    @if ($pivot->inventory_type)
                                        <span class="gs-collection-type gs-collection-type--{{ $pivot->inventory_type }}">{{ __('classification::collections.types.'.$pivot->inventory_type) }}@if ($pivot->unit) · {{ $pivot->unit }}@endif</span>
                                    @else
                                        <span class="gs-collection-type gs-collection-type--unset">{{ __('classification::discover.detail.type_unset') }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="gs-collection-row__status">
                                @if ($pivot->is_adopted)
                                    <span class="gs-collection-in"><span class="gs-collection-tick" aria-hidden="true"><i class="fas fa-check"></i></span> {{ __('classification::discover.detail.in_catalog') }}</span>
                                @elseif ($canAdopt)
                                    <x-gs::button outline size="sm" icon="fa-plus" data-adopt-code="{{ $pivot->code }}" aria-label="{{ __('classification::discover.detail.add_row', ['name' => $primary]) }}">{{ __('classification::discover.detail.add') }}</x-gs::button>
                                @else
                                    <span class="gs-collection-not">{{ __('classification::discover.detail.not_added') }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <button type="button" class="gs-collection-more" data-more="{{ $key }}" hidden
                    data-more-template="{{ __('classification::discover.detail.show_more', ['count' => ':count']) }}" data-fewer="{{ __('classification::discover.detail.show_fewer') }}"></button>
            </div>
        @endforeach

        <div class="gs-collection-empty" id="members-no-match" hidden>
            <span class="gs-collection-tile" aria-hidden="true"><i class="fas fa-search"></i></span>
            <h3>{{ __('classification::discover.detail.no_match_title') }}</h3>
            <p>{{ __('classification::discover.detail.no_match') }}</p>
            <x-gs::button tone="secondary" outline id="members-clear-filters">{{ __('classification::discover.detail.clear_filters') }}</x-gs::button>
        </div>
    </section>

    @if ($canAdopt && $remaining > 0)
        <div class="gs-discover-bulkbar" id="member-bulk-bar" role="region" aria-label="{{ __('classification::discover.detail.selection_actions') }}" hidden>
            <div class="gs-discover-bulkbar__inner">
                <strong id="member-selected-count" aria-live="polite" data-template="{{ __('classification::discover.detail.selected', ['count' => ':count']) }}"></strong>
                <span class="gs-discover-bulkbar__sep" aria-hidden="true"></span>
                <button type="button" class="gs-btn gs-btn--primary gs-btn--sm" id="member-adopt-selected">{{ __('classification::discover.detail.adopt_selected') }}</button>
                <button type="button" class="gs-discover-bulkbar__clear" id="member-clear">{{ __('classification::discover.detail.clear') }}</button>
            </div>
        </div>

        @include('gov-classification::adopt.partials.bulk-preview')
    @endif
</div>
@endsection

@section('moar_scripts')
@parent
<script>
function adoptCodes(codes) {
    if (codes.length > 0) triggerBulkAdoption(codes, @json($collection->id));
}

function adoptRemaining() {
    adoptCodes(@json($unadoptedCodes));
}

document.addEventListener('DOMContentLoaded', function () {
    var PREVIEW = 6;
    var rows = Array.prototype.slice.call(document.querySelectorAll('.gs-collection-row'));
    var blocks = Array.prototype.slice.call(document.querySelectorAll('[data-group-block]'));
    var cards = Array.prototype.slice.call(document.querySelectorAll('.gs-collection-group-card'));
    var boxes = Array.prototype.slice.call(document.querySelectorAll('.member-checkbox'));
    var selectAll = document.getElementById('member-select-all');
    var bar = document.getElementById('member-bulk-bar');
    var search = document.getElementById('member-filter');
    var state = { group: 'all', status: 'all', text: '', expanded: {} };

    function visibleRows() {
        return rows.filter(function (row) { return !row.hidden; });
    }

    function syncSelection() {
        var count = boxes.filter(function (box) { return box.checked; }).length;
        boxes.forEach(function (box) { box.closest('.gs-collection-row').classList.toggle('is-selected', box.checked); });
        if (bar) {
            bar.hidden = count === 0;
            var label = document.getElementById('member-selected-count');
            label.textContent = label.dataset.template.replace(':count', count);
        }
        if (selectAll) {
            var shown = visibleRows().map(function (row) { return row.querySelector('.member-checkbox'); }).filter(Boolean);
            selectAll.checked = shown.length > 0 && shown.every(function (box) { return box.checked; });
            selectAll.disabled = shown.length === 0;
        }
    }

    function apply() {
        var counts = { all: 0, 'not-adopted': 0, adopted: 0 };
        var narrowed = state.group !== 'all' || state.status !== 'all' || state.text !== '';
        var shown = 0;
        blocks.forEach(function (block) {
            var key = block.dataset.groupBlock;
            var matched = 0;
            var inGroup = state.group === 'all' || key === state.group;
            var open = narrowed || state.expanded[key];
            block.querySelectorAll('.gs-collection-row').forEach(function (row) {
                var inText = !state.text || row.dataset.filterText.indexOf(state.text) !== -1;
                if (inGroup && inText) {
                    counts.all++;
                    counts[row.dataset.status]++;
                }
                var match = inGroup && inText && (state.status === 'all' || row.dataset.status === state.status);
                if (match) matched++;
                row.hidden = !match || (!open && matched > PREVIEW);
                if (!row.hidden) shown++;
            });
            block.hidden = matched === 0;
            var more = block.querySelector('[data-more]');
            var extra = matched - PREVIEW;
            more.hidden = narrowed || extra <= 0;
            more.textContent = state.expanded[key] ? more.dataset.fewer : more.dataset.moreTemplate.replace(':count', extra);
            more.setAttribute('aria-expanded', state.expanded[key] ? 'true' : 'false');
        });
        Object.keys(counts).forEach(function (key) {
            var badge = document.querySelector('[data-count="' + key + '"]');
            if (badge) badge.textContent = counts[key];
        });
        document.getElementById('members-no-match').hidden = shown > 0;
        document.getElementById('members-head').hidden = shown === 0;
        var showing = document.getElementById('members-showing');
        showing.textContent = showing.dataset.template.replace(':shown', shown).replace(':total', rows.length);
        var title = document.getElementById('members-title');
        var card = cards.filter(function (item) { return item.dataset.group === state.group; })[0];
        title.textContent = state.group === 'all' || !card ? title.dataset.all : card.dataset.label;
        syncSelection();
    }

    function press(selector, pressed) {
        document.querySelectorAll(selector).forEach(function (other) { other.setAttribute('aria-pressed', other === pressed ? 'true' : 'false'); });
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            var key = state.group === card.dataset.group && card.dataset.group !== 'all' ? 'all' : card.dataset.group;
            state.group = key;
            press('.gs-collection-group-card', cards.filter(function (item) { return item.dataset.group === key; })[0]);
            apply();
        });
    });
    document.querySelectorAll('[data-status-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            state.status = button.dataset.statusFilter;
            press('[data-status-filter]', button);
            apply();
        });
    });
    search.addEventListener('input', function () {
        state.text = search.value.trim().toLowerCase();
        apply();
    });
    document.querySelectorAll('[data-more]').forEach(function (button) {
        button.addEventListener('click', function () {
            state.expanded[button.dataset.more] = !state.expanded[button.dataset.more];
            apply();
        });
    });
    document.getElementById('members-clear-filters').addEventListener('click', function () {
        state = { group: 'all', status: 'all', text: '', expanded: state.expanded };
        search.value = '';
        press('.gs-collection-group-card', cards[0]);
        press('[data-status-filter]', document.querySelector('[data-status-filter="all"]'));
        apply();
    });

    document.querySelectorAll('[data-adopt-code]').forEach(function (button) {
        button.addEventListener('click', function () { adoptCodes([button.dataset.adoptCode]); });
    });
    document.querySelectorAll('[data-adopt-group]').forEach(function (button) {
        button.addEventListener('click', function () {
            adoptCodes(rows.filter(function (row) {
                return row.dataset.group === button.dataset.adoptGroup && row.dataset.status === 'not-adopted';
            }).map(function (row) { return row.dataset.code; }));
        });
    });

    boxes.forEach(function (box) { box.addEventListener('change', syncSelection); });
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            visibleRows().forEach(function (row) {
                var box = row.querySelector('.member-checkbox');
                if (box) box.checked = selectAll.checked;
            });
            syncSelection();
        });
    }
    if (bar) {
        document.getElementById('member-clear').addEventListener('click', function () {
            boxes.forEach(function (box) { box.checked = false; });
            syncSelection();
        });
        document.getElementById('member-adopt-selected').addEventListener('click', function () {
            adoptCodes(boxes.filter(function (box) { return box.checked; }).map(function (box) { return box.value; }));
        });
    }
    apply();
});
</script>
@endsection
