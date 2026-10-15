@extends('layouts/default')

@section('title', __('storeops::storeops.stock_register.title'))

@section('content')
@php
    $r = 'storeops::storeops.stock_register.';
    $na = '—';
    $link = fn (array $changes) => route('storeops.register.index', \GovStore\StoreOperations\Services\StockRegisterQuery::query($filters, $changes));
    $statusTones = ['out' => 'danger', 'low' => 'warning', 'ok' => 'success', 'none' => 'archived'];
    $levelTones = ['out' => 'danger', 'low' => 'warning', 'ok' => 'primary', 'none' => 'secondary'];
    $kinds = 'storeops::storeops.stock_card.kinds.';
    $kpis = [
        ['label' => __($r.'kpi_items'), 'value' => $counts['all'], 'icon' => 'fa-boxes-stacked', 'tone' => 'primary',
            'caption' => trans_choice($r.'kpi_items_caption', $counts['type_count'], ['count' => $counts['type_count']]),
            'href' => $link(['status' => 'all', 'type' => 'all']), 'active' => $filters['status'] === 'all' && $filters['type'] === 'all'],
        ['label' => __($r.'kpi_low'), 'value' => $counts['low'], 'icon' => 'fa-arrow-trend-down', 'tone' => 'warning',
            'caption' => __($r.'kpi_low_caption'), 'href' => $link(['status' => $filters['status'] === 'low' ? 'all' : 'low']), 'active' => $filters['status'] === 'low'],
        ['label' => __($r.'kpi_out'), 'value' => $counts['out'], 'icon' => 'fa-ban', 'tone' => 'danger',
            'caption' => __($r.'kpi_out_caption'), 'href' => $link(['status' => $filters['status'] === 'out' ? 'all' : 'out']), 'active' => $filters['status'] === 'out'],
        ['label' => __($r.'kpi_assets'), 'value' => $counts['asset_units'], 'icon' => 'fa-barcode', 'tone' => 'info',
            'caption' => trans_choice($r.'kpi_assets_caption', $counts['asset_models'], ['count' => $counts['asset_models']]),
            'href' => $link(['type' => $filters['type'] === 'assetmodel' ? 'all' : 'assetmodel', 'status' => 'all']), 'active' => $filters['type'] === 'assetmodel'],
    ];
