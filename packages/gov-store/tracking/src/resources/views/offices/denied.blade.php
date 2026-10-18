@extends('layouts/default')
@section('title', __('govtracking::general.monitor.office_denied'))
@section('content')
<div class="gs-tracking-monitor"><x-gs::status-callout status="warning" :verdict="__('govtracking::general.monitor.office_denied')" :next="__('govtracking::general.monitor.programme_required')" />
    <nav class="gs-level-switch" aria-label="{{ __('govtracking::general.monitor.offices') }}">@foreach($ownOffices as $office)<a href="{{ route('gov.tracking.offices.show',$office->id) }}">{{ $office->name }}</a>@endforeach</nav>
</div>
@endsection
