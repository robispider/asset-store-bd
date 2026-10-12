@extends('layouts/default')
@section('title', __('govtracking::general.ui.launch_new_initiative'))

@section('content')
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <form action="{{ route('gov.tracking.initiatives.store') }}" method="POST">
            @csrf

            <!-- Section 1: The Identity -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-aqua">{{ __('govtracking::general.ui.1_initiative_details') }}</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>{{ __('govtracking::general.ui.initiative_title') }}</label>
                        <input type="text" name="title" class="form-control input-lg" placeholder="{{ __('govtracking::general.ui.extra_e_g_school_ict_modernization_2027') }}" required>
                    </div>
                    <div class="form-group">
                        <label>{{ __('govtracking::general.ui.business_purpose_description') }}</label>
                        <textarea name="purpose" class="form-control" rows="3" placeholder="{{ __('govtracking::general.ui.extra_what_is_the_operational_goal_of_this_umbrella_initiative') }}"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>{{ __('govtracking::general.ui.primary_segment_type') }}</label>
                            <select name="primary_funding" class="form-control" required>
                                <option value="ADP">{{ __('govtracking::general.ui.adp_development_budget') }}</option>
                                <option value="REVENUE">{{ __('govtracking::general.ui.revenue_budget_non_development') }}</option>
                                <option value="OTHER">{{ __('govtracking::general.ui.other_autonomous_reserves') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('govtracking::general.ui.initial_lifecycle_status') }}</label>
                            <select name="status" class="form-control" required>
                                <option value="Planning" selected>{{ __('govtracking::general.ui.planning_setup_phase') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Natural Language Ownership & Accountability -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-orange">{{ __('govtracking::general.ui.2_ownership_accountability') }}</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>{{ __('govtracking::general.ui.which_organization_legally_owns_this_initiative') }}</label>
                        <select id="owner_company_id" name="owner_company_id" class="form-control select2" required>
                            <option value="">{{ __('govtracking::general.ui.select_ministry_organization') }}</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-gs::alert tone="warning" :title="__('govtracking::general.ui.extra_operation_unit_required')">
                        <p class="text-sm">{{ __('govtracking::general.ui.you_must_designate_an_operation_head_and_at_least_one_operation_officer_inside_the_workspace_immediately_after_launching_before_this_initiative_can_be_activated') }}</p>
                    </x-gs::alert>
                </div>
            </div>

            <!-- Section 3: Governance Rules -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-green">{{ __('govtracking::general.ui.3_governance_execution_rules') }}</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="require_documents" value="1" checked>
                                <strong>{{ __('govtracking::general.ui.require_official_documents') }}</strong> {{ __('govtracking::general.ui.ensure_a_pdf_order_document_is_uploaded_when_a_tracking_code_is_created') }}
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="allow_overshoot" value="1">
                                <strong>{{ __('govtracking::general.ui.allow_target_overshoot') }}</strong> {{ __('govtracking::general.ui.allow_operations_like_grns_to_exceed_planned_targets_without_requiring_a_formal_override_justification') }}
                            </label>
                        </div>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <a href="{{ route('gov.tracking.initiatives.index') }}" class="btn btn-default">{{ __('govtracking::general.task.cancel') }}</a>
                    <button type="submit" class="btn btn-success btn-lg">{{ __('govtracking::general.ui.launch_initiative_workspace') }}</button>
                </div>
            </div>

        </form>
    </div>
</div>
@stop
