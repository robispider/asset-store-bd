@extends('layouts/default')

@section('title', __('organization_labels::office.registry_title'))

@php
    $bangla = str_starts_with(strtolower(app()->getLocale()), 'bn');
    $filters = request()->except(['status', 'page']);
    $tabs = collect(['all', 'action', 'setup', 'live', 'paused', 'none'])->map(fn ($key) => [
        'key' => $key,
        'label' => __('organization_labels::office.tab_'.$key),
        'count' => $tabCounts[$key] ?? 0,
        'href' => route('gov.org.provisioning.index', $filters + ($key === 'all' ? [] : ['status' => $key])),
    ])->all();
    $hasFilters = request()->filled('search') || request()->filled('ministry_id') || request()->filled('district_id') || $activeStatus !== 'all';
@endphp

@section('content')
<div class="govorg">
    <x-gs::page-header :title="__('organization_labels::office.registry_title')" :bn="$bangla ? null : __('organization_labels::office.registry_title', [], 'bn-BD')"
        :subtitle="__('organization_labels::office.registry_subtitle')">
        <x-slot:actions>
            <x-gs::button :href="route('gov.org.provisioning.create')" icon="fa-plus">{{ __('organization_labels::office.add_office') }}</x-gs::button>
        </x-slot:actions>
    </x-gs::page-header>

    @include('govorg::partials.section-nav', ['active' => 'offices'])

    @if (($tabCounts['action'] ?? 0) > 0 && $activeStatus !== 'action')
        <x-gs::alert tone="warning" :title="__('organization_labels::office.attention', ['count' => $tabCounts['action']])">
            {{ __('organization_labels::office.attention_hint') }}
            <a href="{{ route('gov.org.provisioning.index', $filters + ['status' => 'action']) }}">{{ __('organization_labels::office.attention_show') }}</a>
        </x-gs::alert>
    @endif

    <x-gs::box>
        <x-gs::status-tabs :tabs="$tabs" :active="$activeStatus" />

        <form method="GET" action="{{ route('gov.org.provisioning.index') }}" role="search" class="govorg-filters" data-govorg-autosubmit>
            @if ($activeStatus !== 'all')<input type="hidden" name="status" value="{{ $activeStatus }}">@endif
            <label class="sr-only" for="search">{{ __('organization_labels::office.search_label') }}</label>
            <input type="search" class="form-control govorg-filters__search" name="search" id="search" value="{{ request('search') }}" placeholder="{{ __('organization_labels::office.search_placeholder') }}">
            <label class="sr-only" for="ministry_id">{{ __('organization_labels::office.col_ministry') }}</label>
            <select class="form-control govorg-filters__select" name="ministry_id" id="ministry_id">
                <option value="">{{ __('organization_labels::office.all_ministries') }}</option>
                @foreach ($companies ?? [] as $company)
                    <option value="{{ $company->id }}" @selected((string) request('ministry_id') === (string) $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="district_id">{{ __('organization_labels::office.col_area') }}</label>
            <select class="form-control govorg-filters__select" name="district_id" id="district_id">
                <option value="">{{ __('organization_labels::office.all_districts') }}</option>
                @foreach ($districts ?? [] as $dist)
                    <option value="{{ $dist->GeoAreaId }}" @selected((string) request('district_id') === (string) $dist->GeoAreaId)>{{ $bangla && $dist->bn_name ? $dist->bn_name : $dist->en_name }}</option>
                @endforeach
            </select>
            <x-gs::button type="submit" tone="light" outline icon="fa-search">{{ __('organization_labels::office.search') }}</x-gs::button>
            @if ($hasFilters)
                <a class="gs-btn gs-btn--light gs-btn--outline" href="{{ route('gov.org.provisioning.index') }}">{{ __('organization_labels::office.clear') }}</a>
            @endif
        </form>

        @if ($offices->total() > 0)
            <p class="govorg-muted govorg-small" aria-live="polite">{{ __('organization_labels::office.showing', ['from' => $offices->firstItem(), 'to' => $offices->lastItem(), 'total' => $offices->total()]) }}</p>
            <x-gs::table :caption="__('organization_labels::office.registry_title')">
                <thead>
                    <tr>
                        <th scope="col">{{ __('organization_labels::office.col_office') }}</th>
                        <th scope="col">{{ __('organization_labels::office.col_area') }}</th>
                        <th scope="col">{{ __('organization_labels::office.col_ministry') }}</th>
                        <th scope="col">{{ __('organization_labels::office.col_setup') }}</th>
                        <th scope="col">{{ __('organization_labels::office.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($offices as $office)
                        @php($href = $office->status === 'unconfigured' ? route('gov.org.provisioning.onboard', ['location_id' => $office->id]) : route('gov.org.hub.show', $office->id))
                        <tr data-govorg-href="{{ $href }}">
                            <td>
                                <a class="govorg-office" href="{{ $href }}">{{ $office->name }}</a>
                                @if ($office->parentName)<span class="govorg-meta">{{ __('organization_labels::office.reports_to', ['name' => $office->parentName]) }}</span>@endif
                            </td>
                            <td>
                                @if ($office->geoName)
                                    {{ $office->geoName }}
                                    @if ($office->districtName)<span class="govorg-meta">{{ $office->districtName }}</span>@endif
                                @else
                                    <span class="govorg-muted">{{ __('organization_labels::office.not_placed') }}</span>
                                @endif
                            </td>
                            <td>{!! $office->ministryName ? e($office->ministryName) : '<span class="govorg-muted">'.e(__('organization_labels::office.not_linked')).'</span>' !!}</td>
                            <td class="govorg-setup">
                                <x-gs::progress :value="$office->done" :max="\GovStore\Organization\ViewModels\OfficeRegistryViewModel::STEPS" size="sm" :show-value="false"
                                    :tone="$office->status === 'suspended' ? 'danger' : ($office->done === 4 ? 'success' : 'primary')"
                                    :label="__('organization_labels::office.setup_count', ['done' => $office->done, 'total' => \GovStore\Organization\ViewModels\OfficeRegistryViewModel::STEPS])" />
                                <span class="govorg-next govorg-next--{{ $office->nextStep }}">{{ __('organization_labels::office.'.$office->nextStep) }}</span>
                            </td>
                            <td><x-gs::status-badge :status="'office_'.$office->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-gs::table>
            {{ $offices->links() }}
        @else
            <x-gs::empty-state icon="fa-building" :title="__('organization_labels::office.empty_title')">
                {{ __('organization_labels::office.empty_body') }}
                @if ($hasFilters)
                    <x-slot:actions>
                        <x-gs::button tone="light" outline :href="route('gov.org.provisioning.index')">{{ __('organization_labels::office.empty_reset') }}</x-gs::button>
                    </x-slot:actions>
                @endif
            </x-gs::empty-state>
        @endif
    </x-gs::box>
</div>
@endsection

@section('moar_scripts')
<script>
$(function () {
    // Instant filters: selects apply on change, search after a short pause. The Search button stays for keyboards and no-JS.
    var $form = $('[data-govorg-autosubmit]');
    var timer;
    $form.on('change', 'select', function () { $form.trigger('submit'); });
    $form.on('input', 'input[type="search"]', function () {
        clearTimeout(timer);
        var value = this.value;
        timer = setTimeout(function () { if (value.length === 0 || value.length >= 2) { $form.trigger('submit'); } }, 500);
    });
    // Whole-row link; the office name stays the real, keyboard-reachable link.
    $('tr[data-govorg-href]').on('click', function (event) {
        if ($(event.target).closest('a, button, input, select, label').length) { return; }
        window.location = $(this).data('govorg-href');
    });
});
</script>
@endsection
