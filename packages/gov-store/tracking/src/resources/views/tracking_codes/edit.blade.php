@extends('layouts/default')
@section('title', 'Edit Tracking Code (Task)')

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <form action="{{ route('gov.tracking.initiatives.tracking-codes.update', [$initiative->id, $trackingCode->id]) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            
            <div class="callout callout-warning">
                <h4><i class="fa fa-pencil"></i> Editing Task Properties: {{ $trackingCode->tracking_code }}</h4>
                <p>This tracking code is currently in a DRAFT state. You can safely modify targets, economic sub-ledgers, and execution scopes before turning it active.</p>
            </div>

            <!-- Section 1: Task Identity -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-aqua">1. Task Identity</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Tracking Code (Immutable ID)</label>
                            <input type="text" class="form-control input-lg" value="{{ $trackingCode->tracking_code }}" disabled>
                        </div>
                        <div class="col-md-8 form-group">
                            <label>Task / Component Title</label>
                            <input type="text" name="task_title" class="form-control input-lg" value="{{ old('task_title', $trackingCode->task_title) }}" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Fiscal & Budget Profile -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-orange">2. Fiscal & Budget Profile</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Fiscal Year</label>
                            <select name="fiscal_year" class="form-control select2" required>
                                <option value="2025-2026" {{ old('fiscal_year', $trackingCode->fiscal_year) == '2025-2026' ? 'selected' : '' }}>2025-2026</option>
                                <option value="2026-2027" {{ old('fiscal_year', $trackingCode->fiscal_year) == '2026-2027' ? 'selected' : '' }}>2026-2027</option>
                                <option value="2027-2028" {{ old('fiscal_year', $trackingCode->fiscal_year) == '2027-2028' ? 'selected' : '' }}>2027-2028</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Budget Main Segment</label>
                            <input type="text" class="form-control" value="{{ $initiative->primary_funding }}" disabled>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Dynamic Sub Fund Source</label>
                            <select name="funding_type_id" class="form-control" required>
                                @foreach($fundingTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('funding_type_id', $trackingCode->funding_type_id) == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Quantitative Goals & Economic Codes -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-green">3. Quantitative Targets (Line Items)</h3></div>
                <div class="box-body">
                    <p class="text-muted">Define the specific items authorized by this code, and their iBAS++ Economic Classification.</p>
                    
                    <table class="table table-bordered table-striped" id="targets-table">
                        <thead>
                            <tr>
                                <th>Asset Category (Mandatory)</th>
                                <th>Planned Quantity (Mandatory)</th>
                                <th>Economic Code (Optional)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="targets-body">
                            @foreach($trackingCode->targets as $index => $activeTarget)
                                <tr>
                                    <td>
                                        <select name="targets[{{ $index }}][category_id]" class="form-control" required>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}" {{ $activeTarget->category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="targets[{{ $index }}][planned_qty]" class="form-control" value="{{ $activeTarget->planned_qty }}" min="1" required>
                                    </td>
                                    <td>
                                        <input type="text" name="targets[{{ $index }}][economic_code]" class="form-control" value="{{ $activeTarget->economic_code }}" placeholder="e.g. 4112202">
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm remove-row" {{ $index === 0 ? 'disabled' : '' }}><i class="fa fa-trash"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-default btn-sm" id="add-target-row"><i class="fa fa-plus"></i> Add Another Category</button>
                </div>
            </div>

            <!-- Section 4: Execution Scope & Participating Offices -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-purple">4. Execution Scope & Governance</h3></div>
                <div class="box-body">
                    @php
                        $geoVal = $activeGeo ? $activeGeo->target_type : 'Inherit';
                        $partVal = $activePart ? $activePart->target_type : 'Inherit';
                    @endphp
                    
                    <div class="form-group">
                        <label>Geographical Coverage</label>
                        <div class="radio">
                            <label>
                                <input type="radio" name="geo_override" value="Inherit" {{ $geoVal === 'Inherit' ? 'checked' : '' }} onchange="toggleGeoSelect()">
                                <strong>Inherit Umbrella Scope</strong> (Valid for entire Bangladesh)
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="geo_override" value="GeoArea" {{ $geoVal === 'GeoArea' ? 'checked' : '' }} onchange="toggleGeoSelect()">
                                <strong>Override for this Task:</strong> Restrict to a specific Geographical Region
                            </label>
                        </div>
                        <div id="geo-select-group" style="{{ $geoVal === 'GeoArea' ? 'display: block;' : 'display: none;' }} margin-left: 20px; margin-top: 10px;">
                            <select name="geo_area_id" class="form-control select2" style="width: 50%;">
                                <option value="">-- Select Division / District / Upazila --</option>
                                @if(isset($geoAreas))
                                    @foreach($geoAreas as $area)
                                        <option value="{{ $area->GeoAreaId }}" {{ ($activeGeo && $activeGeo->target_id == $area->GeoAreaId) ? 'selected' : '' }}>{{ $area->en_name }} ({{ $area->geo_type }})</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Participating Offices (Who can receive assets under this code?)</label>
                        <div class="radio">
                            <label>
                                <input type="radio" name="participant_override" value="Inherit" {{ $partVal === 'Inherit' ? 'checked' : '' }} onchange="toggleParticipantSelect()">
                                <strong>Inherit Umbrella Scope</strong> (Only offices owned by our Ministry/Organization).
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="participant_override" value="CrossTenant" {{ $partVal === 'CrossTenant' ? 'checked' : '' }} onchange="toggleParticipantSelect()">
                                <strong>Cross-Ministry Enabled:</strong> Allow ALL government offices in the permitted geographical coverage area.
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="participant_override" value="SpecificLocations" {{ $partVal === 'SpecificLocations' ? 'checked' : '' }} onchange="toggleParticipantSelect()">
                                <strong>Specific Warehouses Only:</strong> Restrict explicitly to selected locations.
                            </label>
                        </div>
                        <div id="participant-select-group" style="{{ $partVal === 'SpecificLocations' ? 'display: block;' : 'display: none;' }} margin-left: 20px; margin-top: 10px;">
                            <select name="specific_location_ids[]" class="form-control select2" multiple="multiple" data-placeholder="-- Select Specific Offices --" style="width: 100%;">
                                @if(isset($locations))
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}" {{ in_array($location->id, $activeLocationIds) ? 'selected' : '' }}>{{ $location->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="box-footer text-right" style="background-color: #f4f4f4;">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default btn-lg" style="margin-right: 10px;">Cancel</a>
                    <button type="submit" class="btn btn-warning btn-lg"><i class="fa fa-check-circle"></i> Save Task Changes</button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let targetIndex = {{ count($trackingCode->targets) }};
        const body = document.getElementById("targets-body");
        const optionsHtml = document.querySelector('select[name="targets[0][category_id]"]').innerHTML;

        document.getElementById("add-target-row").addEventListener("click", function() {
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td><select name="targets[${targetIndex}][category_id]" class="form-control" required>${optionsHtml}</select></td>
                <td><input type="number" name="targets[${targetIndex}][planned_qty]" class="form-control" min="1" required></td>
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
    });

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