@extends('layouts/default')

@section('title')
    {{ __('asset-tagging::tagging.nav.lookup') }}
    @parent
@stop

@section('content')
@php
    $segments = null;
    $facts = [];
    if ($result) {
        $decoded = $result['decoded'];
        $asset = $result['asset'];
        $snapshot = $result['snapshot'] ?? [];
        if ($decoded['kind'] === 'govt') {
            $segments = app(\GovStore\AssetTagging\Formats\FinanceDivisionV1Format::class)->segments([
                'gov_code' => $decoded['gov_code'], 'location_code' => $decoded['location_code'], 'division_code' => $decoded['division_code'],
                'acquired_on' => '20'.$decoded['year'].'-'.$decoded['month'].'-01'], $decoded['sequence']);
        }
        $facts = [
            ['label' => __('asset-tagging::tagging.lookup.asset'), 'value' => $asset->name ?: ($asset->model?->name ?? '—')],
            ['label' => __('asset-tagging::tagging.lookup.model'), 'value' => $asset->model?->name ?? '—'],
            ['label' => __('asset-tagging::tagging.lookup.serial'), 'value' => $asset->serial ?: '—'],
            ['label' => __('asset-tagging::tagging.lookup.location'), 'value' => $asset->location?->name ?? '—'],
            ['label' => __('asset-tagging::tagging.lookup.status'), 'value' => $asset->status?->name ?? '—'],
            ['label' => __('asset-tagging::tagging.lookup.purchase_date'), 'value' => $asset->purchase_date ? \Illuminate\Support\Carbon::parse($asset->purchase_date)->format('Y-m-d') : '—'],
        ];
        $registration = [];
        if ($result['registration']) {
            $registration = [
                ['label' => __('asset-tagging::tagging.lookup.document'), 'value' => $result['registration']->document_number ?? '—'],
                ['label' => __('asset-tagging::tagging.lookup.registered'), 'value' => (string) $result['registration']->created_at],
                ['label' => __('asset-tagging::tagging.lookup.finance_source'), 'value' => __('asset-tagging::tagging.lookup.source_'.($snapshot['finance_source'] ?? 'none'))],
                ['label' => __('asset-tagging::tagging.lookup.date_source'), 'value' => isset($snapshot['date_source']) ? __('asset-tagging::tagging.lookup.date_'.$snapshot['date_source']).' '.($snapshot['date_reference'] ?? '') : '—'],
            ];
        }
    }
@endphp
<div class="at-page">
    <x-gs::page-header :title="__('asset-tagging::tagging.nav.lookup')" :subtitle="__('asset-tagging::tagging.lookup.intro')" />

    <form method="GET" action="{{ route('asset-tags.lookup') }}" class="at-row" role="search">
        <label class="gs-sr-only" for="lookup-tag">{{ __('asset-tagging::tagging.lookup.tag') }}</label>
        <input id="lookup-tag" type="search" name="tag" value="{{ $tag }}" class="form-control at-lookup-input" maxlength="255" autocomplete="off"
            placeholder="{{ __('asset-tagging::tagging.lookup.placeholder') }}">
        <x-gs::button type="submit" tone="primary" icon="fa-magnifying-glass">{{ __('asset-tagging::tagging.lookup.search') }}</x-gs::button>
    </form>

    @if ($searched && ! $result)
        <x-gs::empty-state icon="fa-tag" :title="__('asset-tagging::tagging.lookup.none')" />
    @elseif ($result)
        <x-gs::box :title="$tag" icon="fas fa-tag">
            <p>
                @if ($result['decoded']['kind'] === 'govt')
                    <x-gs::badge tone="primary">{{ __('asset-tagging::tagging.kind.govt') }}</x-gs::badge>
                @elseif ($result['decoded']['kind'] === 'standard')
                    <x-gs::badge tone="secondary">{{ __('asset-tagging::tagging.kind.standard') }}</x-gs::badge>
                @else
                    <x-gs::badge tone="secondary" outline>{{ __('asset-tagging::tagging.lookup.other_tag') }}</x-gs::badge>
                @endif
            </p>
            @if ($segments)
                <x-gs::code-segments :segments="$segments" :label="$tag" />
                @if ($result['decoded']['finance_item'])
                    <p class="at-small">{{ $result['decoded']['finance_item']->label() }} · <span class="gs-muted">{{ $result['decoded']['finance_item']->path() }}</span></p>
                @endif
            @endif
        </x-gs::box>
        <div class="at-cols">
            <x-gs::box :title="__('asset-tagging::tagging.lookup.asset_facts')" icon="fas fa-laptop">
                <x-gs::key-value :items="$facts" />
                <a href="{{ route('hardware.show', $result['asset']->id) }}">{{ __('asset-tagging::tagging.lookup.open_asset') }}</a>
            </x-gs::box>
            @if ($registration)
                <x-gs::box :title="__('asset-tagging::tagging.lookup.registration')" icon="fas fa-receipt">
                    <x-gs::key-value :items="$registration" />
                </x-gs::box>
            @endif
        </div>
    @endif
</div>
@endsection
