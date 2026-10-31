@extends('layouts/default')
@section('title', __('tenantops::access.ownership_review'))
@section('content')
<div class="box box-primary"><div class="box-body"><h1>{{ __('tenantops::access.ownership_review') }}</h1>
<p>{{ __('tenantops::access.ownership_notice') }}</p>
<p role="status">@foreach(['national','company','office','unresolved'] as $scope){{ __('tenantops::access.scopes.'.$scope) }}: {{ $ownershipCounts[$scope] ?? 0 }}@if(!$loop->last) · @endif @endforeach</p>
<table class="table"><thead><tr><th>{{ __('tenantops::access.reference_type') }}</th><th>{{ __('tenantops::access.reference') }}</th><th>{{ __('tenantops::access.owner_scope') }}</th><th>{{ __('tenantops::access.owner_id') }}</th></tr></thead><tbody>
@foreach($owners as $owner)
<tr><td>{{ __('tenantops::access.reference_types.'.$owner->reference_type) }}</td><td>{{ $owner->reference_name }}</td><td>{{ __('tenantops::access.scopes.'.$owner->owner_type) }}</td><td>
<form method="post" action="{{ route('gov.scope.ownership.update') }}">@csrf
<input type="hidden" name="reference_type" value="{{ $owner->reference_type }}"><input type="hidden" name="reference_id" value="{{ $owner->reference_id }}">
<label for="owner-{{ $owner->id }}" class="sr-only">{{ __('tenantops::access.owner_id') }}: {{ $owner->reference_name }}</label>
<select id="owner-{{ $owner->id }}" name="owner_choice" required class="form-control">
<option value="">{{ __('tenantops::access.scopes.unresolved') }}</option>
<option value="national" @selected($owner->owner_type === 'national')>{{ __('tenantops::access.scopes.national') }}</option>
<optgroup label="{{ __('tenantops::access.scopes.company') }}">@foreach($companies as $company)<option value="company:{{ $company->id }}" @selected($owner->owner_type === 'company' && (int)$owner->owner_id === (int)$company->id)>{{ $company->name }}</option>@endforeach</optgroup>
<optgroup label="{{ __('tenantops::access.scopes.office') }}">@foreach($offices as $office)<option value="office:{{ $office->id }}" @selected($owner->owner_type === 'office' && (int)$owner->owner_id === (int)$office->id)>{{ $office->name }}</option>@endforeach</optgroup>
</select>
<button type="submit" class="btn btn-primary">{{ __('tenantops::access.national_review') }}</button></form>
</td></tr>
@endforeach
</tbody></table>{{ $owners->links() }}</div></div>
@endsection
