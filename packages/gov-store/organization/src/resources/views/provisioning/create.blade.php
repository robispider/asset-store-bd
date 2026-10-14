@extends('layouts/default')

@section('title', __('organization_labels::office.add_title'))

@section('content')
<div class="govorg">
    <x-gs::page-header :title="__('organization_labels::office.add_title')" :subtitle="__('organization_labels::office.add_subtitle')" />

    <div class="govorg-layout">
        <nav class="govorg-layout__nav" aria-label="{{ __('organization_labels::office.steps_label') }}">
            <x-gs::stepper orientation="vertical" :label="__('organization_labels::office.steps_label')" :steps="[
                ['label' => __('organization_labels::office.step_find'), 'state' => $find !== '' ? 'done' : 'current'],
                ['label' => __('organization_labels::office.step_about'), 'state' => $find !== '' ? 'current' : 'upcoming'],
                ['label' => __('organization_labels::office.step_location'), 'state' => 'upcoming'],
                ['label' => __('organization_labels::office.step_reports'), 'state' => 'upcoming'],
                ['label' => __('organization_labels::office.step_create'), 'state' => 'upcoming'],
            ]" />
        </nav>

        <div class="govorg-layout__main govorg-stack">
            <x-gs::box>
                <section class="govorg-step" id="govorg-step-find" aria-labelledby="govorg-step-find-title">
                    <h2 class="govorg-step__title" id="govorg-step-find-title">{{ __('organization_labels::office.find_title') }}</h2>
                    <p class="govorg-step__hint">{{ __('organization_labels::office.find_hint') }}</p>
                    <form method="GET" action="{{ route('gov.org.provisioning.create') }}" role="search" class="govorg-filters govorg-flush">
                        <label class="sr-only" for="find">{{ __('organization_labels::office.find_label') }}</label>
                        <input type="search" class="form-control govorg-filters__search" name="find" id="find" value="{{ $find }}" placeholder="{{ __('organization_labels::office.find_label') }}" maxlength="100">
                        <x-gs::button type="submit" tone="light" outline icon="fa-search">{{ __('organization_labels::office.search') }}</x-gs::button>
                    </form>
                    @if ($find !== '')
                        @if ($matches->isNotEmpty())
                            <p class="govorg-muted govorg-small" aria-live="polite">{{ trans_choice('organization_labels::office.find_results', $matches->count(), ['count' => $matches->count()]) }}</p>
                            <ul class="govorg-results">
                                @foreach ($matches as $match)
                                    <li class="govorg-result">
                                        <div class="govorg-result__main"><strong>{{ $match->name }}</strong><span class="govorg-meta">{{ __('organization_labels::office.find_not_set_up') }}</span></div>
                                        <x-gs::status-badge status="office_unconfigured" />
                                        <x-gs::button size="sm" :href="route('gov.org.provisioning.onboard', ['location_id' => $match->id])">{{ __('organization_labels::office.find_setup') }}</x-gs::button>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="govorg-muted govorg-flush">{{ __('organization_labels::office.find_new') }}</p>
                        @else
                            <p class="govorg-muted govorg-flush" aria-live="polite">{{ __('organization_labels::office.find_none', ['term' => $find]) }}</p>
                        @endif
                    @endif
                </section>
            </x-gs::box>

            <x-gs::box>
                @include('govorg::partials.office-form', ['mode' => 'create', 'preselectedLocation' => null, 'unprovisionedLocations' => collect()])
            </x-gs::box>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
@include('govorg::partials.geo-select-script')
@include('govorg::partials.duplicate-check-script')
@endsection
