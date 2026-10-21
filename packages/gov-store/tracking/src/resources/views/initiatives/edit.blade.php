@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_edit_initiative_properties'))

@section('content')
@php
    $planning = $initiative->status === 'Planning';
    $archived = $initiative->status === 'Archived';
@endphp
<div class="gs-tracking-form-page">
    <x-gs::page-header :title="__('govtracking::general.ui.extra_edit_initiative_properties')" :subtitle="$initiative->title" />

    @include('govtracking::tracking_codes.partials._errors')

    <form action="{{ route('gov.tracking.initiatives.update', $initiative->id) }}" method="POST">
        @csrf @method('PUT')

        <!-- State indicator alert -->
        @unless($planning)
            <x-gs::alert tone="warning" :title="__('govtracking::general.ui.operational_state_lock_active')">
                <p>{{ __('govtracking::general.ui.state_lock_body', ['status' => __('govtracking::general.monitor.initiative_status.'.$initiative->status)]) }}</p>
            </x-gs::alert>
        @endunless

        <!-- Section 1: The Identity -->
        <x-gs::box :title="__('govtracking::general.ui.1_initiative_details')">
            <div class="form-group">
                <label for="initiative_title">{{ __('govtracking::general.ui.initiative_title') }}</label>
                <input type="text" name="title" id="initiative_title" class="form-control input-lg" value="{{ old('title', $initiative->title) }}" required @disabled($archived)>
            </div>
            <div class="form-group">
                <label for="initiative_purpose">{{ __('govtracking::general.ui.business_purpose_description') }}</label>
                <textarea name="purpose" id="initiative_purpose" class="form-control" rows="3" @disabled($archived)>{{ old('purpose', $initiative->purpose) }}</textarea>
            </div>
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="initiative_funding">{{ __('govtracking::general.ui.primary_segment_type') }}</label>
                    @if($planning)
                        <select name="primary_funding" id="initiative_funding" class="form-control" required>
                            <option value="ADP" @selected($initiative->primary_funding == 'ADP')>{{ __('govtracking::general.ui.adp_development_budget') }}</option>
                            <option value="REVENUE" @selected($initiative->primary_funding == 'REVENUE')>{{ __('govtracking::general.ui.revenue_budget_non_development') }}</option>
                            <option value="OTHER" @selected($initiative->primary_funding == 'OTHER')>{{ __('govtracking::general.ui.other_autonomous_reserves') }}</option>
                        </select>
                    @else
                        <input type="text" id="initiative_funding" class="form-control" value="{{ __('govtracking::general.task.segment_budget', ['segment' => $initiative->primary_funding]) }}" disabled>
                        <input type="hidden" name="primary_funding" value="{{ $initiative->primary_funding }}">
                    @endif
                </div>
                <div class="col-md-6 form-group">
                    <label for="initiative_status">{{ __('govtracking::general.ui.initiative_state_lifecycle_stage') }}</label>
                    <select name="status" id="initiative_status" class="form-control" required @disabled($archived)>
                        <option value="Planning" @selected($initiative->status == 'Planning')>{{ __('govtracking::general.ui.planning_setup_phase') }}</option>
                        <option value="Active" @selected($initiative->status == 'Active')>{{ __('govtracking::general.ui.active_open_for_operations') }}</option>
                        <option value="Closed" @selected($initiative->status == 'Closed')>{{ __('govtracking::general.ui.closed_procurement_suspended') }}</option>
                        <option value="Archived" @selected($initiative->status == 'Archived')>{{ __('govtracking::general.ui.archived_read_only_audit_record') }}</option>
                    </select>
                </div>
            </div>
        </x-gs::box>

        <!-- Section 2: Ownership -->
        <x-gs::box :title="__('govtracking::general.ui.2_ownership')">
            <div class="form-group">
                <label for="owner_company_id">{{ __('govtracking::general.ui.which_organization_legally_owns_this_initiative') }}</label>
                @if($planning)
                    <select name="owner_company_id" id="owner_company_id" class="form-control select2" required>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected($initiative->owner_company_id == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" id="owner_company_id" class="form-control" value="{{ $initiative->ownerCompany->name ?? __('govtracking::general.ui.extra_unknown') }}" disabled>
                    <input type="hidden" name="owner_company_id" value="{{ $initiative->owner_company_id }}">
                @endif
            </div>
        </x-gs::box>

        <!-- Section 3: Governance Rules -->
        <x-gs::box :title="__('govtracking::general.ui.3_governance_execution_rules')">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="require_documents" value="1" @checked($initiative->require_documents) @disabled($archived)>
                    <strong>{{ __('govtracking::general.ui.require_official_documents') }}</strong> {{ __('govtracking::general.ui.ensure_a_pdf_order_document_is_uploaded_when_a_tracking_code_is_created') }}
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="allow_overshoot" value="1" @checked($initiative->allow_overshoot) @disabled($archived)>
                    <strong>{{ __('govtracking::general.ui.allow_target_overshoot') }}</strong> {{ __('govtracking::general.ui.allow_operations_like_grns_to_exceed_planned_targets_without_requiring_a_formal_override_justification') }}
                </label>
            </div>
        </x-gs::box>

        <!-- State-aware action footer -->
        <div class="gs-tracking-form-actions">
            @if($planning)
                <x-gs::button tone="danger" outline icon="fa-trash" class="gs-tracking-form-actions__start" data-tracking-delete="{{ __('govtracking::general.ui.confirm_initiative') }}">{{ __('govtracking::general.ui.delete_initiative') }}</x-gs::button>
            @endif
            <x-gs::button tone="secondary" outline :href="route('gov.tracking.initiatives.manage', $initiative->id)">{{ __('govtracking::general.task.cancel') }}</x-gs::button>
            @unless($archived)
                <x-gs::button type="submit" tone="primary">{{ __('govtracking::general.task.save') }}</x-gs::button>
            @endunless
        </div>
    </form>
</div>

@if($planning)
    <!-- Hidden Delete Action Form -->
    <form id="delete-initiative-form" action="{{ route('gov.tracking.initiatives.destroy', $initiative->id) }}" method="POST" hidden>
        @csrf @method('DELETE')
    </form>
@endif
@stop
