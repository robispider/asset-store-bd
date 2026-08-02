@extends('layouts/default')
@section('title', 'Edit Tracking Code (Task)')

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <form action="{{ route('gov.tracking.initiatives.tracking-codes.update', [$initiative->id, $trackingCode->id]) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            
            <!-- Context Header -->
            <div class="callout callout-warning">
                <h4><i class="fa fa-pencil"></i> Editing Task Properties: {{ $trackingCode->tracking_code }}</h4>
                <p>This tracking code is currently in a DRAFT state. You can safely modify targets, economic sub-ledgers, and execution scopes before turning it active.</p>
            </div>

            <!-- Include Reusable Components (Edit Context Detected automatically via Blade) -->
            @include('govtracking::tracking_codes.partials._task_identity')
            @include('govtracking::tracking_codes.partials._fiscal_profile')

            <!-- Immutable Specificity Indicator Badge -->
            <div class="box box-solid bg-gray-light">
                <div class="box-body">
                    <span class="lead"><strong>Task Specificity Type:</strong> 
                        <span class="label label-primary">{{ str_replace('_', ' ', $trackingCode->specificity_level) }}</span>
                    </span>
                    <input type="hidden" name="specificity_level" value="{{ $trackingCode->specificity_level }}">
                </div>
            </div>

            <!-- Include Active Specificity Panel Only -->
            @if($trackingCode->specificity_level === '1_BLANKET')
                @include('govtracking::tracking_codes.partials._level1_blanket')
            @elseif($trackingCode->specificity_level === '2_CATEGORY')
                @include('govtracking::tracking_codes.partials._level2_category_list')
            @elseif($trackingCode->specificity_level === '3_MATRIX')
                @include('govtracking::tracking_codes.partials._level3_matrix_grid')
            @endif
            
            @include('govtracking::tracking_codes.partials._execution_scopes')

            <!-- Submit Footer -->
            <div class="box box-solid">
                <div class="box-footer text-right" style="background-color: #f4f4f4;">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default btn-lg" style="margin-right: 10px;">Cancel</a>
                    <button type="submit" class="btn btn-warning btn-lg"><i class="fa fa-check-circle"></i> Save Task Changes</button>
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Import Central JS Controller (Clean of Obsolete References) -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        toggleGeoSelect();
    });

    function toggleGeoSelect() {
        const val = document.querySelector('input[name="geo_override"]:checked').value;
        document.getElementById('geo-select-group').style.display = (val === 'GeoArea') ? 'block' : 'none';
    }
</script>
@stop