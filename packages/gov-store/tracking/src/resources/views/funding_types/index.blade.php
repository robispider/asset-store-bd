@extends('layouts/default')

@section('title', __('govtracking::general.ui.extra_system_configuration_funding_sources'))

@section('content')
@php
    $u = 'govtracking::general.ui.';
    $segmentTone = ['ADP' => 'success', 'REVENUE' => 'primary'];
@endphp
<div class="gs-tracking-form-page gs-tracking-form-page--wide">
    <x-gs::page-header :title="__($u.'extra_system_configuration_funding_sources')" :subtitle="__($u.'funding_types_intro')" />

    @include('govtracking::tracking_codes.partials._errors')

    <div class="gs-tracking-settings-grid">
        <form action="{{ route('gov.tracking.funding-types.store') }}" method="POST">
            @csrf
            <x-gs::box :title="__($u.'add_new_funding_source')">
                <div class="form-group">
                    <label for="funding_primary_type">{{ __($u.'primary_segment_type') }}</label>
                    <select name="primary_type" id="funding_primary_type" class="form-control" required>
                        <option value="ADP">{{ __($u.'adp_development_budget') }}</option>
                        <option value="REVENUE">{{ __($u.'revenue_budget_non_development') }}</option>
                        <option value="OTHER">{{ __($u.'other_sources_autonomous') }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="funding_name">{{ __($u.'sub_source_name') }}</label>
                    <input type="text" name="name" id="funding_name" class="form-control" value="{{ old('name') }}" placeholder="{{ __($u.'extra_e_g_project_aid_pa_gob_taka') }}" required>
                </div>
                <div class="form-group">
                    <label for="funding_description">{{ __($u.'description_optional') }}</label>
                    <textarea name="description" id="funding_description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <x-slot:footer>
                    <x-gs::button type="submit">{{ __($u.'save_funding_source') }}</x-gs::button>
                </x-slot:footer>
            </x-gs::box>
        </form>

        <x-gs::box :title="__($u.'active_funding_sources_dictionary')">
            <x-gs::table>
                <thead>
                    <tr>
                        <th scope="col">{{ __($u.'primary_segment') }}</th>
                        <th scope="col">{{ __($u.'sub_source_name') }}</th>
                        <th scope="col">{{ __($u.'description') }}</th>
                        <th scope="col" class="gs-num">{{ __($u.'active_task_count') }}</th>
                        <th scope="col" class="gs-num">{{ __($u.'action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fundingTypes as $type)
                        <tr>
                            <td><x-gs::badge :tone="$segmentTone[$type->primary_type] ?? 'secondary'">{{ $type->primary_type }}</x-gs::badge></td>
                            <td><strong>{{ $type->name }}</strong></td>
                            <td>{{ $type->description }}</td>
                            <td class="gs-num">{{ $type->tracking_codes_count }}</td>
                            <td class="gs-num">
                                <form action="{{ route('gov.tracking.funding-types.destroy', $type->id) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <x-gs::button type="submit" tone="danger" outline size="sm" icon="fa-trash" :disabled="$type->tracking_codes_count > 0" :aria-label="__('govtracking::general.delete').' '.$type->name" data-tracking-confirm="{{ __($u.'confirm_funding') }}"></x-gs::button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="gs-table__empty">{{ __($u.'no_funding_sources_configured_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </x-gs::table>
        </x-gs::box>
    </div>
</div>
@stop
