@extends('layouts/default')

@section('title', __('organization_labels::office.waiting_title'))

@php($admin = $profile?->officeAdmin)

@section('content')
<div class="govorg govorg-staff-page">
    <x-gs::box class="govorg-staff-card">
        <div class="govorg-stack">
            <div>
                <h1 class="govorg-staff-card__heading">{{ __('organization_labels::office.waiting_heading') }}</h1>
                <p class="govorg-muted govorg-flush">{{ __('organization_labels::office.waiting_body', ['name' => $location->name]) }}</p>
            </div>

            {{-- The real checklist, read-only: staff see exactly what is done and what is not. --}}
            @include('govorg::partials.readiness', ['readonly' => true, 'roles' => null, 'people' => []])

            @if ($admin)
                <div class="govorg-contact">
                    <x-gs::avatar :name="$admin->present()->fullName" />
                    <div class="govorg-contact__who">
                        <strong>{{ $admin->present()->fullName }}</strong>
                        <span class="govorg-meta">{{ __('organization_labels::office.waiting_contact_role') }}</span>
                    </div>
                    @if ($admin->email)
                        <x-gs::button size="sm" icon="fa-envelope" :href="'mailto:'.$admin->email">{{ __('organization_labels::office.email_person', ['name' => $admin->first_name]) }}</x-gs::button>
                    @endif
                </div>
            @else
                <x-gs::alert tone="info">{{ __('organization_labels::office.waiting_no_admin') }}</x-gs::alert>
            @endif

            <div><x-gs::button tone="light" outline :href="url('/')" icon="fa-house">{{ __('organization_labels::office.home') }}</x-gs::button></div>
        </div>
    </x-gs::box>
</div>
@endsection
