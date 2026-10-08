@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_operation_unit_management'))

@section('content')
<div class="row gs-op-unit-page" data-tracking-staff-url="{{ route('gov.tracking.operation-unit.search-users') }}" data-tracking-initiative="{{ $initiative->id }}" data-tracking-staff-placeholder="{{ __('govtracking::general.ui.staff_search') }}">
    <div class="col-md-10 col-md-offset-1">

        <div class="box box-solid" style="margin-bottom: 25px;">
            <div class="box-body text-right gs-op-summary-bar">
                <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default pull-left"><i class="fa fa-arrow-left"></i> {{ __('govtracking::general.task.back') }}</a>
                <span class="lead pull-right" style="margin-bottom: 0;">{{ __('govtracking::general.task.initiative') }} <strong>{{ $initiative->title }}</strong></span>
            </div>
        </div>

        <x-gs::alert tone="info" :title="__('govtracking::general.ui.extra_operation_unit_assignments')">
            <p>{{ __('govtracking::general.ui.designate_the_administrative_authorities_for_this_initiative') }} <strong>{{ __('govtracking::general.ui.a_single_operation_head_and_at_least_one_operation_officer_are_required') }}</strong> {{ __('govtracking::general.ui.before_this_project_can_be_activated_for_procurement_operations') }}</p>
        </x-gs::alert>

        <!-- 1. OPERATION HEAD -->
        <div class="gs-op-team-card gs-op-team-card--head">
            <div class="gs-op-team-header">
                <h4 class="gs-op-team-title"><i class="fa fa-star text-yellow"></i> {{ __('govtracking::general.ui.operation_head_project_director_lead') }}</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">{{ __('govtracking::general.ui.full_authority_over_the_initiative_only_one_person_may_hold_this_designation') }}</p>
            </div>
            <div class="gs-op-team-body">
                @if($head)
                    <div class="gs-op-staff-row gs-op-staff-row--head">
                        <div>
                            @php
                                $headName = $head->user ? "{$head->user->first_name} {$head->user->last_name}" : "Unknown User (ID: {$head->user_id})";
                            @endphp
                            <span class="gs-op-staff-name">{{ $headName }}</span><br>
                            <small class="gs-op-staff-meta">{{ __('govtracking::general.ui.extra_username') }} {{ $head->user->username ?? 'N/A' }} | {{ __('govtracking::general.ui.extra_emp_no') }} {{ $head->user->employee_num ?? 'N/A' }}</small>
                        </div>
                        <form action="{{ route('gov.tracking.initiatives.operation-unit.destroy', [$initiative->id, $head->id]) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" data-tracking-confirm="{{ __('govtracking::general.ui.confirm_head') }}"><i class="fa fa-times"></i> {{ __('govtracking::general.task.remove') }}</button>
                        </form>
                    </div>
                @else
                    <form action="{{ route('gov.tracking.initiatives.operation-unit.store', $initiative->id) }}" method="POST" class="form-inline gs-op-assign-form">
                        @csrf
                        <input type="hidden" name="designation" value="HEAD">
                        <div class="form-group" style="width: 70%;">
                            <select name="user_id" class="form-control user-search-select" style="width: 100%;" required>
                                <option value="">{{ __('govtracking::general.ui.search_staff_directory') }}</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning pull-right"><i class="fa fa-user-plus"></i> {{ __('govtracking::general.ui.assign_head') }}</button>
                    </form>
                @endif
            </div>
        </div>

        <!-- 2. OPERATION OFFICERS -->
        <div class="gs-op-team-card gs-op-team-card--officer">
            <div class="gs-op-team-header">
                <h4 class="gs-op-team-title"><i class="fa fa-user-tie text-aqua"></i> {{ __('govtracking::general.ui.operation_officers_planners_approvers') }}</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">{{ __('govtracking::general.ui.authorized_to_define_exact_delivery_matrices_and_manage_execution_tracking_codes') }}</p>
            </div>
            <div class="gs-op-team-body">
                @forelse($officers as $officer)
                    @php
                        $officerName = $officer->user ? "{$officer->user->first_name} {$officer->user->last_name}" : "Unknown User (ID: {$officer->user_id})";
                    @endphp
                    <div class="gs-op-staff-row">
                        <div>
                            <span class="gs-op-staff-name">{{ $officerName }}</span><br>
                            <small class="text-muted">{{ __('govtracking::general.ui.extra_username') }} {{ $officer->user->username ?? 'N/A' }} | {{ __('govtracking::general.ui.extra_emp_no') }} {{ $officer->user->employee_num ?? 'N/A' }}</small>
                        </div>
                        <form action="{{ route('gov.tracking.initiatives.operation-unit.destroy', [$initiative->id, $officer->id]) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-danger" title="{{ __('govtracking::general.ui.extra_remove_officer') }}"><i class="fa fa-times"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted text-center" style="padding: 15px; margin: 0; font-style: italic;">{{ __('govtracking::general.ui.no_operation_officers_designated_yet') }}</p>
                @endforelse

                <hr style="margin: 15px 0;">
                <form action="{{ route('gov.tracking.initiatives.operation-unit.store', $initiative->id) }}" method="POST" class="form-inline">
                    @csrf
                    <input type="hidden" name="designation" value="OFFICER">
                    <div class="form-group" style="width: 75%;">
                        <select name="user_id" class="form-control user-search-select" style="width: 100%;" required>
                            <option value="">{{ __('govtracking::general.ui.search_staff_directory') }}</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info pull-right"><i class="fa fa-plus"></i> {{ __('govtracking::general.ui.assign_officer') }}</button>
                </form>
            </div>
        </div>

        <!-- 3. SUPPORT STAFF -->
        <div class="gs-op-team-card gs-op-team-card--support">
            <div class="gs-op-team-header">
                <h4 class="gs-op-team-title"><i class="fa fa-users text-green"></i> {{ __('govtracking::general.ui.support_staff_document_handlers') }}</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">{{ __('govtracking::general.ui.optional_authorized_to_upload_official_documents_and_execute_retrospective_tagging') }}</p>
            </div>
            <div class="gs-op-team-body">
                @forelse($support as $staff)
                    @php
                        $staffName = $staff->user ? "{$staff->user->first_name} {$staff->user->last_name}" : "Unknown User (ID: {$staff->user_id})";
                    @endphp
                    <div class="gs-op-staff-row">
                        <div>
                            <span class="gs-op-staff-name">{{ $staffName }}</span><br>
                            <small class="text-muted">{{ __('govtracking::general.ui.extra_username') }} {{ $staff->user->username ?? 'N/A' }} | {{ __('govtracking::general.ui.extra_emp_no') }} {{ $staff->user->employee_num ?? 'N/A' }}</small>
                        </div>
                        <form action="{{ route('gov.tracking.initiatives.operation-unit.destroy', [$initiative->id, $staff->id]) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-danger" title="{{ __('govtracking::general.ui.extra_remove_support_staff') }}"><i class="fa fa-times"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted text-center" style="padding: 15px; margin: 0; font-style: italic;">{{ __('govtracking::general.ui.no_support_staff_designated') }}</p>
                @endforelse

                <hr style="margin: 15px 0;">
                <form action="{{ route('gov.tracking.initiatives.operation-unit.store', $initiative->id) }}" method="POST" class="form-inline">
                    @csrf
                    <input type="hidden" name="designation" value="SUPPORT">
                    <div class="form-group" style="width: 75%;">
                        <select name="user_id" class="form-control user-search-select" style="width: 100%;" required>
                            <option value="">{{ __('govtracking::general.ui.search_staff_directory') }}</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success pull-right"><i class="fa fa-plus"></i> {{ __('govtracking::general.ui.assign_support') }}</button>
                </form>
            </div>
        </div>

    </div>
</div>


@stop