@endphp
<div class="storeops-theme storeops-register">
    @include('storeops::register.partials.context-bar')

    <x-gs::page-header :title="__($r.'title')" bn="স্টক রেজিস্টার" :subtitle="__($r.'subtitle')">
        <x-slot:actions>
            @if ($abilities['draft'] && $office['id'])
                <a class="btn btn-primary storeops-noprint" href="{{ route('storeops.hub') }}"><i class="fas fa-plus" aria-hidden="true"></i> {{ __($r.'new_document') }}</a>
            @endif
            @if ($office['id'])
                <a class="btn btn-default storeops-noprint" href="{{ route('storeops.register.export', \GovStore\StoreOperations\Services\StockRegisterQuery::query($filters)) }}">
                    <i class="fas fa-file-csv" aria-hidden="true"></i> {{ __($r.'export') }}</a>
                <button type="button" class="btn btn-default storeops-noprint" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> {{ __($r.'print') }}</button>
            @endif
        </x-slot:actions>
    </x-gs::page-header>

    @if (! $office['id'])
        <x-gs::empty-state icon="fa-building" :title="__($r.'no_office')" />
    @else
        @unless ($opened)
            <x-gs::alert tone="warning" :title="__($r.'not_opened_title')">{{ __($r.'not_opened_text') }}</x-gs::alert>
        @endunless

        <div class="gs-kpis storeops-noprint">
            @foreach ($kpis as $kpi)
                <x-gs::kpi :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" :tone="$kpi['tone']" :caption="$kpi['caption']"
                    :href="$kpi['href']" :class="$kpi['active'] ? 'storeops-kpi--active' : ''" :aria-current="$kpi['active'] ? 'true' : 'false'" />
            @endforeach
        </div>

        <x-gs::box :title="__($r.'items_title')" icon="fas fa-warehouse">
            <form method="get" action="{{ route('storeops.register.index') }}" class="storeops-toolbar storeops-noprint" role="search">
                <div class="storeops-field storeops-field--grow">
                    <label for="reg-q" class="storeops-label">{{ __($r.'search') }}</label>
                    <input id="reg-q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control storeops-control" placeholder="{{ __($r.'search_hint') }}">
                </div>
                <div class="storeops-field">
                    <label for="reg-sort" class="storeops-label">{{ __($r.'sort_by') }}</label>
                    <select id="reg-sort" name="sort" class="form-control storeops-control">
                        @foreach (\GovStore\StoreOperations\Services\StockRegisterQuery::SORTS as $sort)
                            <option value="{{ $sort }}" @selected($filters['sort'] === $sort)>{{ __($r.'sorts.'.$sort) }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($filters['type'] !== 'all')<input type="hidden" name="type" value="{{ $filters['type'] }}">@endif
                @if ($filters['status'] !== 'all')<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                <button type="submit" class="btn btn-primary storeops-control">{{ __('storeops::storeops.stock_card.apply') }}</button>
            </form>

            <div class="storeops-filters storeops-noprint">
                <nav class="storeops-seg" aria-label="{{ __($r.'item_type') }}">
                    <a href="{{ $link(['type' => 'all']) }}" @if ($filters['type'] === 'all') aria-current="true" @endif>
                        {{ __($r.'types_plural.all') }} <span class="storeops-seg__n">{{ $counts['all'] }}</span></a>
                    @foreach ($counts['types'] as $type => $count)
                        <a href="{{ $link(['type' => $type]) }}" @if ($filters['type'] === $type) aria-current="true" @endif>
                            {{ __($r.'types_plural.'.$type) }} <span class="storeops-seg__n">{{ $count }}</span></a>
                    @endforeach
                </nav>
                <nav class="storeops-chips" aria-label="{{ __($r.'stock_status') }}">
                    <span class="gs-muted">{{ __($r.'stock_status') }}:</span>
                    @foreach (\GovStore\StoreOperations\Services\StockRegisterQuery::STATUSES as $status)
                        <a href="{{ $link(['status' => $status]) }}" class="storeops-chip-link" @if ($filters['status'] === $status) aria-current="true" @endif>
                            {{ __($r.'status_filters.'.$status) }} <span class="storeops-seg__n">{{ $counts[$status] }}</span></a>
                    @endforeach
                </nav>
            </div>

            <p class="gs-muted storeops-count">{{ __($r.'showing', ['shown' => $items->total(), 'total' => $counts['all']]) }}</p>

            @if ($items->isEmpty())
                <x-gs::empty-state icon="fa-magnifying-glass" :title="__($r.'no_match')">
                    {{ __($r.'no_match_hint') }}
                    <x-slot:actions><a class="btn btn-default" href="{{ route('storeops.register.index') }}">{{ __($r.'clear_filters') }}</a></x-slot:actions>
                </x-gs::empty-state>
            @else
                <x-gs::table class="storeops-register-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __($r.'col_item') }}</th>
                            <th scope="col">{{ __($r.'col_type') }}</th>
                            <th scope="col" class="gs-num">{{ __($r.'col_balance') }}</th>
                            <th scope="col">{{ __($r.'col_level') }}</th>
                            <th scope="col">{{ __($r.'col_status') }}</th>
                            <th scope="col">{{ __($r.'col_last') }}</th>
                            <th scope="col" class="storeops-noprint"><span class="gs-sr-only">{{ __($r.'col_actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $row)
                            @php
                                $cardUrl = route('storeops.register.kardex', ['type' => $row['type'], 'id' => $row['id']]);
                                $unit = $row['type'] === 'assetmodel' ? __('storeops::storeops.asset_register.units_word') : '';
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ $cardUrl }}"><strong>{{ $row['name'] }}</strong></a><br>
                                    <span class="gs-muted">{{ collect([$row['code'], $row['category']])->filter()->implode(' · ') ?: $na }}</span>
                                </td>
                                <td>{{ __($r.'types.'.$row['type']) }}</td>
                                <td class="gs-num"><strong>{{ $row['balance'] ?? $na }}</strong> @if ($row['balance'] !== null && $unit)<span class="gs-muted">{{ $unit }}</span>@endif</td>
                                <td class="storeops-level">
                                    <span class="storeops-meter" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $row['level'] }}"
                                        aria-label="{{ __($r.'level_label', ['balance' => $row['balance'] ?? 0, 'level' => $row['reorder'] ?? $na]) }}">
                                        <span class="storeops-meter__bar storeops-meter__bar--{{ $levelTones[$row['status']] }}" style="width: {{ $row['level'] }}%"></span>
                                    </span>
                                    <span class="gs-muted storeops-meter__caption">{{ $row['reorder'] !== null ? __($r.'reorder_at', ['level' => $row['reorder']]) : __($r.'no_reorder') }}</span>
                                </td>
                                <td><x-gs::status-badge :status="$statusTones[$row['status']]" :label="__($r.'statuses.'.$row['status'])" /></td>
                                <td>
                                    @if ($row['last_at'])
                                        {{ \Carbon\Carbon::parse($row['last_at'])->translatedFormat('d M Y') }}<br>
                                        <span class="gs-muted">{{ collect([$row['last_document'], $row['last_kind'] ? __($kinds.$row['last_kind']) : null])->filter()->implode(' · ') }}</span>
                                    @else
                                        {{ $na }}
                                    @endif
                                </td>
                                <td class="gs-num storeops-noprint">
                                    <a href="{{ $cardUrl }}" class="btn btn-default btn-sm">
                                        <i class="fas {{ $row['type'] === 'assetmodel' ? 'fa-id-card' : 'fa-book' }}" aria-hidden="true"></i>
                                        {{ $row['type'] === 'assetmodel' ? __($r.'asset_card') : __($r.'stock_card') }}
                                        <span class="gs-sr-only">{{ __($r.'for_item', ['name' => $row['name']]) }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-gs::table>
                {{ $items->links() }}
            @endif
        </x-gs::box>

        <details class="storeops-details storeops-explain">
            <summary>{{ __($r.'explain_title') }}</summary>
            <p>{{ __($r.'explain_text') }}</p>
        </details>
    @endif
</div>
@endsection
