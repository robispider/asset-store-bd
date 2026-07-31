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
                    <button type="submit" class="btn btn-warning btn-lg"><i class="fa fa-check-circle"></i> Save Task Changes</button>
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Import Central JS Controller -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        toggleSpecificityPanels();
        toggleGeoSelect();
        if (document.querySelector('input[name="participant_override"]')) {
            toggleParticipantSelect();
        }

        // --- FIXED (Defensive Row-Adder): Only runs if the Level 2 panel is active ---
        const body = document.getElementById("targets-body");
        if (body) {
            // Dynamically calculate the starting index to support pre-populations
            let targetIndex = body.querySelectorAll('tr').length;
            const firstSelect = document.querySelector('.target-category-select');
            const optionsHtml = firstSelect ? firstSelect.innerHTML : '';

            document.getElementById("add-target-row").addEventListener("click", function() {
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td><select name="targets[${targetIndex}][category_id]" class="form-control target-category-select" required>${optionsHtml}</select></td>
                    <td><input type="number" name="targets[${targetIndex}][planned_qty]" class="form-control target-qty-input" min="1" required></td>
                    <td><input type="text" name="targets[${targetIndex}][economic_code]" class="form-control" placeholder="e.g. 4112202"></td>
                    <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button></td>
                `;
                body.appendChild(tr);
                targetIndex++;
            });

            body.addEventListener("click", function(e) {
                if(e.target.closest(".remove-row")) {
                    e.target.closest("tr").remove();
                }
            });
        }
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
            if (catSelects.length > 0) {
                catSelects.forEach(el => el.required = true);
                qtyInputs.forEach(el => el.required = true);
            }
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
    
    function toggleParticipantSelect() {
        const val = document.querySelector('input[name="participant_override"]:checked').value;
        document.getElementById('participant-select-group').style.display = (val === 'SpecificLocations') ? 'block' : 'none';
    }
</script>
@stop