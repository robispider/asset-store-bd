@extends('layouts/default')

@section('title', __('organization_labels::office.unassigned_title'))

@section('content')
<div class="govorg govorg-staff-page">
    <x-gs::box class="govorg-staff-card">
        <div class="govorg-stack">
            <div>
                <h1 class="govorg-staff-card__heading">{{ __('organization_labels::office.unassigned_heading') }}</h1>
                <p class="govorg-muted govorg-flush">{{ __('organization_labels::office.unassigned_body') }}</p>
            </div>
            <div>
                <strong>{{ __('organization_labels::office.unassigned_what') }}</strong>
                <ol class="govorg-steps-list">
                    <li>{{ __('organization_labels::office.unassigned_step1') }}</li>
                    <li>{{ __('organization_labels::office.unassigned_step2') }} <span class="govorg-mono">{{ auth()->user()?->username }}</span></li>
                    <li>{{ __('organization_labels::office.unassigned_step3') }}</li>
                </ol>
            </div>
            <div><x-gs::button tone="light" outline :href="url('/')" icon="fa-house">{{ __('organization_labels::office.home') }}</x-gs::button></div>
        </div>
    </x-gs::box>
</div>
@endsection
