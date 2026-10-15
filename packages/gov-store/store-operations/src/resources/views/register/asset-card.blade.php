@extends('layouts/default')

@section('title', __('storeops::storeops.asset_register.card_title', ['name' => $item->name]))

@section('content')
@php
    $a = 'storeops::storeops.asset_register.';
    $c = 'storeops::storeops.stock_card.';
    $na = '—';
    $group = fn (array $unit) => $unit['ready'] ? 'store' : ($unit['status_key'] === 'deployed' ? 'issued' : 'other');
    $unitCounts = ['all' => $units->count()] + collect(['store', 'issued', 'other'])
        ->mapWithKeys(fn ($key) => [$key => $units->filter(fn ($u) => $group($u) === $key)->count()])->all();
    $needle = mb_strtolower($unitFilters['q']);
    $shownUnits = $units->filter(fn ($u) => ($unitFilters['status'] === 'all' || $group($u) === $unitFilters['status'])
        && ($needle === '' || str_contains(mb_strtolower(implode(' ', [$u['tag'], $u['serial'], $u['holder'], $u['document_number']])), $needle)))->values();
    $ready = $units->where('ready', true)->sortBy([['received_at', 'asc'], ['tag', 'asc']])->values();
    $keep = fn (array $changes) => request()->url().'?'.http_build_query(array_filter(array_merge(request()->except('page', 'entry'), $changes), fn ($v) => $v !== null && $v !== ''));
@endphp
<div class="storeops-theme storeops-card storeops-asset-card">
    @include('storeops::register.partials.context-bar')
    @include('storeops::register.partials.item-header', ['kind' => 'asset'])

    @if (! $card)
        <x-gs::empty-state icon="fa-building" :title="__($a.'no_office')" />
    @else
        @include('storeops::register.partials.card-summary', [
            'closingLabel' => __($a.'kpi_in_store'),
            'closingCaption' => trans_choice($a.'ready_count', $ready->count(), ['count' => $ready->count()]),
        ])

        <div class="storeops-split">
            <div class="storeops-split__main">
                <x-gs::box :title="__($a.'units_title')" icon="fas fa-barcode" id="units">
                    <p class="gs-muted">{{ __($a.'units_subtitle', ['name' => $item->name]) }}</p>
                    <div class="storeops-toolbar storeops-noprint">
                        <nav class="storeops-seg" aria-label="{{ __($a.'status') }}">
                            @foreach ($unitCounts as $key => $count)
                                <a href="{{ $keep(['unit_status' => $key === 'all' ? null : $key]) }}#units" @if ($unitFilters['status'] === $key) aria-current="true" @endif>
                                    {{ __($a.'unit_groups.'.$key) }} <span class="storeops-seg__n">{{ $count }}</span></a>
                            @endforeach
                        </nav>
                        <form method="get" action="{{ request()->url() }}#units" class="storeops-inline-search" role="search">
                            @foreach (request()->except('unit_q', 'page', 'entry') as $name => $value)
                                @if (is_string($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                            @endforeach
                            <label for="unit-q" class="storeops-label">{{ __($a.'find_unit') }}</label>
                            <input id="unit-q" type="search" name="unit_q" value="{{ $unitFilters['q'] }}" class="form-control storeops-control"
                                placeholder="{{ __($a.'find_unit_hint') }}">
                            <button type="submit" class="btn btn-default storeops-control">{{ __($c.'apply') }}</button>
                        </form>
                    </div>
                    <p class="gs-muted storeops-count">{{ __($a.'showing_units', ['shown' => $shownUnits->count(), 'total' => $units->count()]) }}</p>
                    @if ($units->isEmpty())
                        <x-gs::empty-state icon="fa-barcode" :title="__($a.'no_units')" />
                    @elseif ($shownUnits->isEmpty())
                        <x-gs::empty-state icon="fa-filter" :title="__($a.'no_units_match')">
                            <a href="{{ request()->url() }}#units">{{ __($a.'clear_filters') }}</a>
                        </x-gs::empty-state>
                    @else
                        <x-gs::table>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __($a.'asset_tag') }}</th>
                                    <th scope="col">{{ __($a.'serial') }}</th>
                                    <th scope="col">{{ __($a.'received') }}</th>
                                    <th scope="col">{{ __($a.'status') }}</th>
                                    <th scope="col">{{ __($a.'holder') }}</th>
                                    <th scope="col">{{ __($a.'warranty') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($shownUnits as $unit)
                                    <tr>
                                        <td class="storeops-mono">
                                            @if ($unit['asset_id'] && ! $unit['removed'])
                                                <a href="{{ route('hardware.show', $unit['asset_id']) }}"><strong>{{ $unit['tag'] }}</strong></a>
                                            @else
                                                <strong>{{ $unit['tag'] }}</strong>
                                            @endif
                                        </td>
                                        <td class="storeops-mono">{{ $unit['serial'] ?: $na }}</td>
                                        <td>
                                            {{ $unit['received_at'] ? \Carbon\Carbon::parse($unit['received_at'])->translatedFormat('d M Y') : $na }}<br>
                                            <a href="{{ route('storeops.documents.workspace', ['type' => $unit['document_type'], 'id' => $unit['document_id']]) }}" class="storeops-mono">{{ $unit['document_number'] }}</a>
                                        </td>
                                        <td>
                                            <x-gs::status-badge :status="$unit['status_key']"
                                                :label="$unit['removed'] ? __($a.'unit_removed') : ($unit['ready'] ? __($a.'in_store') : $unit['status_label'])" />
                                        </td>
                                        <td>{{ $unit['holder'] ?? ($unit['removed'] ? $na : __($a.'in_store')) }}</td>
                                        <td>
                                            @if ($unit['warranty_until'])
                                                {{ \Carbon\Carbon::parse($unit['warranty_until'])->translatedFormat('M Y') }}
                                            @elseif ($unit['warranty_months'])
                                                {{ __($a.'warranty_months', ['months' => $unit['warranty_months']]) }}
                                            @else
                                                {{ $na }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-gs::table>
                    @endif
                </x-gs::box>
            </div>

            <aside class="storeops-split__side">
                <x-gs::box :title="__($a.'ready_title')" icon="fas fa-hand-holding">
                    <p><strong>{{ trans_choice($a.'ready_count', $ready->count(), ['count' => $ready->count()]) }}</strong></p>
                    @if ($ready->isEmpty())
                        <p class="gs-muted">{{ __($a.'ready_none') }}</p>
                    @else
                        <p class="gs-muted">{{ __($a.'ready_hint') }}</p>
                        <ol class="storeops-ready">
                            @foreach ($ready as $unit)
                                <li @class(['storeops-ready__next' => $loop->first])>
                                    <span class="storeops-mono"><strong>{{ $unit['tag'] }}</strong></span>
                                    <span class="gs-muted">{{ __($a.'ready_since', ['date' => \Carbon\Carbon::parse($unit['received_at'])->translatedFormat('d M Y')]) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-gs::box>
                @include('storeops::register.partials.chain-check')
            </aside>
        </div>

        <div class="storeops-split" id="ledger">
            <div class="storeops-split__main">
                <x-gs::box :title="__($c.'ledger_title')" icon="fas fa-book">
                    <p class="gs-muted">{{ __($c.'ledger_subtitle') }}</p>
                    @include('storeops::register.partials.period-filter')
                    @include('storeops::register.partials.ledger-table', ['showTags' => true])
                </x-gs::box>
            </div>
            <aside class="storeops-split__side">
                @include('storeops::register.partials.entry-details', ['unit' => $facts['unit']])
            </aside>
        </div>
    @endif
</div>
@endsection
