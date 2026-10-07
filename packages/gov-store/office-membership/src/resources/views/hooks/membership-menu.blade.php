@auth
@php
    $memberUser = auth()->user();
    $memberContext = app(\GovStore\TenantScope\Contexts\TenantContext::class);
    $menuMemberships = \GovStore\OfficeMembership\Models\OfficeMembership::with('location')->where('user_id', $memberUser->id)
        ->where('status', 'active')->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString()))->get();
    $menuLocations = $memberUser->isSuperUser()
        ? \App\Models\Location::withoutGlobalScopes()->whereNull('deleted_at')->whereHas('profile')->orderBy('name')->get()
        : $menuMemberships->pluck('location')->filter(fn ($l) => $l && ! $l->deleted_at);
@endphp
<li class="divider"></li>
<li><a href="{{ route('gov.membership.index') }}"><i class="fas fa-id-badge fa-fw" aria-hidden="true"></i> {{ __('office_membership::member.menu_my_memberships') }}</a></li>
<li class="dropdown-header">{{ __('office_membership::member.menu_choose_context') }}</li>
@if($memberUser->isSuperUser())
<li><form action="{{ route('gov.membership.switch') }}" method="POST" style="padding: 3px 20px">@csrf
    <input type="hidden" name="location_id" value="0">
    <button class="btn btn-link" type="submit">{{ __('office_membership::member.menu_global_overview') }}</button>
</form></li>
@endif
@foreach($menuLocations as $menuOffice)
<li><form action="{{ route('gov.membership.switch') }}" method="POST" style="padding: 3px 20px">@csrf
    @if($memberUser->isSuperUser())
    <input type="hidden" name="location_id" value="{{ $menuOffice->id }}">
    @else
    <input type="hidden" name="membership_id" value="{{ $menuMemberships->firstWhere('location_id', $menuOffice->id)->id }}">
    @endif
    <button class="btn btn-link" type="submit" @if((int) $memberContext->locationId === (int) $menuOffice->id) aria-current="true" @endif>{{ $menuOffice->name }}</button>
</form></li>
@endforeach
@endauth
