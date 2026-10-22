@extends('layouts/default')
@section('title', __('geoview::general.monitor.title'))
@section('content')
<div class="gs-geo-monitor">
    <x-gs::page-header :title="__('geoview::general.monitor.title')" :subtitle="__('geoview::general.monitor.intro')" />
    @if ($current)
        @if (count($available) > 1)
            <form method="GET" class="gs-geo-monitor__choose" role="search">
                <label for="geo-monitor-map">{{ __('geoview::general.monitor.choose') }}</label>
                <select id="geo-monitor-map" name="map" class="form-control">
                    @foreach ($available as $key => $label)
                        <option value="{{ $key }}" @selected($key === $current)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="gs-btn">{{ __('geoview::general.monitor.show') }}</button>
            </form>
        @endif
        <x-geoview::map id="geo-monitor" :preset="$current" :label="$available[$current]" />
    @else
        <x-gs::empty-state :title="__('geoview::general.monitor.none')" />
    @endif
</div>
@endsection
