@extends('layouts/default')
@section('title', __('tenantops::access.requests'))
@section('content')
<div class="tenant-scope-theme">
@foreach($requests as $entry)
<div class="box box-default"><div class="box-header with-border"><h2 class="box-title">{{ __('tenantops::access.abilities.'.str_replace('.', '_', $entry->ability)) }} — {{ __('tenantops::access.'.$entry->status) }}</h2></div><div class="box-body">
<p>{{ __('tenantops::access.actor') }}: {{ \App\Models\User::withoutGlobalScopes()->find($entry->user_id)?->getFullNameAttribute() ?? $entry->user_id }}</p>
<p>{{ $entry->reason }}</p><p>{{ __('tenantops::access.end_date') }}: {{ $entry->expires_at ?? '—' }}</p>
@if($entry->status === 'pending')
<form method="post" action="{{ route('gov.access.requests.review', $entry->id) }}">@csrf
<label for="note-{{ $entry->id }}">{{ __('tenantops::access.review_note') }}</label><textarea id="note-{{ $entry->id }}" name="review_note" class="form-control" maxlength="1000"></textarea>
<button type="submit" name="decision" value="approved" class="btn btn-success">{{ __('tenantops::access.approve') }}</button>
<button type="submit" name="decision" value="declined" class="btn btn-default">{{ __('tenantops::access.decline') }}</button>
</form>
@else<p>{{ $entry->review_note }}</p>@endif
</div></div>
@endforeach
{{ $requests->links() }}

</div>
@endsection
