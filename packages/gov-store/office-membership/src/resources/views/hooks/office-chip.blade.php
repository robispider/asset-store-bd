{{-- Navbar office chip (context zone): shows the working office and switches it.
     Members with a few offices pick from a short list; super users and members of many offices
     get recent offices plus a search box. The full office list is never rendered on a page. --}}
@auth
@php
    $chipUser = auth()->user();
    $chipPicker = app(\GovStore\OfficeMembership\Services\OfficePicker::class);
    $chipSuper = $chipUser->isSuperUser();
    $chipMemberships = $chipSuper ? collect() : $chipPicker->memberships($chipUser);
    $chipSearches = $chipPicker->searches($chipUser, $chipMemberships);
    $chipRecent = $chipSearches ? $chipPicker->recent($chipUser) : collect();
    $chipLocationId = (int) app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId;
    $chipOffice = $chipLocationId ? \App\Models\Location::withoutGlobalScopes()->find($chipLocationId) : null;
    $chipName = $chipOffice?->name
        ?? ($chipSuper ? __('office_membership::member.menu_all_offices') : __('office_membership::member.menu_no_office'));
@endphp
@if($chipSuper || $chipMemberships->isNotEmpty())
<li class="dropdown gs-office-chip" id="gov-office-chip">
    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"
       title="{{ __('office_membership::member.menu_working_office', ['office' => $chipName]) }}">
        <i class="fas fa-map-marker-alt fa-fw" aria-hidden="true"></i>
        <span class="sr-only">{{ __('office_membership::member.menu_working_office', ['office' => '']) }}</span>
        <span class="gs-office-chip-name">{{ $chipName }}</span>
        <strong class="caret" aria-hidden="true"></strong>
    </a>
    <ul class="dropdown-menu gs-office-picker">
        <li class="dropdown-header">{{ __('office_membership::member.menu_choose_context') }}</li>
        <li class="hidden">
            {{-- One switch form for every option; option buttons fill in the field. --}}
            <form action="{{ route('gov.membership.switch') }}" method="POST" id="gs-office-switch">@csrf</form>
        </li>
        @if($chipSuper)
            <li><ul class="gs-office-picker-results">
                <li><button type="submit" form="gs-office-switch" name="location_id" value="0" class="btn btn-link"
                    @if(! $chipLocationId) aria-current="true" @endif>
                    <i class="fas fa-globe fa-fw" aria-hidden="true"></i> {{ __('office_membership::member.menu_global_overview') }}
                </button></li>
            </ul></li>
        @endif
        @if($chipSearches)
            @if($chipRecent->isNotEmpty())
                <li class="dropdown-header">{{ __('office_membership::member.menu_recent_offices') }}</li>
                <li><ul class="gs-office-picker-results">
                    @foreach($chipRecent as $chipTarget)
                        <li><button type="submit" form="gs-office-switch" name="{{ $chipTarget['field'] }}" value="{{ $chipTarget['value'] }}"
                            class="btn btn-link" @if($chipTarget['location_id'] === $chipLocationId) aria-current="true" @endif>
                            {{ $chipTarget['text'] }}
                            @if($chipTarget['sub'] !== '')<span class="gs-office-picker-sub">{{ $chipTarget['sub'] }}</span>@endif
                        </button></li>
                    @endforeach
                </ul></li>
            @endif
            <li class="gs-office-picker-search">
                <label class="sr-only" for="gs-office-search">{{ __('office_membership::member.menu_filter_offices') }}</label>
                <input type="search" class="form-control input-sm" id="gs-office-search" autocomplete="off"
                    placeholder="{{ __('office_membership::member.menu_filter_offices') }}"
                    aria-controls="gs-office-search-results" data-endpoint="{{ route('gov.membership.offices') }}">
            </li>
            <li><ul class="gs-office-picker-results" id="gs-office-search-results" aria-live="polite"></ul></li>
            <li class="gs-office-picker-status" id="gs-office-search-status" role="status"
                data-hint="{{ __('office_membership::member.menu_search_hint') }}"
                data-searching="{{ __('office_membership::member.menu_searching') }}"
                data-none="{{ __('office_membership::member.menu_search_none') }}"
                data-more="{{ __('office_membership::member.menu_search_more') }}"
                data-failed="{{ __('office_membership::member.menu_search_failed') }}">{{ __('office_membership::member.menu_search_hint') }}</li>
        @else
            <li><ul class="gs-office-picker-results">
                @foreach($chipMemberships as $chipMembership)
                    <li><button type="submit" form="gs-office-switch" name="membership_id" value="{{ $chipMembership->id }}" class="btn btn-link"
                        @if((int) $chipMembership->location_id === $chipLocationId) aria-current="true" @endif>
                        {{ $chipMembership->location->name }}
                    </button></li>
                @endforeach
            </ul></li>
        @endif
        <li class="divider"></li>
        <li><a href="{{ route('gov.membership.index') }}"><i class="fas fa-id-badge fa-fw" aria-hidden="true"></i> {{ __('office_membership::member.menu_my_memberships') }}</a></li>
    </ul>
</li>
@if($chipSearches)
<script nonce="{{ csrf_token() }}">
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('gs-office-search');
    var list = document.getElementById('gs-office-search-results');
    var status = document.getElementById('gs-office-search-status');
    var chip = document.getElementById('gov-office-chip');
    if (!input || !list || !status) return;
    var timer = null, sequence = 0;
    var say = function (key) { status.textContent = status.getAttribute('data-' + key) || ''; status.hidden = !status.textContent; };
    // Typing and clicking inside a Bootstrap dropdown must not close it.
    ['click', 'keydown'].forEach(function (type) { input.addEventListener(type, function (event) { event.stopPropagation(); }); });
    if (window.jQuery) { window.jQuery(chip).on('shown.bs.dropdown', function () { input.focus(); }); }
    input.addEventListener('input', function () {
        window.clearTimeout(timer);
        var term = input.value.trim();
        if (term.length < 2) { list.innerHTML = ''; say('hint'); return; }
        timer = window.setTimeout(function () {
            var mine = ++sequence;
            say('searching');
            fetch(input.getAttribute('data-endpoint') + '?q=' + encodeURIComponent(term), {
                credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                if (!response.ok) throw new Error(response.status);
                return response.json();
            }).then(function (data) {
                if (mine !== sequence) return; // a newer search is on its way
                list.innerHTML = '';
                (data.results || []).forEach(function (office) {
                    var item = document.createElement('li');
                    var button = document.createElement('button');
                    button.type = 'submit';
                    button.className = 'btn btn-link';
                    button.setAttribute('form', 'gs-office-switch');
                    button.name = office.field;
                    button.value = office.value;
                    button.appendChild(document.createTextNode(office.text));
                    if (office.sub) {
                        var sub = document.createElement('span');
                        sub.className = 'gs-office-picker-sub';
                        sub.textContent = office.sub;
                        button.appendChild(sub);
                    }
                    item.appendChild(button);
                    list.appendChild(item);
                });
                say(!data.results || !data.results.length ? 'none' : (data.more ? 'more' : ''));
            }).catch(function () { if (mine === sequence) say('failed'); });
        }, 250);
    });
});
</script>
@endif
@endif
@endauth
