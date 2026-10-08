@extends('layouts/default')
@section('title', __('tenantops::access.my_access'))
@section('content')
<div class="tenant-scope-theme">
<div class="box box-primary"><div class="box-header with-border"><h1 class="box-title">{{ __('tenantops::access.my_access') }}</h1></div><div class="box-body">
<p>{{ __('tenantops::access.office') }}: {{ $context->locationId ?? '—' }}</p>
<p>{{ __('tenantops::access.roles') }}: {{ implode(', ', array_map(fn ($role) => __('tenantops::access.role_names.'.$role), $roles)) }}</p>
<h2 class="h4">{{ __('tenantops::access.abilities_label') }}</h2>
<ul>@foreach($abilities as $ability => $definition)<li>{{ __('tenantops::access.abilities.'.str_replace('.', '_', $ability)) }}</li>@endforeach</ul>
<h2 class="h4">{{ __('tenantops::access.notifications') }}</h2>
<ul>@foreach(\Illuminate\Support\Facades\DB::table('gov_access_notices')->where('user_id', auth()->id())->orderByDesc('id')->limit(10)->get() as $notice)<li>{{ __('tenantops::access.'.$notice->message_key) }} ({{ $notice->created_at }})</li>@endforeach</ul>
@if(!$requestable)<p class="alert alert-info">{{ __('tenantops::access.not_requestable') }}</p>@endif
@if($context->locationId && $requestable)
<form method="post" action="{{ route('gov.access.requests.store') }}">@csrf
    <div class="form-group"><label for="accessAbility">{{ __('tenantops::access.request') }}</label><select id="accessAbility" name="ability" class="form-control" required>
    @foreach(\GovStore\TenantScope\Http\Controllers\AccessController::REQUESTABLE as $ability => $role)<option value="{{ $ability }}" @selected($selected === $ability)>{{ __('tenantops::access.abilities.'.str_replace('.', '_', $ability)) }}</option>@endforeach
    </select></div>
    <div class="form-group"><label for="accessReason">{{ __('tenantops::access.reason') }}</label><textarea id="accessReason" name="reason" class="form-control" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea></div>
    <div class="form-group"><label for="accessEnd">{{ __('tenantops::access.end_date') }}</label><input id="accessEnd" type="date" name="expires_at" class="form-control" value="{{ old('expires_at') }}"></div>
    <button class="btn btn-primary" type="submit">{{ __('tenantops::access.request') }}</button>
</form>
@endif
<h2 class="h4">{{ __('tenantops::access.requests') }}</h2>
<ul>@foreach($requests as $entry)<li>{{ __('tenantops::access.abilities.'.str_replace('.', '_', $entry->ability)) }}: {{ __('tenantops::access.'.$entry->status) }} @if($entry->review_note) — {{ $entry->review_note }} @endif</li>@endforeach</ul>{{ $requests->links() }}
@can('access.manage')<a class="btn btn-default" href="{{ route('gov.access.requests.index') }}">{{ __('tenantops::access.requests') }}</a>@endcan
@can('access.matrix')<a class="btn btn-default" href="{{ route('gov.access.matrix') }}">{{ __('tenantops::access.matrix') }}</a>@endcan
@can('access.shadow')<a class="btn btn-default" href="{{ route('gov.access.shadow') }}">{{ __('tenantops::access.shadow') }}</a>@endcan
@can('access.audit')<a class="btn btn-default" href="{{ route('gov.access.audit') }}">{{ __('tenantops::access.audit') }}</a>@endcan
</div></div>

</div>
@endsection
