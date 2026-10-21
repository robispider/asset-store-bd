@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_create_task', ['title' => $initiative->title]))

@section('content')
@php
    // Defensive initializations to support the reuse of edit partials inside the creation flow
    $trackingCode = null;
    $activeGeo = null;
    $activePart = null;
    $activeLocationIds = [];
@endphp

<div class="gs-tracking-form-page">
    <x-gs::page-header :title="__('govtracking::general.crumbs.new_code')" :subtitle="$initiative->title" />

    @include('govtracking::tracking_codes.partials._errors')

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

        <div class="gs-tracking-form-actions">
            <x-gs::button tone="secondary" outline :href="route('gov.tracking.initiatives.manage', $initiative->id)">{{ __('govtracking::general.task.cancel') }}</x-gs::button>
            <x-gs::button type="submit" tone="success" icon="fa-circle-check">{{ __('govtracking::general.task.create_task') }}</x-gs::button>
        </div>
    </form>
</div>

@include('govtracking::tracking_codes.partials._task_data')
@stop
