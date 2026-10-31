@extends('layouts/default')

@section('title')
    {{ __('classification::my_catalog.title') }}
    @parent
@stop

@section('content')
@php
    $t = fn (string $key, array $replace = []) => __('classification::my_catalog.'.$key, $replace);
    $q = $filters['q'];
    $listQuery = array_filter(['tab' => $activeTab, 'q' => $q !== '' ? $q : null]);

    $tabs = [];
    foreach (['active' => $metrics['total_active'], 'cleanup' => $metrics['needs_cleanup'], 'archived' => $metrics['archived_count']] as $key => $count) {
        $tabs[] = ['key' => $key, 'label' => $t('tab_'.$key), 'count' => $count,
            'href' => route('gov.catalog.my_catalog.index', array_filter(['tab' => $key, 'q' => $q !== '' ? $q : null]))];
    }

    $typeIcons = ['asset' => 'fa-desktop', 'component' => 'fa-microchip', 'accessory' => 'fa-computer-mouse', 'consumable' => 'fa-box-open', 'license' => 'fa-key'];
    $chips = [['key' => null, 'label' => $t('type_all'), 'count' => array_sum($typeCounts)]];
    foreach (\GovStore\Classification\Services\MyCatalogService::TYPES as $type) {
        if (($typeCounts[$type] ?? 0) > 0 || $filters['type'] === $type) {
            $chips[] = ['key' => $type, 'label' => $t('type_'.$type), 'count' => $typeCounts[$type] ?? 0];
        }
    }

    $total = max(1, $metrics['total_active']);
    $okPct = round($metrics['in_use'] / $total * 100, 1);
    $warnPct = round($metrics['needs_cleanup'] / $total * 100, 1);
    $maxItems = max(1, $metrics['top']['count'] ?? 1);

    $canAct = ! $isReadOnly;
    $canSelect = $canAct && $activeTab === 'cleanup';
    $openByDefault = $activeTab === 'cleanup' || $q !== '';
    $hasGroups = collect($families)->contains(fn ($f) => count($f['rows']) > 1);
    $colspan = 5 + ($canSelect ? 1 : 0) + ($canAct ? 1 : 0);

    $source = function ($row) use ($t) {
        return match ($row->governance_type) {
            'global' => ['fa-globe', $t('source_global')],
            'company', 'location' => ['fa-building', $t('source_organization')],
            default => ['fa-server', $t('source_native')],
        };
    };
    $rowStatus = function ($row) use ($activeTab, $t) {
        if ($activeTab === 'archived') {
            return ['archived', $t('status_archived')];
        }

        return $row->total_usage_count > 0 ? ['success', $t('status_in_use')] : ['warning', $t('status_unused')];
    };

    if ($q !== '') {
        [$emptyTitle, $emptyBody] = [$t('empty_search_title'), $t('empty_search_body', ['q' => $q])];
    } elseif ($filters['type']) {
        [$emptyTitle, $emptyBody] = [$t('empty_search_title'), $t('empty_type_body')];
    } else {
        [$emptyTitle, $emptyBody] = [$t('empty_'.$activeTab.'_title'), $t('empty_'.$activeTab.'_body')];
    }

    $jsText = [
        'archivedOne' => $t('toast_archived_one'), 'archivedMany' => $t('toast_archived_many'),
        'restoredOne' => $t('toast_restored_one'), 'restoredMany' => $t('toast_restored_many'),
        'archiveAll' => $t('archive_all'), 'restoreAll' => $t('restore_all'),
        'expandAll' => $t('expand_all'), 'collapseAll' => $t('collapse_all'),
        'failed' => $t('action_failed'),
    ];
