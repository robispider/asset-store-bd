@extends('layouts/default')
@section('title', 'Add Tracking Code (Task)')

@section('content')
@php
    // Defensive placeholder initializations to allow seamless reuse of edit partials on creation flow
    $trackingCode = null;
    $activeGeo = null;
    $activePart = null;
    $activeLocationIds = [];
@endphp

<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <form action="{{ route('gov.tracking.initiatives.tracking-codes.store', $initiative->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <!-- Context Header -->
            <div class="callout callout-info" style="background-color: #3c8dbc !important; border-color: #367fa9;">
                <h4><i class="fa fa-umbrella"></i> Initiative: {{ $initiative->title }}</h4>
                <p>Primary Funding Segment: <strong>{{ $initiative->primary_funding }} Budget</strong></p>
            </div>

            <!-- Include Reusable Components -->
            @include('govtracking::tracking_codes.partials._task_identity')
            @include('govtracking::tracking_codes.partials._fiscal_profile')
            @include('govtracking::tracking_codes.partials._specificity_selector')
            
            @include('govtracking::tracking_codes.partials._level1_blanket')
            @include('govtracking::tracking_codes.partials._level2_category_list')
            @include('govtracking::tracking_codes.partials._level3_matrix_grid')
            
            @include('govtracking::tracking_codes.partials._execution_scopes')

            <!-- Submit Footer -->
            <div class="box box-solid">
                <div class="box-footer text-right" style="background-color: #f4f4f4;">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default btn-lg" style="margin-right: 10px;">Cancel</a>
                    <button type="submit" class="btn btn-success btn-lg"><i class="fa fa-check-circle"></i> Generate Tracking Code</button>
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Import Central JS Controller (Clean of Obsolete References) -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        toggleSpecificityPanels();
        toggleGeoSelect();
    });

    function toggleSpecificityPanels() {
        const level = document.querySelector('input[name="specificity_level"]:checked').value;
        const p1 = document.getElementById('panel-level1');
        const p2 = document.getElementById('panel-level2');
        const p3 = document.getElementById('panel-level3');
        
        const catSelects = document.querySelectorAll('.target-category-select');
        const qtyInputs = document.querySelectorAll('.target-qty-input');

        p1.style.display = 'none';
        p2.style.display = 'none';
        p3.style.display = 'none';

        if (level === '1_BLANKET') {
            p1.style.display = 'block';
            catSelects.forEach(el => el.required = false);
            qtyInputs.forEach(el => el.required = false);
        } else if (level === '2_CATEGORY') {
            p2.style.display = 'block';
            catSelects.forEach(el => el.required = true);
            qtyInputs.forEach(el => el.required = true);
        } else if (level === '3_MATRIX') {
            p3.style.display = 'block';
            catSelects.forEach(el => el.required = false);
            qtyInputs.forEach(el => el.required = false);
        }
    }

    function toggleGeoSelect() {
        const val = document.querySelector('input[name="geo_override"]:checked').value;
        document.getElementById('geo-select-group').style.display = (val === 'GeoArea') ? 'block' : 'none';
    }
</script>
@stop