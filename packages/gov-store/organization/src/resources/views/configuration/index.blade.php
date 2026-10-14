@extends('layouts/default')

@section('title', __('organization_labels::office.my_office_title'))

@php
    $active = in_array($profile->lifecycle_status, \GovStore\Organization\Services\OfficeLifecycleService::ACTIVE, true);
    $people = $localStaff->mapWithKeys(fn ($user) => [$user->id => $user->present()->fullName])->all();
    $geo = $profile->geoArea;
    $bangla = str_starts_with(strtolower(app()->getLocale()), 'bn');
@endphp

@section('content')
<div class="govorg">
    <x-gs::page-header :title="$location->name" :subtitle="__('organization_labels::office.my_office_subtitle')">
        <x-slot:meta>
            <span class="govorg-toolbar">
                <x-gs::status-badge :status="'office_'.$profile->lifecycle_status" />
                @if ($geo)<span>{{ $bangla && $geo->bn_name ? $geo->bn_name : $geo->en_name }}</span>@endif
                <span>{{ $location->company->name ?? __('organization_labels::office.not_linked') }}</span>
            </span>
        </x-slot:meta>
    </x-gs::page-header>

    <x-gs::box tone="primary">
        @include('govorg::partials.readiness', ['rolesAnchor' => '#govorg-roles'])
    </x-gs::box>

    @include('govorg::partials.roles', ['rolesAction' => route('gov.org.config.save'), 'adminForm' => false, 'locked' => ! $active])

    @include('govorg::partials.staff')
</div>
@endsection
