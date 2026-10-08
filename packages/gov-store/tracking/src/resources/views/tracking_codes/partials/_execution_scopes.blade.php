@php
    $geoVal = isset($activeGeo) ? $activeGeo->target_type : 'Inherit';
    $partVal = isset($activePart) ? $activePart->target_type : 'Inherit';
@endphp

<div class="box box-solid">
    <div class="box-header with-border"><h3 class="box-title text-purple">{{ __('govtracking::general.task.execution') }}</h3></div>
    <div class="box-body">

        <!-- Geographical Coverage (Enforced across all levels) -->
        <div class="form-group">
            <label>{{ __('govtracking::general.task.geography') }}</label>
            <div class="radio">
                <label>
                    <input type="radio" name="geo_override" value="Inherit" {{ $geoVal === 'Inherit' ? 'checked' : '' }}>
                    <strong>{{ __('govtracking::general.task.inherit') }}</strong> {{ __('govtracking::general.task.country') }}
                </label>
            </div>
            <div class="radio">
                <label>
                    <input type="radio" name="geo_override" value="GeoArea" {{ $geoVal === 'GeoArea' ? 'checked' : '' }}>
                    <strong>{{ __('govtracking::general.task.override') }}</strong> {{ __('govtracking::general.task.restrict_region') }}
                </label>
            </div>
            <div id="geo-select-group" style="{{ $geoVal === 'GeoArea' ? 'display: block;' : 'display: none;' }} margin-left: 20px; margin-top: 10px;">
                <select name="geo_area_id" id="geo_area_id" class="form-control select2" style="width: 50%;">
                    <option value="">{{ __('govtracking::general.task.select_region') }}</option>
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
            <label>{{ __('govtracking::general.task.participants') }}</label>
            <div class="radio">
                <label>
                    <input type="radio" name="participant_override" value="Inherit" {{ $partVal === 'Inherit' ? 'checked' : '' }}>
                    <strong>{{ __('govtracking::general.task.inherit') }}</strong> {{ __('govtracking::general.task.own_offices') }}
                </label>
            </div>
            <div class="radio">
                <label>
                    <input type="radio" name="participant_override" value="CrossTenant" {{ $partVal === 'CrossTenant' ? 'checked' : '' }}>
                    <strong>{{ __('govtracking::general.task.cross_enabled') }}</strong> {{ __('govtracking::general.task.all_offices') }}
                </label>
            </div>
        </div>

    </div>
</div>
