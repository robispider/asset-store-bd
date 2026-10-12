@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_modify_task') . $trackingCode->tracking_code)

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">

        <!-- Programme Context Path Indicator -->
        <div class="gs-tracking-breadcrumb-path">
            <ol class="breadcrumb">
                <li><a href="{{ route('gov.tracking.initiatives.index') }}" class="text-muted"><i class="fa fa-briefcase"></i> {{ __('govtracking::general.task.programmes') }}</a></li>
                <li><a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="text-blue"><strong>{{ $initiative->title }}</strong></a></li>
                <li><a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="text-muted">{{ __('govtracking::general.task.tasks') }}</a></li>
                <li class="active text-muted">{{ __('govtracking::general.ui.extra_modify_parameters') }} ({{ $trackingCode->tracking_code }})</li>
            </ol>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger gs-tracking-task-errors">
                <strong>{{ __('govtracking::general.task.check_parameters') }}</strong>
                <ul style="margin-left: 15px; padding-left: 0; margin-top: 5px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('gov.tracking.initiatives.tracking-codes.update', [$initiative->id, $trackingCode->id]) }}" method="POST" enctype="multipart/form-data" id="task-modification-form">
            @csrf
            @method('PUT')

            <!-- 1. What are you creating? -->
            @include('govtracking::tracking_codes.partials._task_identity')

            <!-- 2. Where can this task operate? -->
            @include('govtracking::tracking_codes.partials._execution_scopes')

            <!-- 3. Which budget funds this task? -->
            @include('govtracking::tracking_codes.partials._fiscal_profile')

            <!-- 4. Immutable Specificity / Strategy Indicator -->
            <div class="box box-solid bg-gray-light gs-tracking-strategy-box">
                <div class="box-body gs-tracking-strategy-box__body">
                    <span class="gs-tracking-strategy-text">
                        <i class="fa fa-lock text-muted" style="margin-right: 5px;"></i> <strong>{{ __('govtracking::general.task.strategy') }}</strong>
                        @if($trackingCode->specificity_level === '1_BLANKET')
                            <span class="label label-default" style="font-size: 11px; margin-left: 5px;">{{ __('govtracking::general.task.open_allocation') }}</span>
                        @elseif($trackingCode->specificity_level === '2_CATEGORY')
                            <span class="label label-success" style="font-size: 11px; margin-left: 5px;">{{ __('govtracking::general.task.category_targets') }}</span>
                        @elseif($trackingCode->specificity_level === '3_MATRIX')
                            <span class="label label-primary" style="font-size: 11px; margin-left: 5px;">{{ __('govtracking::general.task.office_schedule') }}</span>
                        @endif
                    </span>
                    <span class="help-block text-muted" style="margin-top: 5px; margin-bottom: 0; font-size: 11px; line-height: 1.4;">
                        {{ __('govtracking::general.task.strategy_locked') }}
                    </span>
                    <input type="hidden" name="specificity_level" value="{{ $trackingCode->specificity_level }}">
                </div>
            </div>

            <!-- Include Only the Active Target Allocation Panel -->
            <div id="allocation-worksheets-container">
                @if($trackingCode->specificity_level === '1_BLANKET')
                    @include('govtracking::tracking_codes.partials._level1_blanket')
                @elseif($trackingCode->specificity_level === '2_CATEGORY')
                    @include('govtracking::tracking_codes.partials._level2_category_list')
                @elseif($trackingCode->specificity_level === '3_MATRIX')
                    @include('govtracking::tracking_codes.partials._level3_matrix_grid')
                @endif
            </div>

            <!-- Submit Footer -->
            <div class="box box-solid gs-tracking-submit-box">
                <div class="box-footer text-right gs-tracking-submit-footer">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default btn-lg" style="margin-right: 10px;">{{ __('govtracking::general.task.cancel') }}</a>
                    <button type="submit" class="btn btn-warning btn-lg" style="font-weight: bold;"><i class="fa fa-check-circle"></i> {{ __('govtracking::general.task.save') }}</button>
                </div>
            </div>

        </form>
        @include('govtracking::tracking_codes.partials._documents')
    </div>
</div>

@include('govtracking::tracking_codes.partials._task_data')
@stop
