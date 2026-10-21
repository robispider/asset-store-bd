@extends('layouts/default')
@section('title', __('govtracking::general.module_title'))
@section('content')
<div class="gs-tracking-form-page">
    <x-gs::alert tone="danger">{{ $message }}</x-gs::alert>
    <x-gs::button tone="secondary" outline :href="route('gov.tracking.initiatives.index')">{{ __('govtracking::general.active_initiatives') }}</x-gs::button>
</div>
@stop
