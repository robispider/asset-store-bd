@extends('layouts/default')
@section('title', 'Modify Task - ' . $trackingCode->tracking_code)

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        
        <!-- Programme Context Path Indicator -->
        <div style="margin-bottom: 20px; padding: 10px 15px; background-color: #fff; border: 1px solid #cbd5e1; border-radius: 4px;">
            <ol class="breadcrumb" style="background: transparent; margin: 0; padding: 0;">
                <li><a href="{{ route('gov.tracking.initiatives.index') }}" class="text-muted"><i class="fa fa-briefcase"></i> Programmes</a></li>
                <li><a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="text-blue"><strong>{{ $initiative->title }}</strong></a></li>
                <li><a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="text-muted">Tasks</a></li>
                <li class="active text-muted">Modify Parameters ({{ $trackingCode->tracking_code }})</li>
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
            <div class="box box-solid bg-gray-light" style="border-left: 4px solid #3c8dbc; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="box-body" style="padding: 15px 20px;">
                    <span style="font-size: 15px; color: #475569; display: block;">
                        <i class="fa fa-lock text-muted" style="margin-right: 5px;"></i> <strong>Delivery Management Strategy:</strong>
                        @if($trackingCode->specificity_level === '1_BLANKET')
                            <span class="label label-default" style="font-size: 11px; margin-left: 5px;">OPEN ALLOCATION</span>
                        @elseif($trackingCode->specificity_level === '2_CATEGORY')
                            <span class="label label-success" style="font-size: 11px; margin-left: 5px;">CATEGORY TARGETS</span>
                        @elseif($trackingCode->specificity_level === '3_MATRIX')
                            <span class="label label-primary" style="font-size: 11px; margin-left: 5px;">OFFICE DELIVERY SCHEDULE</span>
                        @endif
                    </span>
                    <span class="help-block text-muted" style="margin-top: 5px; margin-bottom: 0; font-size: 11px; line-height: 1.4;">
                        This setting is locked and cannot be changed. Active inventories and programmatic registries are tied directly to this structural logic.
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
            <div class="box box-solid" style="border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 40px;">
                <div class="box-footer text-right" style="background-color: #f8fafc; padding: 15px 20px;">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default btn-lg" style="margin-right: 10px;">Cancel</a>
                    <button type="submit" class="btn btn-warning btn-lg" style="font-weight: bold;"><i class="fa fa-check-circle"></i> Save Changes</button>
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Parent Controller Script Block -->
<script>
    (function() {
        function initEditFlowCoordinator() {
            // Polling check to ensure jQuery and Select2 are fully loaded before executing core UI controllers
            if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
                setTimeout(initEditFlowCoordinator, 50);
                return;
            }

            window.jQuery(function($) {
                // Initialize default Select2 dropdown components
                $('.select2').select2();

                // Robust jQuery Event Delegation instead of fragile inline onchange attributes
                $(document).on('change', 'input[name="geo_override"]', function() {
                    toggleGeoSelect();
                });

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
                var targetIndex = {{ $trackingCode->specificity_level === '2_CATEGORY' ? $trackingCode->targets->count() : 1 }};
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

                // Boot spreadsheet engine dynamically if the active edit strategy is Level 3
                const activeStrategy = "{{ $trackingCode->specificity_level }}";
                if (activeStrategy === '3_MATRIX') {
                    if (typeof window.initMatrixSpawner === 'function') {
                        window.initMatrixSpawner();
                    }
                }

                toggleTrashButtons();

                // Set explicit element style block for initial load of geographical selectors
                const initialGeoVal = $('input[name="geo_override"]:checked').val();
                document.getElementById('geo-select-group').style.display = (initialGeoVal === 'GeoArea') ? 'block' : 'none';
            });
        }

        initEditFlowCoordinator();
    })();
</script>
@stop