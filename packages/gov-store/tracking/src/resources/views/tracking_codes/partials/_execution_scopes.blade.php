@php
    $geoVal = isset($activeGeo) ? $activeGeo->target_type : 'Inherit';
    $partVal = isset($activePart) ? $activePart->target_type : 'Inherit';
    $bnAreas = str_starts_with(app()->getLocale(), 'bn');
@endphp

<x-gs::box :title="__('govtracking::general.task.execution')">
    <!-- Geographical Coverage (Enforced across all levels) -->
    <fieldset class="form-group gs-tracking-fieldset">
        <legend class="gs-tracking-legend">{{ __('govtracking::general.task.geography') }}</legend>
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
        {{-- task-form.js toggles this group; the inline display is its initial state. --}}
        <div id="geo-select-group" class="gs-tracking-indent" @if($geoVal !== 'GeoArea') style="display: none;" @endif>
            <label class="gs-sr-only" for="geo_area_id">{{ __('govtracking::general.task.select_region') }}</label>
            {{-- task-form.js searches areas over gov.geo.search; only the chosen area is rendered here. --}}
            <select name="geo_area_id" id="geo_area_id" class="form-control gs-tracking-half"
                    @if(Route::has('gov.geo.search')) data-geo-search="{{ route('gov.geo.search') }}" @endif>
                <option value="">{{ __('govtracking::general.task.select_region') }}</option>
                @if(isset($geoAreas))
                    @foreach($geoAreas as $area)
                        <option value="{{ $area->GeoAreaId }}" selected>{{ ($bnAreas ? ($area->bn_name ?? null) : null) ?: $area->en_name }} ({{ $area->geo_type }})</option>
                    @endforeach
                @endif
            </select>
        </div>
    </fieldset>

    <!-- Participating Offices -->
    <fieldset class="form-group gs-tracking-fieldset">
        <legend class="gs-tracking-legend">{{ __('govtracking::general.task.participants') }}</legend>
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
    </fieldset>
</x-gs::box>
