@extends('layouts/default')

@section('title', __('organization_labels::office.onboard_title'))

@section('content')
<div class="govorg">
    <x-gs::page-header :title="__('organization_labels::office.onboard_title')" :subtitle="__('organization_labels::office.onboard_subtitle')" />

    <div class="govorg-layout">
        <nav class="govorg-layout__nav" aria-label="{{ __('organization_labels::office.steps_label') }}">
            <x-gs::stepper orientation="vertical" :label="__('organization_labels::office.steps_label')" :steps="[
                ['label' => __('organization_labels::office.step_find'), 'state' => 'done'],
                ['label' => __('organization_labels::office.step_about'), 'state' => 'current'],
                ['label' => __('organization_labels::office.step_location'), 'state' => 'upcoming'],
                ['label' => __('organization_labels::office.step_reports'), 'state' => 'upcoming'],
                ['label' => __('organization_labels::office.step_create'), 'state' => 'upcoming'],
            ]" />
        </nav>
        <div class="govorg-layout__main">
            <x-gs::box>
                @include('govorg::partials.office-form', ['mode' => 'onboard'])
            </x-gs::box>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
@include('govorg::partials.geo-select-script')
@include('govorg::partials.duplicate-check-script')
@endsection
