@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_edit_initiative_properties'))

@section('content')
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <form action="{{ route('gov.tracking.initiatives.update', $initiative->id) }}" method="POST">
            @csrf @method('PUT')

            <!-- State indicator alert -->
            @if($initiative->status !== 'Planning')
                <div class="callout callout-warning">
                    <h4><i class="fa fa-lock"></i> {{ __('govtracking::general.ui.operational_state_lock_active') }}</h4>
                    <p>{{ __('govtracking::general.ui.extra_because_this_initiative_is_currently') }} <strong>{{ strtoupper($initiative->status) }}</strong>{{ __('govtracking::general.ui.extra_its_core_financial_attributes_primary_segment_owning_organization_are_locked_and_cannot_be_changed_to_preserve_ledger_integrity') }}</p>
                </div>
            @endif

            <!-- Section 1: The Identity -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-aqua">{{ __('govtracking::general.ui.1_initiative_details') }}</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>{{ __('govtracking::general.ui.initiative_title') }}</label>
                        <input type="text" name="title" class="form-control input-lg" value="{{ old('title', $initiative->title) }}" required {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>
                    </div>
                    <div class="form-group">
                        <label>{{ __('govtracking::general.ui.business_purpose_description') }}</label>
                        <textarea name="purpose" class="form-control" rows="3" {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>{{ old('purpose', $initiative->purpose) }}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>{{ __('govtracking::general.ui.primary_segment_type') }}</label>
                            @if($initiative->status === 'Planning')
                                <select name="primary_funding" class="form-control" required>
                                    <option value="ADP" {{ $initiative->primary_funding == 'ADP' ? 'selected' : '' }}>{{ __('govtracking::general.ui.adp_development_budget') }}</option>
                                    <option value="REVENUE" {{ $initiative->primary_funding == 'REVENUE' ? 'selected' : '' }}>{{ __('govtracking::general.ui.revenue_budget_non_development') }}</option>
                                    <option value="OTHER" {{ $initiative->primary_funding == 'OTHER' ? 'selected' : '' }}>{{ __('govtracking::general.ui.other_autonomous_reserves') }}</option>
                                </select>
                            @else
                                <input type="text" class="form-control" value="{{ $initiative->primary_funding }} Budget" disabled>
                                <input type="hidden" name="primary_funding" value="{{ $initiative->primary_funding }}">
                            @endif
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('govtracking::general.ui.initiative_state_lifecycle_stage') }}</label>
                            <select name="status" class="form-control" required {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>
                                <option value="Planning" {{ $initiative->status == 'Planning' ? 'selected' : '' }}>{{ __('govtracking::general.ui.planning_setup_phase') }}</option>
                                <option value="Active" {{ $initiative->status == 'Active' ? 'selected' : '' }}>{{ __('govtracking::general.ui.active_open_for_operations') }}</option>
                                <option value="Closed" {{ $initiative->status == 'Closed' ? 'selected' : '' }}>{{ __('govtracking::general.ui.closed_procurement_suspended') }}</option>
                                <option value="Archived" {{ $initiative->status == 'Archived' ? 'selected' : '' }}>{{ __('govtracking::general.ui.archived_read_only_audit_record') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Ownership -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-orange">{{ __('govtracking::general.ui.2_ownership') }}</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>{{ __('govtracking::general.ui.which_organization_legally_owns_this_initiative') }}</label>
                        @if($initiative->status === 'Planning')
                            <select name="owner_company_id" class="form-control select2" required>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ $initiative->owner_company_id == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ $initiative->ownerCompany->name ?? __('govtracking::general.ui.extra_unknown') }}" disabled>
                            <input type="hidden" name="owner_company_id" value="{{ $initiative->owner_company_id }}">
                        @endif
                    </div>
                </div>
            </div>

            <!-- Section 3: Governance Rules -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-green">{{ __('govtracking::general.ui.3_governance_execution_rules') }}</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="require_documents" value="1" {{ $initiative->require_documents ? 'checked' : '' }} {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>
                                <strong>{{ __('govtracking::general.ui.require_official_documents') }}</strong> {{ __('govtracking::general.ui.ensure_a_pdf_order_document_is_uploaded_when_a_tracking_code_is_created') }}
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="allow_overshoot" value="1" {{ $initiative->allow_overshoot ? 'checked' : '' }} {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>
                                <strong>{{ __('govtracking::general.ui.allow_target_overshoot') }}</strong> {{ __('govtracking::general.ui.allow_operations_like_grns_to_exceed_planned_targets_without_requiring_a_formal_override_justification') }}
                            </label>
                        </div>
                    </div>
                </div>

                <!-- State-aware action footer -->
                <div class="box-footer text-right">
                    @if($initiative->status === 'Planning')
                        <button type="button" class="btn btn-danger pull-left" data-tracking-delete="{{ __('govtracking::general.ui.confirm_initiative') }}"><i class="fa fa-trash"></i> {{ __('govtracking::general.ui.delete_initiative') }}</button>
                    @endif

                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default">{{ __('govtracking::general.task.cancel') }}</a>

                    @if($initiative->status !== 'Archived')
                        <button type="submit" class="btn btn-warning">{{ __('govtracking::general.task.save') }}</button>
                    @endif
                </div>
            </div>

        </form>
    </div>
</div>

@if($initiative->status === 'Planning')
    <!-- Hidden Delete Action Form -->
    <form id="delete-initiative-form" action="{{ route('gov.tracking.initiatives.destroy', $initiative->id) }}" method="POST" style="display: none;">
        @csrf @method('DELETE')
    </form>


@endif
@stop
