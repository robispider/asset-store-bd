@extends('layouts/default')
@section('title', __('tenantops::access.title'))
@section('content')
<div class="tenant-scope-theme">
<div class="box box-warning">
    <div class="box-header with-border"><h1 class="box-title" lang="bn">{{ trans('tenantops::access.title', [], 'bn-BD') }}</h1><p lang="en">{{ trans('tenantops::access.title', [], 'en-US') }}</p></div>
    <div class="box-body" role="alert">
        <p lang="bn">{{ trans('tenantops::access.abilities.'.str_replace('.', '_', $payload['ability']), [], 'bn-BD') }}</p>
        <p lang="en">{{ trans('tenantops::access.abilities.'.str_replace('.', '_', $payload['ability']), [], 'en-US') }}</p>
        <p lang="bn">{{ trans('tenantops::access.'.$payload['reason_key'], [], 'bn-BD') }}</p>
        <p lang="en">{{ trans('tenantops::access.'.$payload['reason_key'], [], 'en-US') }}</p>
        <h2 class="h4">{{ __('tenantops::access.helpers') }}</h2>
        <ul>@forelse($payload['helpers'] as $helper)<li>{{ $helper['name'] }}</li>@empty<li>{{ __('tenantops::access.no_helpers') }}</li>@endforelse</ul>
        @php($nextKey = (config('govstore-abilities', [])[$payload['ability']]['national'] ?? false) ? 'national_next_step' : 'next_step')
        <p lang="bn">{{ trans('tenantops::access.'.$nextKey, [], 'bn-BD') }}</p><p lang="en">{{ trans('tenantops::access.'.$nextKey, [], 'en-US') }}</p>
        <p>{{ __('tenantops::access.reference') }}: <code>{{ $payload['reference_id'] }}</code></p>
        <a class="btn btn-primary" href="{{ $payload['access_url'] }}">{{ __('tenantops::access.request') }}</a>
        <a class="btn btn-default" href="{{ route('gov.access.index') }}">{{ __('tenantops::access.my_access') }}</a>
        <a class="btn btn-default" href="{{ url('/') }}">{{ __('tenantops::access.back') }}</a>
    </div>
</div>

</div>
@endsection
