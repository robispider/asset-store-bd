@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_modify_task', ['code' => $trackingCode->tracking_code]))

@section('content')
@php
    $strategy = [
        '1_BLANKET' => ['tone' => 'secondary', 'label' => 'open_allocation'],
        '2_CATEGORY' => ['tone' => 'success', 'label' => 'category_targets'],
        '3_MATRIX' => ['tone' => 'primary', 'label' => 'office_schedule'],
    ][$trackingCode->specificity_level] ?? null;
@endphp
<div class="gs-tracking-form-page">
    <x-gs::page-header :title="$trackingCode->tracking_code" :subtitle="$trackingCode->task_title" />

    @include('govtracking::tracking_codes.partials._errors')

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
        <x-gs::box class="gs-tracking-strategy-box">
            <p class="gs-tracking-strategy-text">
                <i class="fas fa-lock gs-muted" aria-hidden="true"></i> <strong>{{ __('govtracking::general.task.strategy') }}</strong>
                @if($strategy)<x-gs::badge :tone="$strategy['tone']">{{ __('govtracking::general.task.'.$strategy['label']) }}</x-gs::badge>@endif
            </p>
            <p class="gs-tracking-help">{{ __('govtracking::general.task.strategy_locked') }}</p>
            <input type="hidden" name="specificity_level" value="{{ $trackingCode->specificity_level }}">
        </x-gs::box>

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

        <div class="gs-tracking-form-actions">
            <x-gs::button tone="secondary" outline :href="route('gov.tracking.initiatives.tracking-codes.show', [$initiative->id, $trackingCode->id])">{{ __('govtracking::general.task.cancel') }}</x-gs::button>
            <x-gs::button type="submit" tone="primary" icon="fa-circle-check">{{ __('govtracking::general.task.save') }}</x-gs::button>
        </div>
    </form>
    @include('govtracking::tracking_codes.partials._documents')
</div>

@include('govtracking::tracking_codes.partials._task_data')
@stop
