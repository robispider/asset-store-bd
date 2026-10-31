@extends('layouts/default')
@section('title', __('classification::discover.explorer.title'))

@section('content')
@php
    $isLocal = $mode === 'local';
    $current = $breadcrumbs->last();
    $levels = __('classification::discover.explorer.levels');
    $childLevel = $current ? min(4, $current->level + 1) : 1;
    $upCode = $breadcrumbs->count() > 1 ? $breadcrumbs[$breadcrumbs->count() - 2]->code : null;
    $currentSegment = $breadcrumbs->first()?->code;
    $explorerUrl = function (array $params = []) use ($mode) {
        return route('gov.catalog.discover.explorer', array_merge(['mode' => $mode], $params));
    };
    $formatCode = function ($code) {
        return implode(' ', str_split((string) $code, 2));
    };
    $rows = $nodes->getCollection()->map(function ($node) {
        $status = $node->is_folder ? 'folder' : ($node->is_global ? 'shared' : ($node->is_adopted ? 'adopted' : 'not-adopted'));

        return ['node' => $node, 'status' => $status];
    });
    $counts = $rows->countBy('status');
    $filters = collect([
        'all' => [__('classification::discover.explorer.filter_all'), $rows->count()],
        'not-adopted' => [__('classification::discover.explorer.filter_not'), $counts['not-adopted'] ?? 0],
        'adopted' => [__('classification::discover.explorer.filter_adopted'), $counts['adopted'] ?? 0],
        'shared' => [__('classification::discover.explorer.filter_shared'), $counts['shared'] ?? 0],
        'folder' => [__('classification::discover.explorer.filter_folders'), $counts['folder'] ?? 0],
    ])->filter(function ($filter, $key) {
        return $key === 'all' || $filter[1] > 0;
    });
    $currentTitle = $current ? $current->title_en : __('classification::discover.explorer.root_title');
