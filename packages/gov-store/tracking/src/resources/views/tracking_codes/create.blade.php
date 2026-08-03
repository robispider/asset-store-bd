@extends('layouts/default')
@section('title', 'Create Task - ' . $initiative->title)

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
        <div style="margin-bottom: 20px; padding: 10px 15px; background-color: #fff; border: 1px solid #cbd5e1; border-radius: 4px;">
            <ol class="breadcrumb" style="background: transparent; margin: 0; padding: 0;">
                <li><a href="{{ route('gov.tracking.initiatives.index') }}" class="text-muted"><i class="fa fa-briefcase"></i> Programmes</a></li>
                <li><a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="text-blue"><strong>{{ $initiative->title }}</strong></a></li>
                <li class="active text-muted">Create Task</li>
            </ol>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <strong>Please check your parameters:</strong>
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
            <div class="box box-solid" style="border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 40px;">
                <div class="box-footer text-right" style="background-color: #f8fafc; padding: 15px 20px;">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default btn-lg" style="margin-right: 10px;">Cancel</a>
                    <button type="submit" class="btn btn-success btn-lg" style="font-weight: bold;"><i class="fa fa-check-circle"></i> Create Task</button>
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Parent Controller Script Block -->
<script>
    (function() {
        function initCreateFlowCoordinator() {
            // Polling check to ensure jQuery and Select2 are fully loaded before executing core UI controllers
            if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
                setTimeout(initCreateFlowCoordinator, 50);
                return;
            }

            window.jQuery(function($) {
                // Initialize default Select2 dropdown components
                $('.select2').select2();

                // Robust jQuery Event Delegation instead of fragile inline onchange attributes
                $(document).on('change', 'input[name="specificity_level"]', function() {
                    toggleSpecificityPanels();
                });

                $(document).on('change', 'input[name="geo_override"]', function() {
                    toggleGeoSelect();
                });

                window.toggleSpecificityPanels = function() {
                    const level = $('input[name="specificity_level"]:checked').val();
                    const p1 = document.getElementById('panel-level1');
                    const p2 = document.getElementById('panel-level2');
                    const p3 = document.getElementById('panel-level3');
                    
                    const catSelects = document.querySelectorAll('.target-category-select');
                    const qtyInputs = document.querySelectorAll('.target-qty-input');

                    // Reset display visibility states
                    p1.style.display = 'none';
                    p2.style.display = 'none';
                    p3.style.display = 'none';

                    // Update constraints & dynamic inputs requirements
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
                        
                        // Fire spreadsheet boots if loaded
                        if (typeof window.initMatrixSpawner === 'function') {
                            window.initMatrixSpawner();
                        }
                    }
                };

                window.toggleGeoSelect = function() {
                    const val = $('input[name="geo_override"]:checked').val();
                    const geoSelectGroup = document.getElementById('geo-select-group');
                    const $geoSelect = $('select[name="geo_area_id"]');
                    
                    if (val === 'GeoArea') {
                        geoSelectGroup.style.display = 'block';
                        $geoSelect.select2('open');
                    } else {
                        geoSelectGroup.style.display = 'none';
                        $geoSelect.val('').trigger('change');
                    }
                };

                // Dynamic targets row multiplier (Level 2 targets table)
                var targetIndex = {{ count(old('targets', [[]])) }};
                $(document).on('click', '#add-target-row', function() {
                    var $tbody = $('#targets-body');
                    var template = `
                        <tr>
                            <td>
                                <select name="targets[${targetIndex}][category_id]" class="form-control target-category-select select2-dynamic" style="width: 100%;" required>
                                    <option value="">-- Select Category --</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" name="targets[${targetIndex}][planned_qty]" class="form-control target-qty-input" min="1" placeholder="e.g. 150" required>
                            </td>
                            <td>
                                <input type="text" name="targets[${targetIndex}][economic_code]" class="form-control" placeholder="e.g. 4112202">
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>
                    `;
                    var $newRow = $(template);
                    $tbody.append($newRow);
                    
                    // Initialize Select2 specifically for newly dynamically spawned dropdowns
                    $newRow.find('.select2-dynamic').select2();
                    
                    targetIndex++;
                    toggleTrashButtons();
                });

                // Row Removal delegation
                $(document).on('click', '#targets-body .remove-row', function() {
                    $(this).closest('tr').remove();
                    toggleTrashButtons();
                });

                function toggleTrashButtons() {
                    var rows = $('#targets-body tr');
                    if (rows.length <= 1) {
                        rows.find('.remove-row').prop('disabled', true);
                    } else {
                        rows.find('.remove-row').prop('disabled', false);
                    }
                }

                // Run Initializers on ready states
                window.toggleSpecificityPanels();
                toggleTrashButtons();
                
                // Set explicit element style block for initial load of geographical selectors
                const initialGeoVal = $('input[name="geo_override"]:checked').val();
                document.getElementById('geo-select-group').style.display = (initialGeoVal === 'GeoArea') ? 'block' : 'none';
            });
        }

        initCreateFlowCoordinator();
    })();
</script>
@stop