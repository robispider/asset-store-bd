@extends('layouts/default')
@section('title', 'Add Tracking Code (Task)')

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <form action="{{ route('gov.tracking.initiatives.tracking-codes.store', $initiative->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <!-- Context Header -->
            <div class="callout callout-info" style="background-color: #3c8dbc !important; border-color: #367fa9;">
                <h4><i class="fa fa-umbrella"></i> Initiative: {{ $initiative->title }}</h4>
                <p>Primary Funding Segment: <strong>{{ $initiative->primary_funding }} Budget</strong></p>
            </div>

            <!-- Section 1: Task Identity -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-aqua">1. Task Identity</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Tracking Code (Used in GRN)</label>
                            <input type="text" name="tracking_code" class="form-control input-lg" value="{{ old('tracking_code') }}" placeholder="e.g. ICT-2027-01" required>
                            <p class="help-block text-sm">Storekeepers type this exact code to authorize receipts.</p>
                        </div>
                        <div class="col-md-8 form-group">
                            <label>Task / Component Title</label>
                            <input type="text" name="task_title" class="form-control input-lg" value="{{ old('task_title') }}" placeholder="e.g. Supply of Equipment for Sylhet Zone" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Official Government Order / Memo (PDF)</label>
                        <input type="file" name="order_pdf" class="form-control" accept="application/pdf" {{ $initiative->require_documents ? 'required' : '' }}>
                        @if($initiative->require_documents)
                            <p class="help-block text-red"><i class="fa fa-exclamation-circle"></i> Official document upload is strictly required for this Initiative.</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Section 2: Fiscal & Budget Profile (DYNAMICALLY FILTERED FROM SYSTEM DICTIONARY) -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-orange">2. Fiscal & Budget Profile</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Fiscal Year</label>
                            <select name="fiscal_year" class="form-control select2" required>
                                <option value="">-- Select FY --</option>
                                <option value="2025-2026" {{ old('fiscal_year') == '2025-2026' ? 'selected' : '' }}>2025-2026</option>
                                <option value="2026-2027" {{ old('fiscal_year') == '2026-2027' ? 'selected' : '' }}>2026-2027</option>
                                <option value="2027-2028" {{ old('fiscal_year') == '2027-2028' ? 'selected' : '' }}>2027-2028</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Budget Main Segment</label>
                            <input type="text" class="form-control" value="{{ $initiative->primary_funding }}" disabled>
                            <input type="hidden" name="fund_main" value="{{ $initiative->primary_funding }}">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Dynamic Sub Fund Source</label>
                            <select name="funding_type_id" class="form-control" required>
                                <option value="">-- Select Sub-Source --</option>
                                @foreach($fundingTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('funding_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
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
                            <tr>
                                <td>
                                    <select name="targets[0][category_id]" class="form-control" required>
                                        <option value="">-- Select Category --</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="targets[0][planned_qty]" class="form-control" min="1" placeholder="e.g. 150" required>
                                </td>
                                <td>
                                    <input type="text" name="targets[0][economic_code]" class="form-control" placeholder="e.g. 4112202">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm remove-row" disabled><i class="fa fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-default btn-sm" id="add-target-row"><i class="fa fa-plus"></i> Add Another Category</button>
                </div>
            </div>

            <!-- Section 4: Execution Scope & Participating Offices -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-purple">4. Execution Scope & Governance</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Geographical Coverage</label>
                        <div class="radio">
                            <label>
                                <input type="radio" name="geo_override" value="Inherit" checked onchange="toggleGeoSelect()">
                                <strong>Inherit Umbrella Scope</strong> (Valid for entire Bangladesh)
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="geo_override" value="GeoArea" onchange="toggleGeoSelect()">
                                <strong>Override for this Task:</strong> Restrict to a specific Geographical Region
                            </label>
                        </div>
                        <div id="geo-select-group" style="display: none; margin-left: 20px; margin-top: 10px;">
                            <select name="geo_area_id" class="form-control select2" style="width: 50%;">
                                <option value="">-- Select Division / District / Upazila --</option>
                                @if(isset($geoAreas))
                                    @foreach($geoAreas as $area)
                                        <option value="{{ $area->GeoAreaId }}">{{ $area->en_name }} ({{ $area->geo_type }})</option>
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
                                <input type="radio" name="participant_override" value="Inherit" checked onchange="toggleParticipantSelect()">
                                <strong>Inherit Umbrella Scope</strong> (Only offices owned by our Ministry/Organization).
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="participant_override" value="CrossTenant" onchange="toggleParticipantSelect()">
                                <strong>Cross-Ministry Enabled:</strong> Allow ALL government offices in the permitted geographical coverage area.
                                <br><small class="text-muted" style="margin-left: 18px;">Useful for Central Procurement (e.g., ICT Div buying for Education Div).</small>
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="participant_override" value="SpecificLocations" onchange="toggleParticipantSelect()">
                                <strong>Specific Warehouses Only:</strong> Restrict explicitly to selected locations.
                            </label>
                        </div>
                        <div id="participant-select-group" style="display: none; margin-left: 20px; margin-top: 10px;">
                            <select name="specific_location_ids[]" class="form-control select2" multiple="multiple" data-placeholder="-- Select Specific Offices --" style="width: 100%;">
                                @if(isset($locations))
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="box-footer text-right" style="background-color: #f4f4f4;">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default btn-lg" style="margin-right: 10px;">Cancel</a>
                    <button type="submit" class="btn btn-success btn-lg"><i class="fa fa-check-circle"></i> Generate Tracking Code</button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let targetIndex = 1;
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
        
        toggleGeoSelect();
        toggleParticipantSelect();
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