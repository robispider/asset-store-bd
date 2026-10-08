@extends('layouts/default')
@section('title', __('tenantops::access.title'))
@section('content')
<div class="tenant-scope-theme">
<div class="alert alert-danger" role="alert">{{ $message }}</div>
<a class="btn btn-default" href="{{ route('gov.access.index') }}">{{ __('tenantops::access.my_access') }}</a>

</div>
@endsection
