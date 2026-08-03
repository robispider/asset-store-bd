@php
    $geoVal = isset($activeGeo) ? $activeGeo->target_type : 'Inherit';
    $partVal = isset($activePart) ? $activePart->target_type : 'Inherit';
@endphp

<div class="box box-solid">
    <div class="box-header with-border"><h3 class="box-title text-purple">2. Where can this task operate?</h3></div>
    <div class="box-body">
        
        <!-- Geographical Coverage (Enforced across all levels) -->
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
                <select name="geo_area_id" id="geo_area_id" class="form-control select2" style="width: 50%;">
                    <option value="">-- Select Division / District / Upazila --</option>
                    @if(isset($geoAreas))
                        @foreach($geoAreas as $area)
                            <option value="{{ $area->GeoAreaId }}" {{ (isset($activeGeo) && $activeGeo->target_id == $area->GeoAreaId) ? 'selected' : '' }}>{{ $area->en_name }} ({{ $area->geo_type }})</option>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>

        <hr>

        <!-- Participating Offices -->
        <div class="form-group">
            <label>Participating Offices (Who can receive assets under this code?)</label>
            <div class="radio">
                <label>
                    <input type="radio" name="participant_override" value="Inherit" {{ $partVal === 'Inherit' ? 'checked' : '' }}>
                    <strong>Inherit Umbrella Scope</strong> (Only offices owned by our Ministry/Organization).
                </label>
            </div>
            <div class="radio">
                <label>
                    <input type="radio" name="participant_override" value="CrossTenant" {{ $partVal === 'CrossTenant' ? 'checked' : '' }}>
                    <strong>Cross-Ministry Enabled:</strong> Allow ALL government offices in the permitted geographical coverage area.
                </label>
            </div>
        </div>

    </div>
</div>