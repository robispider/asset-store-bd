@extends('layouts/default')

@section('title', $location->name)

@php
    $status = $profile->lifecycle_status;
    $active = in_array($status, \GovStore\Organization\Services\OfficeLifecycleService::ACTIVE, true);
    $bangla = str_starts_with(strtolower(app()->getLocale()), 'bn');
    $geo = $profile->geoArea;
    $areaName = $geo ? ($bangla && $geo->bn_name ? $geo->bn_name : $geo->en_name) : null;
    $people = $localStaff->mapWithKeys(fn ($user) => [$user->id => $user->present()->fullName])->all();
    $timeline = $activityLogs->map(function ($log) {
        $key = 'organization_labels::office.event_'.$log->event_type;
        $action = __($key) === $key ? __('organization_labels::office.event_other') : __($key);
        $reason = $log->details['reason'] ?? ($log->details['message'] ?? null);

        return [
            'by' => $log->performer?->display_name ?? __('organization_labels::office.activity_system'),
            'action' => $action,
            'target' => $reason ? __('organization_labels::office.activity_reason', ['reason' => $reason]) : '',
            'at' => $log->created_at?->format('Y-m-d H:i'),
        ];
    })->all();
    $starterStatus = $starter->status ?? 'waiting';
@endphp

@section('content')
<div class="govorg">
    <x-gs::page-header :title="$location->name">
        <x-slot:meta>
            <span class="govorg-toolbar">
                <x-gs::status-badge :status="'office_'.$status" />
                <span>{{ __('organization_labels::orglabel.office_type_'.($profile->office_type ?? 'default')) }}</span>
                @if ($areaName)<span>{{ $areaName }}</span>@endif
                <span>{{ $location->company->name ?? __('organization_labels::office.not_linked') }}</span>
                @if ($location->parent)<span>{{ __('organization_labels::office.reports_to', ['name' => $location->parent->name]) }}</span>@endif
            </span>
        </x-slot:meta>
    </x-gs::page-header>

    <x-gs::box tone="primary">
        @include('govorg::partials.readiness', ['rolesAnchor' => '#govorg-roles'])
    </x-gs::box>

    <div class="govorg-layout">
        <nav class="govorg-layout__nav govorg-toc" aria-label="{{ __('organization_labels::office.hub_sections') }}">
            <a href="#govorg-roles">{{ __('organization_labels::office.section_roles') }}</a>
            <a href="#govorg-staff">{{ __('organization_labels::office.section_staff') }} <span class="govorg-muted">{{ $localStaff->count() }}</span></a>
            <a href="#govorg-details">{{ __('organization_labels::office.section_details') }}</a>
            <a href="#govorg-location">{{ __('organization_labels::office.section_location') }}</a>
            <a href="#govorg-activity">{{ __('organization_labels::office.section_activity') }}</a>
            <a href="#govorg-settings">{{ __('organization_labels::office.section_settings') }}</a>
        </nav>

        <div class="govorg-layout__main govorg-stack">
            @include('govorg::partials.roles', ['rolesAction' => route('gov.org.hub.save-roles', $location->id), 'adminForm' => true, 'locked' => ! $active])

            @include('govorg::partials.staff')

            <x-gs::box :title="__('organization_labels::office.section_details')" id="govorg-details">
                <x-gs::key-value :items="[
                    ['label' => __('organization_labels::office.detail_name'), 'value' => $location->name],
                    ['label' => __('organization_labels::office.detail_type'), 'value' => __('organization_labels::orglabel.office_type_'.($profile->office_type ?? 'default'))],
                    ['label' => __('organization_labels::office.detail_ministry'), 'value' => $location->company->name ?? __('organization_labels::office.not_linked')],
                    ['label' => __('organization_labels::office.detail_parent'), 'value' => $location->parent->name ?? __('organization_labels::office.no_parent')],
                ]" />
                <p class="govorg-muted govorg-small"><i class="fas fa-lock" aria-hidden="true"></i> {{ __('organization_labels::office.ministry_locked') }}</p>
                @if ($active)
                    <details>
                        <summary>{{ __('organization_labels::orglabel.office_profile_edit') }}</summary>
                        <form method="POST" action="{{ route('gov.org.hub.update', $location->id) }}" class="govorg-filters">
                            @csrf
                            <input type="hidden" name="company_id" value="{{ $location->company_id }}">
                            <input type="hidden" name="parent_id" value="{{ $location->parent_id }}">
                            <input type="hidden" name="geo_area_id" value="{{ $profile->geo_area_id }}">
                            <input type="hidden" name="office_admin_id" value="{{ $profile->office_admin_id }}">
                            <label class="sr-only" for="rename">{{ __('organization_labels::office.name') }}</label>
                            <input type="text" class="form-control govorg-filters__search" id="rename" name="name" value="{{ old('name', $location->name) }}" required maxlength="255">
                            <label for="office-level">{{ __('organization_labels::orglabel.office_level_label') }}</label>
                            <select class="form-control" id="office-level" name="office_level">
                                <option value="">{{ __('organization_labels::orglabel.office_level_inferred') }}</option>
                                @foreach (\GovStore\Organization\Models\LocationProfile::OFFICE_LEVELS as $level)
                                    <option value="{{ $level }}" @selected(old('office_level', $profile->office_level) === $level)>{{ __('organization_labels::orglabel.office_level_'.$level) }}</option>
                                @endforeach
                            </select>
                            @error('office_level')<p class="gs-form-row__error" role="alert">{{ $message }}</p>@enderror
                            <x-gs::button type="submit" tone="light" outline>{{ __('organization_labels::office.rename_save') }}</x-gs::button>
                        </form>
                        @error('name')<p class="gs-form-row__error" role="alert">{{ $message }}</p>@enderror
                    </details>
                @endif
            </x-gs::box>

            <x-gs::box :title="__('organization_labels::office.section_location')" id="govorg-location">
                <div class="govorg-stack">
                    <div>
                        <strong>{{ $areaName ?? __('organization_labels::office.not_placed') }}</strong>
                        @if (! empty($geoTrail))<span class="govorg-meta"><x-gs::trail :items="$geoTrail" /></span>@endif
                    </div>
                    @if ($profile->geo_area_verified_at)
                        <x-gs::alert tone="success">{{ __('organization_labels::office.location_confirmed', [
                            'name' => $profile->verifier->display_name ?? __('organization_labels::office.activity_system'),
                            'date' => $profile->geo_area_verified_at->format('Y-m-d'),
                        ]) }}</x-gs::alert>
                    @elseif ($active)
                        <x-gs::alert tone="info">
                            {{ __('organization_labels::office.location_unconfirmed') }}
                            <div class="govorg-toolbar">
                                <form method="POST" action="{{ route('gov.org.hub.verify-geo', $location->id) }}" class="govorg-inline-form">
                                    @csrf
                                    <x-gs::button type="submit" size="sm" icon="fa-check">{{ __('organization_labels::office.location_confirm') }}</x-gs::button>
                                </form>
                                <button type="button" class="gs-btn gs-btn--light gs-btn--outline gs-btn--sm" data-toggle="modal" data-target="#govorg-dialog-relocate">{{ __('organization_labels::office.location_wrong') }}</button>
                            </div>
                        </x-gs::alert>
                    @endif
                    @if ($geo)
                        <details class="govorg-muted">
                            <summary>{{ __('organization_labels::office.technical_details') }}</summary>
                            <p class="govorg-mono govorg-flush">{{ __('organization_labels::office.area_code') }} {{ $geo->bbs_code ?? $geo->hid }} · {{ __('organization_labels::office.area_level') }} {{ $geo->GeoLevel }} · {{ __('organization_labels::office.location_id') }} {{ $location->id }}</p>
                        </details>
                    @endif
                </div>
            </x-gs::box>

            <x-gs::box :title="__('organization_labels::office.starter_title')">
                @if ($starterStatus === 'failed')
                    <x-gs::alert tone="danger">{{ __('organization_labels::office.starter_failed', ['reference' => $starter->failure_reference ?? '—']) }}</x-gs::alert>
                @else
                    <p class="govorg-flush">{{ __('organization_labels::office.starter_'.(in_array($starterStatus, ['waiting', 'pending', 'completed'], true) ? $starterStatus : 'waiting')) }}</p>
                @endif
                @if ($profile->office_admin_id && $active && (! $starter || $starter->status !== 'completed'))
                    <form method="POST" action="{{ route('gov.org.hub.retry-starter', $location->id) }}" class="govorg-toolbar">
                        @csrf
                        <x-gs::button type="submit" size="sm" tone="light" outline icon="fa-rotate">{{ __('organization_labels::office.starter_retry') }}</x-gs::button>
                    </form>
                @endif
            </x-gs::box>

            <x-gs::box :title="__('organization_labels::office.section_activity')" id="govorg-activity">
                @if ($timeline)
                    <x-gs::timeline :items="$timeline" />
                @else
                    <p class="govorg-muted govorg-flush">{{ __('organization_labels::office.activity_empty') }}</p>
                @endif
            </x-gs::box>

            @include('govorg::provisioning.lifecycle')
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
@include('govorg::partials.geo-select-script')
<script>
$(function () {
    window.govorgGeoSelect('#lifecycle-geo', { dropdownParent: $('#govorg-dialog-relocate') });
    // A lifecycle form that failed validation reopens its dialog with the message and the typed reason.
    $('[data-govorg-reopen]').modal('show');
});
</script>
@endsection
