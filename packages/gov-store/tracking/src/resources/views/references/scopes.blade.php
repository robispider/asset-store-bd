@extends('layouts/default')

@section('title', 'Scope Boundaries: ' . $reference->reference_code)

@section('content')
<div class="row">
    <div class="col-md-5">
        <!-- Scope Rule Configuration -->
        <form action="{{ route('gov.tracking.references.scopes.store', $reference->id) }}" method="POST">
            @csrf
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Configure Scope Boundary Rule</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Scope Dimension</label>
                        <select name="dimension" class="form-control" required>
                            <option value="OWNERSHIP">OWNERSHIP (Governing Ministry)</option>
                            <option value="VISIBILITY">VISIBILITY (Who can select this reference)</option>
                            <option value="APPLICABILITY">APPLICABILITY (Physical delivery boundaries)</option>
                            <option value="ADMINISTRATION">ADMINISTRATION (Edit rights over rules)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Target Class Type</label>
                        <select name="target_type" id="target_type" class="form-control" required onchange="toggleScopeInputs()">
                            <option value="Global">Platform-Wide (Global)</option>
                            <option value="Company">Ministry (Company)</option>
                            <option value="Location">Specific Office (Location)</option>
                            @if(count($geoAreas) > 0)
                                <option value="GeoArea">Geographical Region (GeoArea)</option>
                            @endif
                        </select>
                    </div>

                    <!-- Target Value Selections -->
                    <div class="form-group" id="company_group" style="display:none;">
                        <label>Select Ministry</label>
                        <select name="target_id_company" class="form-control target-field" disabled>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" id="location_group" style="display:none;">
                        <label>Select Office Location</label>
                        <select name="target_id_location" class="form-control target-field" disabled>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if(count($geoAreas) > 0)
                        <div class="form-group" id="geo_group" style="display:none;">
                            <label>Select Geographic Area</label>
                            <select name="target_id_geo" class="form-control target-field" disabled>
                                @foreach($geoAreas as $area)
                                    <option value="{{ $area->GeoAreaId }}">{{ $area->en_name }} ({{ $area->geo_type }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <input type="hidden" name="target_id" id="target_id">
                </div>
                <div class="box-footer text-right">
                    <a href="{{ route('gov.tracking.references.show', $reference->id) }}" class="btn btn-default">Back</a>
                    <button type="submit" class="btn btn-primary" onclick="syncTargetId()">Enforce Rule</button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-md-7">
        <!-- Active Boundaries -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Active Boundary Rules</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Dimension</th>
                            <th>Scope Rule Target</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reference->scopes as $scope)
                            <tr>
                                <td><strong>{{ $scope->dimension }}</strong></td>
                                <td>
                                    <span class="label label-default">{{ $scope->target_type }}</span> 
                                    <code>{{ $scope->target_display_name }}</code>
                                </td>
                                <td class="text-right">
                                    <form action="{{ route('gov.tracking.references.scopes.destroy', [$reference->id, $scope->id]) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Decommission this operational boundary rule?')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No explicit operational boundary rules are mapped to this reference.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleScopeInputs() {
        const type = document.getElementById('target_type').value;
        
        // Hide and disable all
        document.querySelectorAll('.target-field').forEach(el => {
            el.disabled = true;
            el.closest('.form-group').style.display = 'none';
        });

        // Enable matching group
        if (type === 'Company') {
            const group = document.getElementById('company_group');
            group.style.display = 'block';
            group.querySelector('select').disabled = false;
        } else if (type === 'Location') {
            const group = document.getElementById('location_group');
            group.style.display = 'block';
            group.querySelector('select').disabled = false;
        } else if (type === 'GeoArea') {
            const group = document.getElementById('geo_group');
            if (group) {
                group.style.display = 'block';
                group.querySelector('select').disabled = false;
            }
        }
    }

    function syncTargetId() {
        const type = document.getElementById('target_type').value;
        let activeSelect = null;

        if (type === 'Company') {
            activeSelect = document.querySelector('select[name="target_id_company"]');
        } else if (type === 'Location') {
            activeSelect = document.querySelector('select[name="target_id_location"]');
        } else if (type === 'GeoArea') {
            activeSelect = document.querySelector('select[name="target_id_geo"]');
        }

        document.getElementById('target_id').value = activeSelect ? activeSelect.value : '';
    }

    // Trigger initial state
    document.addEventListener("DOMContentLoaded", toggleScopeInputs);
</script>
@stop
