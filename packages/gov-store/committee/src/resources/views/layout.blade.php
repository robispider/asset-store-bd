@extends('layouts.default')
@section('title', __('committee::committee.title'))
@push('css')
<link rel="stylesheet" href="{{ mix('css/dist/committee.css') }}">
@endpush
@section('content')
@php
    $display = \GovStore\Committee\Support\CommitteeDisplay::class;
    $context = app(\GovStore\TenantScope\Contexts\TenantContext::class);
    $enumOptions = fn ($keys) => collect(explode(' ', $keys))->mapWithKeys(fn ($key) => [$key => __('committee::committee.options.'.$key)])->all();
    $typeOptions = ($types ?? collect())->where('is_active',true)->mapWithKeys(fn ($t) => [$t->id => $display::text($t->name_bn,$t->name_en)])->all();
    $orderOptions = collect($orders ?? [])->mapWithKeys(fn ($o) => [$o['id'] => $o['memo_no'].' · '.$display::date($o['issued_on'])])->all();
@endphp
<div class="committee-workspace" data-base="{{ url('gov-store/committees') }}" data-committee="{{ $c?->id ?? '' }}" data-today="{{ now('Asia/Dhaka')->toDateString() }}" data-locale="{{ app()->getLocale() }}" data-copy="{{ json_encode(__('committee::committee.ux')) }}" data-options="{{ json_encode(__('committee::committee.options')) }}">
    <div class="cm-office"><strong>{{ __('committee::committee.title') }}</strong><span><i class="fa fa-building-o" aria-hidden="true"></i> {{ $officeName ?? \Illuminate\Support\Facades\DB::table('locations')->where('id',$context->locationId)->value('name') }} · {{ auth()->user()->display_name ?: auth()->user()->first_name }}</span></div>
    <nav aria-label="{{ __('committee::committee.title') }}">
        <ul class="nav nav-tabs cm-nav">
            @if(app(\GovStore\TenantScope\Services\GovAccess::class)->permitsRequest(auth()->user(),'committee.manage'))<li class="{{ $mode === 'dashboard' ? 'active' : '' }}"><a href="{{ route('committee.dashboard') }}" @if($mode === 'dashboard') aria-current="page" @endif>{{ __('committee::committee.ux.desk') }}</a></li>@endif
            @can('committee.view')<li class="{{ in_array($mode,['registry','search','history']) || ($c ?? null) ? 'active' : '' }}"><a href="{{ route('committee.registry') }}">{{ __('committee::committee.ux.all_committees') }}</a></li>@endcan
            <li class="{{ $mode === 'mine' ? 'active' : '' }}"><a href="{{ route('committee.mine') }}" @if($mode === 'mine') aria-current="page" @endif>{{ __('committee::committee.mine') }}</a></li>
            @can('committee.types.manage')<li class="cm-settings {{ in_array($mode,['types','purposes']) ? 'active' : '' }}"><a href="{{ route('committee.types') }}"><i class="fa fa-cog" aria-hidden="true"></i> {{ __('committee::committee.ux.rules') }}</a></li>@endcan
        </ul>
    </nav>
    <div class="alert hidden cm-feedback" role="status" aria-live="polite" tabindex="-1"></div>
    @yield('committee-content')
    @include('committee::partials/confirmation')
</div>
@stop
@section('moar_scripts')<script src="{{ mix('js/dist/committee.js') }}"></script>@stop
