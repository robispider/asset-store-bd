@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_create_task') . $initiative->title)

@section('content')
@php
    // Defensive initializations to support the reuse of edit partials inside the creation flow
    $trackingCode = null;
    $activeGeo = null;
    $activePart = null;
    $activeLocationIds = [];
@endphp

<div class="row">
    <div class="col-md-10 col-md-offset-1">

        <!-- Programme Context Path Indicator -->
        <div class="gs-tracking-breadcrumb-path">
            <ol class="breadcrumb">
                <li><a href="{{ route('gov.tracking.initiatives.index') }}" class="text-muted"><i class="fa fa-briefcase"></i> {{ __('govtracking::general.task.programmes') }}</a></li>
                <li><a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="text-blue"><strong>{{ $initiative->title }}</strong></a></li>
                <li class="active text-muted">{{ __('govtracking::general.task.create_task') }}</li>
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

        <form action="{{ route('gov.tracking.initiatives.tracking-codes.store', $initiative->id) }}" method="POST" enctype="multipart/form-data" id="task-creation-form">
            @csrf

            <!-- 1. What are you creating? -->
            @include('govtracking::tracking_codes.partials._task_identity')

            <!-- 2. Where can this task operate? -->
            @include('govtracking::tracking_codes.partials._execution_scopes')

            <!-- 3. Which budget funds this task? -->
            @include('govtracking::tracking_codes.partials._fiscal_profile')

            <!-- 4. How will deliveries be managed? -->
            @include('govtracking::tracking_codes.partials._specificity_selector')

            <!-- Dynamic Target Allocation Worksheet Panels -->
            <div id="allocation-worksheets-container">
                @include('govtracking::tracking_codes.partials._level1_blanket')
                @include('govtracking::tracking_codes.partials._level2_category_list')
                @include('govtracking::tracking_codes.partials._level3_matrix_grid')
            </div>

            <!-- Submit Footer -->
            <div class="box box-solid gs-tracking-submit-box">
                <div class="box-footer text-right gs-tracking-submit-footer">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default btn-lg" style="margin-right: 10px;">{{ __('govtracking::general.task.cancel') }}</a>
                    <button type="submit" class="btn btn-success btn-lg" style="font-weight: bold;"><i class="fa fa-check-circle"></i> {{ __('govtracking::general.task.create_task') }}</button>
                </div>
            </div>

        </form>
    </div>
</div>

@include('govtracking::tracking_codes.partials._task_data')
@stop
