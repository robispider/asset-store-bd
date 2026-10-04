@extends('layouts/default')
@section('title', __('tenantops::access.title'))
@section('content')
<div class="alert alert-danger" role="alert">{{ $message }}</div>
<a class="btn btn-default" href="{{ route('gov.access.index') }}">{{ __('tenantops::access.my_access') }}</a>
@endsection
