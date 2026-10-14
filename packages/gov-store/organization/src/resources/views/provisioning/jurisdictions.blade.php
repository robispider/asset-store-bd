@extends('layouts/default')

@section('title', __('organization_labels::office.section_ict'))

@php($bangla = str_starts_with(strtolower(app()->getLocale()), 'bn'))

@section('content')
<div class="govorg">
    @include('govorg::partials.admin-header', ['tab' => 'ict', 'ictCount' => $jurisdictions->count(), 'ministryCount' => $ministryCount ?? 0])

    <div class="govorg-layout">
        <div class="govorg-layout__main">
            <x-gs::box>
                @if ($jurisdictions->isEmpty())
                    <x-gs::empty-state icon="fa-user-shield" :title="__('organization_labels::office.ict_empty')" />
                @else
                    <x-gs::table :caption="__('organization_labels::office.ict_caption')">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('organization_labels::office.col_officer') }}</th>
                                <th scope="col">{{ __('organization_labels::office.col_scope_area') }}</th>
                                <th scope="col">{{ __('organization_labels::office.col_home') }}</th>
                                <th scope="col"><span class="sr-only">{{ __('organization_labels::office.col_actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jurisdictions as $jur)
                                @php($name = $jur->user?->present()->fullName ?? '—')
                                <tr>
                                    <td>
                                        <span class="govorg-toolbar"><x-gs::avatar :name="$name" size="sm" />
                                        <span><strong>{{ $name }}</strong><span class="govorg-meta govorg-mono">{{ $jur->user->username ?? '' }}</span></span></span>
                                    </td>
                                    <td>{{ $jur->geoArea ? ($bangla && $jur->geoArea->bn_name ? $jur->geoArea->bn_name : $jur->geoArea->en_name) : '—' }}</td>
                                    <td>{!! $jur->user?->location ? e($jur->user->location->name) : '<span class="govorg-muted">'.e(__('organization_labels::office.no_home')).'</span>' !!}</td>
                                    <td>
                                        <form method="POST" action="{{ route('gov.org.jurisdictions.destroy', $jur->id) }}" class="govorg-inline-form">
                                            @csrf
                                            <x-gs::button type="submit" size="sm" tone="light" outline :aria-label="__('organization_labels::office.remove_access_for', ['name' => $name])">{{ __('organization_labels::office.remove_access') }}</x-gs::button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-gs::table>
                @endif
            </x-gs::box>
        </div>

        <aside class="govorg-layout__aside">
            <x-gs::box :title="__('organization_labels::office.give_area_title')">
                <form method="POST" action="{{ route('gov.org.jurisdictions.store') }}" class="govorg-stack">
                    @csrf
                    @include('govorg::partials.errors')
                    <x-gs::form-row for="user_id" :label="__('organization_labels::office.person')" required>
                        <select class="form-control select2" name="user_id" id="user_id" required style="width: 100%">
                            <option value="">{{ __('organization_labels::office.person_placeholder') }}</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->present()->fullName }} ({{ $user->username }})</option>
                            @endforeach
                        </select>
                    </x-gs::form-row>
                    <x-gs::form-row for="geo_area_id" :label="__('organization_labels::office.area_manage')" required :help="__('organization_labels::office.area_manage_hint')">
                        <select class="form-control" name="geo_area_id" id="geo_area_id" required style="width: 100%" aria-describedby="geo_area_id-help"></select>
                    </x-gs::form-row>
                    <p class="govorg-muted govorg-small govorg-flush">{{ __('organization_labels::office.national_review_note') }}</p>
                    <div class="govorg-toolbar govorg-toolbar--end"><x-gs::button type="submit" icon="fa-shield-halved">{{ __('organization_labels::office.give_access') }}</x-gs::button></div>
                </form>
            </x-gs::box>
        </aside>
    </div>
</div>
@endsection

@section('moar_scripts')
@include('govorg::partials.geo-select-script')
<script>$(function () { window.govorgGeoSelect('#geo_area_id'); });</script>
@endsection