@endphp
<div class="gs-my-catalog" id="gs-my-catalog" data-tab="{{ $activeTab }}"
    data-archive-url="{{ route('gov.catalog.my_catalog.archive') }}" data-restore-url="{{ route('gov.catalog.my_catalog.restore') }}">
    @include('gov-classification::discover.partials.nav', ['active' => 'my_catalog'])

    <x-gs::page-header :title="$t('title')" :subtitle="$t('subtitle')">
        @if ($office)
            <x-slot:meta>
                <span class="gs-my-catalog__office"><i class="fas fa-building" aria-hidden="true"></i><span class="gs-sr-only">{{ $t('office') }}:</span> {{ $office }}</span>
            </x-slot:meta>
        @endif
        <x-slot:actions>
            <x-gs::button tone="light" outline icon="fa-sitemap" :href="route('gov.catalog.discover.explorer', ['mode' => 'local'])">{{ $t('explorer_view') }}</x-gs::button>
            @if ($canAct)
                <x-gs::button icon="fa-plus" :href="route('gov.catalog.discover.collections')">{{ $t('add_categories') }}</x-gs::button>
            @endif
        </x-slot:actions>
    </x-gs::page-header>

    <div class="gs-my-catalog__summary" role="region" aria-label="{{ $t('summary_label') }}">
        <div class="gs-my-catalog__panel">
            <span class="gs-my-catalog__eyebrow">{{ $t('health_label') }}</span>
            <p class="gs-my-catalog__figure"><strong>{{ $t('health_value', ['in_use' => number_format($metrics['in_use']), 'total' => number_format($metrics['total_active'])]) }}</strong> <span>{{ $t('health_caption') }}</span></p>
            <div class="gs-my-catalog__health" aria-hidden="true">
                <span class="gs-my-catalog__health-ok" style="width: {{ $okPct }}%"></span>
                <span class="gs-my-catalog__health-warn" style="width: {{ $warnPct }}%"></span>
            </div>
            <ul class="gs-my-catalog__legend">
                <li><span class="gs-my-catalog__dot gs-my-catalog__dot--ok"></span>{{ $t('legend_in_use') }} <strong>{{ number_format($metrics['in_use']) }}</strong></li>
                <li><span class="gs-my-catalog__dot gs-my-catalog__dot--warn"></span>{{ $t('legend_unused') }} <strong>{{ number_format($metrics['needs_cleanup']) }}</strong></li>
                <li><span class="gs-my-catalog__dot gs-my-catalog__dot--muted"></span>{{ $t('legend_archived') }} <strong>{{ number_format($metrics['archived_count']) }}</strong></li>
            </ul>
        </div>

        <div class="gs-my-catalog__panel">
            <span class="gs-my-catalog__eyebrow">{{ $t('items_label') }}</span>
            <p class="gs-my-catalog__figure"><strong>{{ number_format($metrics['items']) }}</strong> <span>{{ $t('items_caption', ['count' => number_format($metrics['families'])]) }}</span></p>
            @if ($metrics['top'])
                <p class="gs-my-catalog__note">{{ $t('top_used', ['name' => $metrics['top']['name'], 'count' => number_format($metrics['top']['count'])]) }}</p>
            @endif
            <p class="gs-my-catalog__note">
                {{ $t('unspsc_mapped', ['mapped' => number_format($metrics['total_active'] - $metrics['unmapped']), 'total' => number_format($metrics['total_active'])]) }}
                @if ($canMap)
                    · <a href="{{ route('gov.catalog.mapping') }}">{{ $t('map_codes') }}</a>
                @endif
            </p>
        </div>

        @if ($metrics['needs_cleanup'] > 0)
            <div class="gs-my-catalog__panel gs-my-catalog__panel--attention">
                <i class="fas fa-triangle-exclamation gs-my-catalog__panel-icon" aria-hidden="true"></i>
                <p class="gs-my-catalog__panel-title">{{ trans_choice('classification::my_catalog.attention_title', $metrics['needs_cleanup'], ['count' => number_format($metrics['needs_cleanup'])]) }}</p>
                <p class="gs-my-catalog__note">{{ $canAct ? $t('attention_body') : $t('attention_body_readonly') }}</p>
                @if ($canAct && $activeTab !== 'cleanup')
                    <x-gs::button tone="light" size="sm" :href="route('gov.catalog.my_catalog.index', ['tab' => 'cleanup'])">
                        {{ $t('attention_action', ['count' => number_format($metrics['needs_cleanup'])]) }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </x-gs::button>
                @endif
            </div>
        @else
            <div class="gs-my-catalog__panel gs-my-catalog__panel--tidy">
                <i class="fas fa-circle-check gs-my-catalog__panel-icon" aria-hidden="true"></i>
                <p class="gs-my-catalog__panel-title">{{ $t('tidy_title') }}</p>
                <p class="gs-my-catalog__note">{{ $t('tidy_body') }}</p>
            </div>
        @endif
    </div>

    <div class="gs-my-catalog__list">
        <div class="gs-my-catalog__bar">
            <x-gs::status-tabs :tabs="$tabs" :active="$activeTab" :label="$t('tabs_label')" />
            <form class="gs-my-catalog__search" method="get" action="{{ route('gov.catalog.my_catalog.index') }}" role="search">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                @if ($filters['type'])<input type="hidden" name="type" value="{{ $filters['type'] }}">@endif
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="q" id="gs-my-catalog-q" value="{{ $q }}" maxlength="100"
                    placeholder="{{ $t('search_placeholder') }}" aria-label="{{ $t('search_label') }}">
                <button type="submit" class="gs-sr-only">{{ $t('search_submit') }}</button>
            </form>
        </div>

        <div class="gs-my-catalog__filters">
            <nav class="gs-my-catalog__chips" aria-label="{{ $t('types_label') }}">
                @foreach ($chips as $chip)
                    <a href="{{ route('gov.catalog.my_catalog.index', $listQuery + array_filter(['type' => $chip['key']])) }}"
                        @class(['gs-my-catalog__chip', 'is-active' => $filters['type'] === $chip['key']])
                        @if ($filters['type'] === $chip['key']) aria-current="true" @endif>
                        {{ $chip['label'] }} <span>{{ number_format($chip['count']) }}</span>
                    </a>
                @endforeach
            </nav>
            @if ($hasGroups)
                <button type="button" class="gs-btn gs-btn--ghost gs-btn--sm" data-catalog-expand-all aria-pressed="{{ $openByDefault ? 'true' : 'false' }}">
                    {{ $openByDefault ? $t('collapse_all') : $t('expand_all') }}
                </button>
            @endif
        </div>

        @if ($activeTab === 'cleanup' && $metrics['needs_cleanup'] > 0 && $canAct)
            <x-gs::alert tone="info" class="gs-my-catalog__help">{{ $t('review_help') }}</x-gs::alert>
        @endif

        @if ($canSelect)
            <div class="gs-my-catalog__bulk" data-catalog-bulk role="region" aria-live="polite" aria-label="{{ $t('bulk_label') }}" hidden>
                <span><strong data-catalog-bulk-count>0</strong> {{ $t('selected') }}</span>
                <span class="gs-my-catalog__bulk-gap"></span>
                <button type="button" class="gs-btn gs-btn--ghost gs-btn--sm" data-catalog-clear>{{ $t('clear_selection') }}</button>
                <button type="button" class="gs-btn gs-btn--light gs-btn--sm" data-catalog-archive-selected><i class="fas fa-box-archive" aria-hidden="true"></i> {{ $t('archive_selected') }}</button>
            </div>
        @endif

        <x-gs::table class="gs-my-catalog__table" :caption="$t('table_caption')">
            <thead>
                <tr>
                    @if ($canSelect)
                        <th scope="col" class="gs-my-catalog__select"><input type="checkbox" data-catalog-select-all aria-label="{{ $t('select_all') }}"></th>
                    @endif
                    <th scope="col">{{ $t('col_category') }}</th>
                    <th scope="col">{{ $t('col_type') }}</th>
                    <th scope="col">{{ $t('col_source') }}</th>
                    <th scope="col">{{ $t('col_items') }}</th>
                    <th scope="col">{{ $t('col_status') }}</th>
                    @if ($canAct)<th scope="col"><span class="gs-sr-only">{{ $t('col_actions') }}</span></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($families as $i => $family)
                    @php
                        $rows = $family['rows'];
                        $single = count($rows) === 1;
                        $first = $rows[0];
                        $key = 'f'.$i;
                        $ids = collect($rows)->pluck('id')->implode(',');
                        [$sourceIcon, $sourceLabel] = $source($first);
                        if ($single) {
                            [$status, $statusLabel] = $rowStatus($first);
                        } elseif ($activeTab === 'archived') {
                            [$status, $statusLabel] = ['archived', $t('status_archived')];
                        } elseif ($family['unused'] === 0) {
                            [$status, $statusLabel] = ['success', $t('status_all_in_use')];
                        } elseif ($family['unused'] === count($rows)) {
                            [$status, $statusLabel] = ['warning', $t('status_none_in_use')];
                        } else {
                            [$status, $statusLabel] = ['warning', $t('status_some_unused', ['unused' => $family['unused'], 'total' => count($rows)])];
                        }
                    @endphp
                    <tr class="gs-my-catalog__family" data-family="{{ $key }}" @if ($single) data-row-id="{{ $first->id }}" @endif>
                        @if ($canSelect)
                            <td class="gs-my-catalog__select">
                                <input type="checkbox" data-catalog-select="{{ $ids }}" data-family-of="{{ $key }}" aria-label="{{ $t('select_row', ['name' => $family['en']]) }}">
                            </td>
                        @endif
                        <td>
                            <div class="gs-my-catalog__cat">
                                @if ($single)
                                    <span class="gs-my-catalog__chev-space"></span>
                                @else
                                    <button type="button" class="gs-my-catalog__chev" data-catalog-toggle="{{ $key }}" aria-expanded="{{ $openByDefault ? 'true' : 'false' }}"
                                        aria-label="{{ $t('show_codes', ['name' => $family['en']]) }}"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                                @endif
                                <span class="gs-my-catalog__tile gs-my-catalog__tile--{{ $family['type'] }}" aria-hidden="true"><i class="fas {{ $typeIcons[$family['type']] ?? 'fa-tag' }}"></i></span>
                                <span class="gs-my-catalog__names">
                                    <span class="gs-my-catalog__en">
                                        {{ $family['en'] }}
                                        @if ($single)
                                            @if ($first->catalog_label['code'])<code class="gs-my-catalog__code">{{ $first->catalog_label['code'] }}</code>@endif
                                        @else
                                            <span class="gs-my-catalog__count">{{ $t('codes_count', ['count' => count($rows)]) }}</span>
                                            <span class="gs-sr-only">{{ $t('codes_list', ['codes' => implode(', ', $family['codes'])]) }}</span>
                                        @endif
                                    </span>
                                    @if ($family['bn'])<span class="gs-my-catalog__bn" lang="bn">{{ $family['bn'] }}</span>@endif
                                </span>
                            </div>
                        </td>
                        <td>{{ $t('type_'.$family['type']) }}</td>
                        <td><span class="gs-my-catalog__source"><i class="fas {{ $sourceIcon }}" aria-hidden="true"></i> {{ $sourceLabel }}</span></td>
                        <td>
                            <span class="gs-my-catalog__use">
                                <strong @class(['is-zero' => $family['items'] === 0])>{{ number_format($family['items']) }}</strong>
                                <span class="gs-my-catalog__meter" aria-hidden="true"><span style="width: {{ min(100, round($family['items'] / $maxItems * 100)) }}%"></span></span>
                            </span>
                        </td>
                        <td><x-gs::status-badge :status="$status" :label="$statusLabel" /></td>
                        @if ($canAct)
                            <td class="gs-my-catalog__actions">
                                @if ($single && $activeTab === 'active')
                                    <a href="{{ route('gov.catalog.my_catalog.show', $first->id) }}" class="gs-btn gs-btn--ghost gs-btn--sm">{{ $t('manage') }}</a>
                                @elseif ($activeTab === 'cleanup')
                                    <button type="button" class="gs-btn gs-btn--light gs-btn--sm btn-archive" data-catalog-action="archive" data-ids="{{ $ids }}" data-family-of="{{ $key }}">
                                        {{ $single ? $t('archive') : $t('archive_all', ['count' => count($rows)]) }}
                                    </button>
                                @elseif ($activeTab === 'archived')
                                    <button type="button" class="gs-btn gs-btn--light gs-btn--sm btn-restore" data-catalog-action="restore" data-ids="{{ $ids }}" data-family-of="{{ $key }}">
                                        {{ $single ? $t('restore') : $t('restore_all', ['count' => count($rows)]) }}
                                    </button>
                                @endif
                            </td>
                        @endif
                    </tr>
                    @unless ($single)
                        @foreach ($rows as $row)
                            @php
                                [$status, $statusLabel] = $rowStatus($row);
                            @endphp
                            <tr class="gs-my-catalog__child" data-family-child="{{ $key }}" data-row-id="{{ $row->id }}" @unless ($openByDefault) hidden @endunless>
                                @if ($canSelect)
                                    <td class="gs-my-catalog__select">
                                        <input type="checkbox" data-catalog-select="{{ $row->id }}" data-child-of="{{ $key }}" aria-label="{{ $t('select_row', ['name' => $family['en'].' '.$row->catalog_label['code']]) }}">
                                    </td>
                                @endif
                                <td><span class="gs-my-catalog__branch"><code class="gs-my-catalog__code">{{ $row->catalog_label['code'] ?? $row->name }}</code></span></td>
                                <td></td>
                                <td></td>
                                <td>
                                    <span class="gs-my-catalog__use">
                                        <strong @class(['is-zero' => (int) $row->total_usage_count === 0])>{{ number_format($row->total_usage_count) }}</strong>
                                        <span class="gs-my-catalog__meter" aria-hidden="true"><span style="width: {{ min(100, round($row->total_usage_count / $maxItems * 100)) }}%"></span></span>
                                    </span>
                                </td>
                                <td><x-gs::status-badge :status="$status" :label="$statusLabel" /></td>
                                @if ($canAct)
                                    <td class="gs-my-catalog__actions">
                                        @if ($activeTab === 'active')
                                            <a href="{{ route('gov.catalog.my_catalog.show', $row->id) }}" class="gs-btn gs-btn--ghost gs-btn--sm">{{ $t('manage') }}</a>
                                        @elseif ($activeTab === 'cleanup')
                                            <button type="button" class="gs-btn gs-btn--light gs-btn--sm btn-archive" data-catalog-action="archive" data-ids="{{ $row->id }}">{{ $t('archive') }}</button>
                                        @else
                                            <button type="button" class="gs-btn gs-btn--light gs-btn--sm btn-restore" data-catalog-action="restore" data-ids="{{ $row->id }}">{{ $t('restore') }}</button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @endunless
                @empty
                    <tr>
                        <td colspan="{{ $colspan }}">
                            <x-gs::empty-state :icon="$activeTab === 'archived' ? 'fa-box-archive' : ($q !== '' ? 'fa-magnifying-glass' : 'fa-folder-open')" :title="$emptyTitle">
                                {{ $emptyBody }}
                                @if ($q !== '' || $filters['type'])
                                    <x-slot:actions>
                                        <x-gs::button tone="light" size="sm" :href="route('gov.catalog.my_catalog.index', ['tab' => $activeTab])">{{ $t('clear_filters') }}</x-gs::button>
                                    </x-slot:actions>
                                @endif
                            </x-gs::empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-gs::table>

        <div class="gs-my-catalog__footer">
            <span>{{ $t('footer', ['categories' => number_format($categories->total()), 'families' => number_format(count($families))]) }}</span>
            {{ $categories->appends($listQuery + array_filter(['type' => $filters['type']]))->links() }}
        </div>
    </div>

    <div class="gs-my-catalog__toast" data-catalog-toast role="status" aria-live="polite" hidden>
        <i class="fas fa-circle-check" aria-hidden="true"></i>
        <span data-catalog-toast-text></span>
        <button type="button" data-catalog-undo>{{ $t('undo') }}</button>
        <button type="button" data-catalog-toast-close aria-label="{{ $t('dismiss') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </div>
</div>
@endsection

@section('moar_scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('gs-my-catalog');
    if (!root) return;
    var text = @json($jsText);
    var token = '{{ csrf_token() }}';
    var lastAction = null;
    var toastTimer = null;

    function all(selector, scope) { return Array.prototype.slice.call((scope || root).querySelectorAll(selector)); }
    function ids(value) { return String(value).split(',').filter(Boolean); }
    function fill(template, count) { return template.replace(':count', count); }

    // Families: show or hide the codes that share one title.
    function setOpen(key, open) {
        var toggle = root.querySelector('[data-catalog-toggle="' + key + '"]');
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        all('[data-family-child="' + key + '"]').forEach(function (row) { row.hidden = !open; });
    }
    all('[data-catalog-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            setOpen(toggle.dataset.catalogToggle, toggle.getAttribute('aria-expanded') !== 'true');
        });
    });
    var expandAll = root.querySelector('[data-catalog-expand-all]');
    if (expandAll) {
        expandAll.addEventListener('click', function () {
            var open = expandAll.getAttribute('aria-pressed') !== 'true';
            all('[data-catalog-toggle]').forEach(function (toggle) { setOpen(toggle.dataset.catalogToggle, open); });
            expandAll.setAttribute('aria-pressed', open ? 'true' : 'false');
            expandAll.textContent = open ? text.collapseAll : text.expandAll;
        });
    }

    // Selection (Needs review tab).
    var bulk = root.querySelector('[data-catalog-bulk]');
    function selectedIds() {
        var picked = {};
        all('[data-catalog-select]:checked').forEach(function (box) { ids(box.dataset.catalogSelect).forEach(function (id) { picked[id] = true; }); });
        return Object.keys(picked);
    }
    function syncSelection() {
        all('[data-family-of][data-catalog-select]').forEach(function (box) {
            var kids = all('[data-child-of="' + box.dataset.familyOf + '"]');
            if (!kids.length) return;
            var on = kids.filter(function (k) { return k.checked; }).length;
            box.checked = on === kids.length;
            box.indeterminate = on > 0 && on < kids.length;
        });
        all('tbody tr').forEach(function (row) {
            var box = row.querySelector('[data-catalog-select]');
            row.setAttribute('aria-selected', box && box.checked ? 'true' : 'false');
        });
        var count = selectedIds().length;
        var boxes = all('tbody [data-catalog-select]');
        var selectAll = root.querySelector('[data-catalog-select-all]');
        if (selectAll) selectAll.checked = boxes.length > 0 && boxes.every(function (b) { return b.checked; });
        if (bulk) {
            bulk.hidden = count === 0;
            bulk.querySelector('[data-catalog-bulk-count]').textContent = count;
        }
    }
    root.addEventListener('change', function (event) {
        var box = event.target;
        if (box.matches('[data-catalog-select-all]')) {
            all('tbody [data-catalog-select]').forEach(function (b) { b.checked = box.checked; });
        } else if (box.matches('[data-family-of][data-catalog-select]')) {
            all('[data-child-of="' + box.dataset.familyOf + '"]').forEach(function (k) { k.checked = box.checked; });
        }
        if (box.matches('[data-catalog-select], [data-catalog-select-all]')) syncSelection();
    });
    var clear = root.querySelector('[data-catalog-clear]');
    if (clear) clear.addEventListener('click', function () {
        all('[data-catalog-select], [data-catalog-select-all]').forEach(function (b) { b.checked = false; b.indeterminate = false; });
        syncSelection();
    });

    // Archive / restore with undo instead of a confirm dialog.
    function post(kind, list) {
        var url = kind === 'archive' ? root.dataset.archiveUrl : root.dataset.restoreUrl;
        return Promise.all(list.map(function (id) {
            return jQuery.post(url, { _token: token, category_id: id }).then(function (r) { return r; });
        }));
    }
    function removeRows(list) {
        list.forEach(function (id) {
            all('tr[data-row-id="' + id + '"]').forEach(function (row) { row.remove(); });
        });
        all('tr[data-family]').forEach(function (head) {
            var key = head.dataset.family;
            if (head.dataset.rowId) return;
            var kids = all('[data-family-child="' + key + '"]');
            if (!kids.length) { head.remove(); return; }
            var button = head.querySelector('[data-catalog-action]');
            if (button) {
                button.dataset.ids = kids.map(function (k) { return k.dataset.rowId; }).join(',');
                button.textContent = fill(button.dataset.catalogAction === 'archive' ? text.archiveAll : text.restoreAll, kids.length);
            }
            var box = head.querySelector('[data-catalog-select]');
            if (box) box.dataset.catalogSelect = kids.map(function (k) { return k.dataset.rowId; }).join(',');
        });
        syncSelection();
    }
    var toast = root.querySelector('[data-catalog-toast]');
    function showToast(message) {
        toast.querySelector('[data-catalog-toast-text]').textContent = message;
        toast.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.hidden = true; }, 8000);
    }
    function run(kind, list, buttons) {
        if (!list.length) return;
        buttons.forEach(function (b) { b.disabled = true; });
        post(kind, list).then(function () {
            lastAction = { kind: kind, ids: list };
            removeRows(list);
            var many = list.length > 1;
            showToast(kind === 'archive'
                ? (many ? fill(text.archivedMany, list.length) : text.archivedOne)
                : (many ? fill(text.restoredMany, list.length) : text.restoredOne));
            if (!root.querySelector('tbody tr[data-row-id], tbody tr[data-family]')) setTimeout(function () { window.location.reload(); }, 1500);
        }, function (xhr) {
            buttons.forEach(function (b) { b.disabled = false; });
            alert((xhr && xhr.responseJSON && xhr.responseJSON.message) || text.failed);
        });
    }
    root.addEventListener('click', function (event) {
        var button = event.target.closest('[data-catalog-action]');
        if (button) run(button.dataset.catalogAction, ids(button.dataset.ids), [button]);
    });
    var archiveSelected = root.querySelector('[data-catalog-archive-selected]');
    if (archiveSelected) archiveSelected.addEventListener('click', function () { run('archive', selectedIds(), [archiveSelected]); });

    toast.querySelector('[data-catalog-undo]').addEventListener('click', function () {
        if (!lastAction) return;
        var undo = this;
        undo.disabled = true;
        post(lastAction.kind === 'archive' ? 'restore' : 'archive', lastAction.ids).then(function () { window.location.reload(); }, function () {
            undo.disabled = false;
            alert(text.failed);
        });
    });
    toast.querySelector('[data-catalog-toast-close]').addEventListener('click', function () { toast.hidden = true; });
});
</script>
@endsection