@endphp
<div class="classification-theme gs-discover">
    @include('gov-classification::discover.partials.nav', ['active' => 'explorer'])

    <header class="gs-discover-hero gs-discover-hero--compact">
        <div class="gs-discover-hero__text">
            <h1 class="gs-discover-title gs-discover-title--sm">{{ __('classification::discover.explorer.title') }}</h1>
            <p class="gs-discover-lede">{{ $isLocal ? __('classification::discover.explorer.lede_local') : __('classification::discover.explorer.lede_master') }}</p>
        </div>
        <div class="gs-discover-hero__tools">
            <div class="gs-discover-switch" role="group" aria-label="{{ __('classification::discover.explorer.source_label') }}">
                <a href="{{ route('gov.catalog.discover.explorer', ['mode' => 'master', 'parent' => $parentCode]) }}" @class(['gs-discover-switch__opt', 'is-active' => ! $isLocal]) @if (! $isLocal) aria-current="page" @endif>
                    <i class="fas fa-globe" aria-hidden="true"></i> {{ __('classification::discover.explorer.master') }}
                </a>
                <a href="{{ route('gov.catalog.discover.explorer', ['mode' => 'local', 'parent' => $parentCode]) }}" @class(['gs-discover-switch__opt', 'is-active' => $isLocal]) @if ($isLocal) aria-current="page" @endif>
                    <i class="fas fa-building" aria-hidden="true"></i> {{ __('classification::discover.explorer.local') }}
                </a>
            </div>
            <a class="gs-discover-link" href="{{ route('gov.catalog.my_catalog.index') }}"><i class="fas fa-list" aria-hidden="true"></i> {{ __('classification::discover.explorer.list_view') }}</a>
        </div>
    </header>

    <div class="gs-discover-explorer">
        <aside class="gs-discover-rail" aria-labelledby="discover-segments-title">
            <h2 id="discover-segments-title" class="gs-discover-rail__title">{{ __('classification::discover.explorer.segments') }}</h2>
            <div class="gs-discover-input">
                <i class="fas fa-search" aria-hidden="true"></i>
                <label for="segment-filter" class="gs-sr-only">{{ __('classification::discover.explorer.filter_segments') }}</label>
                <input id="segment-filter" type="search" autocomplete="off" placeholder="{{ __('classification::discover.explorer.filter_segments') }}">
            </div>
            <ul class="gs-discover-rail__list" id="segment-list">
                @foreach ($segments as $segment)
                    <li data-filter-text="{{ \Illuminate\Support\Str::lower($segment->code.' '.$segment->title_en) }}">
                        <a href="{{ $explorerUrl(['parent' => $segment->code]) }}" @class(['gs-discover-rail__item', 'is-active' => $segment->code === $currentSegment]) @if ($segment->code === $currentSegment) aria-current="true" @endif>
                            <span class="gs-discover-mono">{{ substr($segment->code, 0, 2) }}</span>
                            <span>{{ $segment->title_en }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>

        <section class="gs-discover-panel" aria-labelledby="discover-level-title">
            <div class="gs-discover-panel__head">
                <nav class="gs-discover-crumbs" aria-label="{{ __('classification::discover.explorer.location') }}">
                    <a href="{{ $explorerUrl() }}"><i class="fas fa-home" aria-hidden="true"></i> {{ $isLocal ? __('classification::discover.explorer.local') : __('classification::discover.explorer.master') }}</a>
                    @foreach ($breadcrumbs as $crumb)
                        <i class="fas fa-chevron-right gs-discover-crumbs__sep" aria-hidden="true"></i>
                        @if (! $loop->last)
                            <a href="{{ $explorerUrl(['parent' => $crumb->code]) }}">{{ $crumb->title_en }}</a>
                        @else
                            <span aria-current="page">{{ $crumb->title_en }}</span>
                        @endif
                    @endforeach
                </nav>
                <div class="gs-discover-level">
                    <div class="gs-discover-level__name">
                        @if ($parentCode)
                            <a href="{{ $explorerUrl(['parent' => $upCode]) }}" class="gs-discover-iconbtn" aria-label="{{ __('classification::discover.explorer.up') }}" title="{{ __('classification::discover.explorer.up') }}"><i class="fas fa-arrow-up" aria-hidden="true"></i></a>
                        @endif
                        <div>
                            <h2 id="discover-level-title" class="gs-discover-level__title">{{ $currentTitle }}</h2>
                            <p class="gs-discover-muted">
                                @if ($current)
                                    <span class="gs-discover-mono">{{ $formatCode($current->code) }}</span> · {{ $levels[$current->level] ?? '' }} ·
                                @endif
                                {{ __('classification::discover.explorer.children.'.$childLevel, ['count' => number_format($nodes->total())]) }}
                            </p>
                        </div>
                    </div>
                    @if ($current && $current->level < 4)
                        <div class="gs-discover-actions">
                            @if ($canManageCollections)
                                <x-gs::button tone="secondary" outline icon="fa-layer-group" onclick="triggerAddToCollection(['{{ $current->code }}'])">{{ __('classification::discover.explorer.add_level') }}</x-gs::button>
                            @endif
                            @if (! $isLocal)
                                <x-gs::button icon="fa-check" onclick="triggerBulkAdoption(['{{ $current->code }}'])">{{ __('classification::discover.explorer.adopt_level') }}</x-gs::button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if ($rows->isNotEmpty())
                <div class="gs-discover-toolbar">
                    <div class="gs-discover-pills" role="group" aria-label="{{ __('classification::discover.explorer.filter_label') }}">
                        @foreach ($filters as $key => $filter)
                            <button type="button" class="gs-discover-pill" data-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $filter[0] }} <span class="gs-discover-pill__count">{{ $filter[1] }}</span></button>
                        @endforeach
                    </div>
                    <div class="gs-discover-input gs-discover-input--grow">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <label for="row-filter" class="gs-sr-only">{{ __('classification::discover.explorer.search_level', ['name' => $currentTitle]) }}</label>
                        <input id="row-filter" type="search" autocomplete="off" placeholder="{{ __('classification::discover.explorer.search_level', ['name' => $currentTitle]) }}">
                    </div>
                </div>

                <x-gs::table class="gs-discover-table">
                    <thead>
                        <tr>
                            <th scope="col" class="gs-discover-table__check"><input type="checkbox" id="select-all" aria-label="{{ __('classification::discover.explorer.select_all') }}"></th>
                            <th scope="col">{{ __('classification::discover.explorer.col_item') }}</th>
                            <th scope="col" class="gs-discover-table__status">{{ __('classification::discover.explorer.col_status') }}</th>
                            <th scope="col" class="gs-discover-table__actions"><span class="gs-sr-only">{{ __('classification::discover.explorer.col_actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $node = $row['node'];
                                $openUrl = $explorerUrl(['parent' => $node->code]);
                            @endphp
                            <tr data-status="{{ $row['status'] }}" data-filter-text="{{ \Illuminate\Support\Str::lower($node->code.' '.$node->title_en) }}">
                                <td class="gs-discover-table__check">
                                    @if (! $node->is_folder || ! $isLocal)
                                        <input type="checkbox" class="node-checkbox" value="{{ $node->code }}" aria-label="{{ __('classification::discover.explorer.select_row', ['name' => $node->title_en]) }}">
                                    @endif
                                </td>
                                <td>
                                    <div class="gs-discover-item">
                                        <span class="gs-discover-item__icon" aria-hidden="true"><i class="fas {{ $node->is_folder ? 'fa-folder' : 'fa-cube' }}"></i></span>
                                        <div class="gs-discover-item__text">
                                            @if ($node->is_folder)
                                                <a href="{{ $openUrl }}" class="gs-discover-item__title">{{ $node->title_en }}</a>
                                            @else
                                                <span class="gs-discover-item__title">{{ $node->title_en }}</span>
                                            @endif
                                            <span class="gs-discover-mono gs-discover-item__code">{{ $formatCode($node->code) }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="gs-discover-table__status">
                                    @switch($row['status'])
                                        @case('folder')
                                            <x-gs::badge tone="secondary" outline pill>{{ $levels[$node->level] ?? __('classification::discover.explorer.status_folder') }}</x-gs::badge>
                                            @break
                                        @case('shared')
                                            <x-gs::badge tone="info" pill><i class="fas fa-globe" aria-hidden="true"></i> {{ __('classification::discover.explorer.status_shared') }}</x-gs::badge>
                                            @break
                                        @case('adopted')
                                            <x-gs::badge tone="success" pill><i class="fas fa-check" aria-hidden="true"></i> {{ __('classification::discover.explorer.status_adopted') }}</x-gs::badge>
                                            @break
                                        @default
                                            <x-gs::badge tone="secondary" outline pill>{{ __('classification::discover.explorer.status_not') }}</x-gs::badge>
                                    @endswitch
                                </td>
                                <td class="gs-discover-table__actions">
                                    <div class="gs-discover-rowactions">
                                        @if ($node->is_folder)
                                            <a href="{{ $openUrl }}" class="gs-btn gs-btn--ghost gs-btn--sm">{{ __('classification::discover.explorer.open') }}</a>
                                        @elseif ($isLocal && ($node->is_adopted || $node->is_global) && $node->snipeMapping)
                                            <a href="{{ route('gov.catalog.my_catalog.show', $node->snipeMapping->category_id) }}" class="gs-btn gs-btn--ghost gs-btn--sm">{{ __('classification::discover.explorer.manage') }}</a>
                                        @elseif (! $node->is_adopted && ! $node->is_global)
                                            <button type="button" class="gs-btn gs-btn--primary gs-btn--outline gs-btn--sm" onclick="triggerBulkAdoption(['{{ $node->code }}'])">{{ __('classification::discover.explorer.adopt') }}</button>
                                        @else
                                            <a href="{{ route('gov.catalog.mapping.show', $node->code) }}" class="gs-btn gs-btn--ghost gs-btn--sm">{{ __('classification::discover.explorer.details') }}</a>
                                        @endif
                                        @if ($canManageCollections)
                                            <button type="button" class="gs-discover-iconbtn gs-discover-iconbtn--sm" onclick="triggerAddToCollection(['{{ $node->code }}'])"
                                                aria-label="{{ __('classification::discover.explorer.add_row', ['name' => $node->title_en]) }}" title="{{ __('classification::discover.explorer.add_one') }}"><i class="fas fa-layer-group" aria-hidden="true"></i></button>
                                        @endif
                                        <div class="btn-group">
                                            <button class="gs-discover-iconbtn gs-discover-iconbtn--sm gs-discover-iconbtn--plain dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                                aria-label="{{ __('classification::discover.explorer.more_actions', ['name' => $node->title_en]) }}"><i class="fas fa-ellipsis-v" aria-hidden="true"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-right">
                                                @if ($node->is_folder)
                                                    <li><a href="{{ $openUrl }}"><i class="fas fa-folder-open fa-fw" aria-hidden="true"></i> {{ __('classification::discover.explorer.browse') }}</a></li>
                                                    @if (! $isLocal)
                                                        <li><a href="#" onclick="event.preventDefault(); triggerBulkAdoption(['{{ $node->code }}'])"><i class="fas fa-check fa-fw" aria-hidden="true"></i> {{ __('classification::discover.explorer.adopt_all_commodities') }}</a></li>
                                                    @endif
                                                    @if ($canManageCollections)
                                                        <li><a href="#" onclick="event.preventDefault(); triggerAddToCollection(['{{ $node->code }}'])"><i class="fas fa-layer-group fa-fw" aria-hidden="true"></i> {{ __('classification::discover.explorer.add_all') }}</a></li>
                                                    @endif
                                                @else
                                                    @if ($isLocal && ($node->is_adopted || $node->is_global) && $node->snipeMapping)
                                                        <li><a href="{{ route('gov.catalog.my_catalog.show', $node->snipeMapping->category_id) }}"><i class="fas fa-cog fa-fw" aria-hidden="true"></i> {{ __('classification::discover.explorer.manage_local') }}</a></li>
                                                    @else
                                                        <li><a href="{{ route('gov.catalog.mapping.show', $node->code) }}"><i class="fas fa-eye fa-fw" aria-hidden="true"></i> {{ __('classification::discover.explorer.view_metadata') }}</a></li>
                                                        @if (! $node->is_adopted && ! $node->is_global)
                                                            <li><a href="#" onclick="event.preventDefault(); triggerBulkAdoption(['{{ $node->code }}'])"><i class="fas fa-check fa-fw" aria-hidden="true"></i> {{ __('classification::discover.explorer.adopt_category') }}</a></li>
                                                        @endif
                                                    @endif
                                                    @if ($canManageCollections)
                                                        <li><a href="#" onclick="event.preventDefault(); triggerAddToCollection(['{{ $node->code }}'])"><i class="fas fa-layer-group fa-fw" aria-hidden="true"></i> {{ __('classification::discover.explorer.add_one') }}</a></li>
                                                    @endif
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr id="discover-no-match" hidden><td colspan="4" class="gs-table__empty">{{ __('classification::discover.explorer.no_match') }}</td></tr>
                    </tbody>
                </x-gs::table>
                <div class="gs-discover-panel__foot">
                    <p class="gs-discover-muted" id="discover-showing" data-template="{{ __('classification::discover.explorer.showing', ['shown' => ':shown', 'total' => number_format($nodes->total())]) }}">{{ __('classification::discover.explorer.showing', ['shown' => $rows->count(), 'total' => number_format($nodes->total())]) }}</p>
                    {{ $nodes->appends(['parent' => $parentCode, 'mode' => $mode])->links() }}
                </div>
            @else
                <x-gs::empty-state icon="fa-folder-open" :title="__('classification::discover.explorer.empty_title')">
                    {{ $isLocal ? __('classification::discover.explorer.empty_local') : __('classification::discover.explorer.empty_master') }}
                    @if ($isLocal)
                        <x-slot:actions>
                            <x-gs::button tone="secondary" outline :href="route('gov.catalog.discover.explorer', ['mode' => 'master', 'parent' => $parentCode])">{{ __('classification::discover.explorer.to_master') }}</x-gs::button>
                        </x-slot:actions>
                    @endif
                </x-gs::empty-state>
            @endif
        </section>
    </div>

    <div class="gs-discover-bulkbar" id="bulk-action-bar" role="region" aria-label="{{ __('classification::discover.explorer.selection_actions') }}" hidden>
        <div class="gs-discover-bulkbar__inner">
            <strong id="selected-count" aria-live="polite" data-template="{{ __('classification::discover.explorer.selected', ['count' => ':count']) }}"></strong>
            <span class="gs-discover-bulkbar__sep" aria-hidden="true"></span>
            @if (! $isLocal)
                <button class="gs-btn gs-btn--primary gs-btn--sm" type="button" onclick="executeExplorerBulkAdoption()">{{ __('classification::discover.explorer.adopt_selected') }}</button>
            @endif
            @if ($canManageCollections)
                <button type="button" class="gs-discover-bulkbar__btn" onclick="executeExplorerBulkAddToCollection()">{{ __('classification::discover.explorer.add_selected') }}</button>
            @endif
            <button type="button" class="gs-discover-bulkbar__clear" id="bulk-clear">{{ __('classification::discover.explorer.clear') }}</button>
        </div>
    </div>

    @include('gov-classification::adopt.partials.bulk-preview')
    @include('gov-classification::discover.partials.collection-modal')
</div>
@endsection

@section('moar_scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function () {
    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.node-checkbox'));
    var selectAll = document.getElementById('select-all');
    var bar = document.getElementById('bulk-action-bar');
    var countLabel = document.getElementById('selected-count');
    var activeFilter = 'all';

    function visibleRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.gs-discover-table tbody tr[data-status]')).filter(function (row) { return !row.hidden; });
    }

    function updateActionBar() {
        var count = checkboxes.filter(function (box) { return box.checked; }).length;
        countLabel.textContent = countLabel.dataset.template.replace(':count', count);
        bar.hidden = count === 0;
        checkboxes.forEach(function (box) { box.closest('tr').classList.toggle('is-selected', box.checked); });
        if (selectAll) {
            var visible = visibleRows().map(function (row) { return row.querySelector('.node-checkbox'); }).filter(Boolean);
            selectAll.checked = visible.length > 0 && visible.every(function (box) { return box.checked; });
        }
    }

    function applyFilters() {
        var input = document.getElementById('row-filter');
        var text = input ? input.value.trim().toLowerCase() : '';
        var shown = 0;
        document.querySelectorAll('.gs-discover-table tbody tr[data-status]').forEach(function (row) {
            var match = (activeFilter === 'all' || row.dataset.status === activeFilter) && (!text || row.dataset.filterText.indexOf(text) !== -1);
            row.hidden = !match;
            if (match) shown++;
        });
        var empty = document.getElementById('discover-no-match');
        if (empty) empty.hidden = shown > 0;
        var showing = document.getElementById('discover-showing');
        if (showing) showing.textContent = showing.dataset.template.replace(':shown', shown);
        updateActionBar();
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            visibleRows().forEach(function (row) {
                var box = row.querySelector('.node-checkbox');
                if (box) box.checked = selectAll.checked;
            });
            updateActionBar();
        });
    }
    checkboxes.forEach(function (box) { box.addEventListener('change', updateActionBar); });
    document.getElementById('bulk-clear').addEventListener('click', function () {
        checkboxes.forEach(function (box) { box.checked = false; });
        updateActionBar();
    });

    document.querySelectorAll('.gs-discover-pill').forEach(function (pill) {
        pill.addEventListener('click', function () {
            activeFilter = pill.dataset.filter;
            document.querySelectorAll('.gs-discover-pill').forEach(function (other) { other.setAttribute('aria-pressed', other === pill ? 'true' : 'false'); });
            applyFilters();
        });
    });
    var rowFilter = document.getElementById('row-filter');
    if (rowFilter) rowFilter.addEventListener('input', applyFilters);

    var segmentFilter = document.getElementById('segment-filter');
    segmentFilter.addEventListener('input', function () {
        var text = segmentFilter.value.trim().toLowerCase();
        document.querySelectorAll('#segment-list li').forEach(function (item) {
            item.hidden = text && item.dataset.filterText.indexOf(text) === -1;
        });
    });
    var activeSegment = document.querySelector('.gs-discover-rail__item.is-active');
    if (activeSegment) {
        var list = document.getElementById('segment-list');
        list.scrollTop = activeSegment.offsetTop - list.offsetTop - list.clientHeight / 3;
    }
});

function selectedExplorerCodes() {
    return Array.prototype.slice.call(document.querySelectorAll('.node-checkbox:checked')).map(function (box) { return box.value; });
}

function executeExplorerBulkAdoption() {
    var codes = selectedExplorerCodes();
    if (codes.length > 0) triggerBulkAdoption(codes);
}

function executeExplorerBulkAddToCollection() {
    var codes = selectedExplorerCodes();
    if (codes.length > 0) triggerAddToCollection(codes);
}
</script>
@endsection
