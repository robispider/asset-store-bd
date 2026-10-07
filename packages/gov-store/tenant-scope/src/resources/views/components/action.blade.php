@props(['ability', 'type' => 'button', 'locked' => false])
@php
    $access = app(\GovStore\TenantScope\Services\GovAccess::class);
    $decision = $access->decide(auth()->user(), $ability);
    $enabled = !$locked && $access->permitsRequest(auth()->user(), $ability);
    $reasonId = 'access-reason-'.\Illuminate\Support\Str::uuid();
@endphp
<button type="{{ $type }}" {{ $attributes }} @if(!$enabled) disabled aria-disabled="true" aria-describedby="{{ $reasonId }}" @endif>{{ $slot }}</button>
@if(!$enabled)
    <span id="{{ $reasonId }}" class="help-block">{{ __('tenantops::access.'.($locked ? 'state_locked' : $decision->reason)) }}</span>
@endif
