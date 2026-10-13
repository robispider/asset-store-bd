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
<li><form action="{{ route('gov.membership.switch') }}" method="POST" class="om-inline-3591cfd6">@csrf
    <input type="hidden" name="location_id" value="0">
    <button class="btn btn-link" type="submit">{{ __('office_membership::member.menu_global_overview') }}</button>
</form></li>
@endif
@php($longSwitcher = $menuLocations->count() > 8)
@if($longSwitcher)
<li class="om-office-filter"><input type="search" class="form-control input-sm" id="om-office-filter" autocomplete="off"
    placeholder="{{ __('office_membership::member.menu_filter_offices') }}" aria-label="{{ __('office_membership::member.menu_filter_offices') }}"
    aria-controls="om-office-list"></li>
<li><ul class="list-unstyled om-office-list" id="om-office-list">
@endif
@foreach($menuLocations as $menuOffice)
<li class="om-office-option"><form action="{{ route('gov.membership.switch') }}" method="POST" class="om-inline-3591cfd6">@csrf
    @if($memberUser->isSuperUser())
    <input type="hidden" name="location_id" value="{{ $menuOffice->id }}">
    @else
    <input type="hidden" name="membership_id" value="{{ $menuMemberships->firstWhere('location_id', $menuOffice->id)->id }}">
    @endif
    <button class="btn btn-link" type="submit" @if((int) $memberContext->locationId === (int) $menuOffice->id) aria-current="true" @endif>{{ $menuOffice->name }}</button>
</form></li>
@endforeach
@if($longSwitcher)
</ul></li>
<script nonce="{{ csrf_token() }}">
document.addEventListener('DOMContentLoaded', function () {
    var filter = document.getElementById('om-office-filter');
    if (!filter) return;
    // Typing inside a Bootstrap dropdown must not close it.
    ['click', 'keydown'].forEach(function (type) { filter.addEventListener(type, function (event) { event.stopPropagation(); }); });
    filter.addEventListener('input', function () {
        var needle = filter.value.trim().toLocaleLowerCase();
        document.querySelectorAll('#om-office-list .om-office-option').forEach(function (option) {
            option.hidden = needle !== '' && option.textContent.toLocaleLowerCase().indexOf(needle) === -1;
        });
    });
});
</script>
@endif
@endauth
