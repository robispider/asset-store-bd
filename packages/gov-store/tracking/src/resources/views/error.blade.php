@extends('layouts/default')
@section('title', __('govtracking::general.module_title'))
@section('content')
<div class="alert alert-danger" role="alert">{{ $message }}</div>
<a href="{{ route('gov.tracking.initiatives.index') }}" class="btn btn-default">{{ __('govtracking::general.active_initiatives') }}</a>
@stop
