@extends('layouts/default')
@section('title', __('govtracking::general.ui.launch_new_initiative'))

@section('content')
<div class="gs-tracking-form-page">
    <x-gs::page-header :title="__('govtracking::general.ui.launch_new_initiative')" :subtitle="__('govtracking::general.ui.new_programme_intro')" />

    @include('govtracking::tracking_codes.partials._errors')

    <form action="{{ route('gov.tracking.initiatives.store') }}" method="POST">
        @csrf

        <!-- Section 1: The Identity -->
        <x-gs::box :title="__('govtracking::general.ui.1_initiative_details')">
            <div class="form-group">
                <label for="initiative_title">{{ __('govtracking::general.ui.initiative_title') }}</label>
                <input type="text" name="title" id="initiative_title" class="form-control input-lg" value="{{ old('title') }}" placeholder="{{ __('govtracking::general.ui.extra_e_g_school_ict_modernization_2027') }}" required>
            </div>
            <div class="form-group">
                <label for="initiative_purpose">{{ __('govtracking::general.ui.business_purpose_description') }}</label>
                <textarea name="purpose" id="initiative_purpose" class="form-control" rows="3" placeholder="{{ __('govtracking::general.ui.extra_what_is_the_operational_goal_of_this_umbrella_initiative') }}">{{ old('purpose') }}</textarea>
            </div>
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="initiative_funding">{{ __('govtracking::general.ui.primary_segment_type') }}</label>
                    <select name="primary_funding" id="initiative_funding" class="form-control" required>
                        <option value="ADP" @selected(old('primary_funding') === 'ADP')>{{ __('govtracking::general.ui.adp_development_budget') }}</option>
                        <option value="REVENUE" @selected(old('primary_funding') === 'REVENUE')>{{ __('govtracking::general.ui.revenue_budget_non_development') }}</option>
                        <option value="OTHER" @selected(old('primary_funding') === 'OTHER')>{{ __('govtracking::general.ui.other_autonomous_reserves') }}</option>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label for="initiative_status">{{ __('govtracking::general.ui.initial_lifecycle_status') }}</label>
                    <select name="status" id="initiative_status" class="form-control" required>
                        <option value="Planning" selected>{{ __('govtracking::general.ui.planning_setup_phase') }}</option>
                    </select>
                </div>
            </div>
        </x-gs::box>

        <!-- Section 2: Natural Language Ownership & Accountability -->
        <x-gs::box :title="__('govtracking::general.ui.2_ownership_accountability')">
            <div class="form-group">
                <label for="owner_company_id">{{ __('govtracking::general.ui.which_organization_legally_owns_this_initiative') }}</label>
                <select id="owner_company_id" name="owner_company_id" class="form-control select2" required>
                    <option value="">{{ __('govtracking::general.ui.select_ministry_organization') }}</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) old('owner_company_id') === (string) $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>

            <x-gs::alert tone="warning" :title="__('govtracking::general.ui.extra_operation_unit_required')">
                <p>{{ __('govtracking::general.ui.you_must_designate_an_operation_head_and_at_least_one_operation_officer_inside_the_workspace_immediately_after_launching_before_this_initiative_can_be_activated') }}</p>
            </x-gs::alert>
        </x-gs::box>

        <!-- Section 3: Governance Rules -->
        <x-gs::box :title="__('govtracking::general.ui.3_governance_execution_rules')">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="require_documents" value="1" @checked(old('require_documents', '1'))>
                    <strong>{{ __('govtracking::general.ui.require_official_documents') }}</strong> {{ __('govtracking::general.ui.ensure_a_pdf_order_document_is_uploaded_when_a_tracking_code_is_created') }}
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="allow_overshoot" value="1" @checked(old('allow_overshoot'))>
                    <strong>{{ __('govtracking::general.ui.allow_target_overshoot') }}</strong> {{ __('govtracking::general.ui.allow_operations_like_grns_to_exceed_planned_targets_without_requiring_a_formal_override_justification') }}
                </label>
            </div>
        </x-gs::box>

        <div class="gs-tracking-form-actions">
            <x-gs::button tone="secondary" outline :href="route('gov.tracking.initiatives.index')">{{ __('govtracking::general.task.cancel') }}</x-gs::button>
            <x-gs::button type="submit" tone="success" icon="fa-rocket">{{ __('govtracking::general.ui.launch_initiative_workspace') }}</x-gs::button>
        </div>
    </form>
</div>
@stop
