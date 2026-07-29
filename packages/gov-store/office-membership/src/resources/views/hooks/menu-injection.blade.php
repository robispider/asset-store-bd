@auth
<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    
    console.log("Gov-Store: Starting Theme-Compliant User-Menu injection...");

    @php
        $user = auth()->user();
        $isAdmin = $user->isSuperUser() || $user->hasAccess('admin');

        $activeMemberships = \GovStore\OfficeMembership\Models\OfficeMembership::with('location')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $activeLocations = $isAdmin 
            ? \App\Models\Location::whereHas('profile', function($q) { $q->where('lifecycle_status', 'operational'); })->get() 
            : $activeMemberships->pluck('location')->filter();

        $currentMembershipId = session('gov_working_membership_id');
        
        $currentLocId = null;
        $currentWorkingName = 'Global Overview';
        
        if ($currentMembershipId) {
            $activeMem = $activeMemberships->where('id', $currentMembershipId)->first();
            if ($activeMem) {
                $currentWorkingName = $activeMem->location->name;
                $currentLocId = $activeMem->location_id;
            }
        } elseif (!$isAdmin) {
            $currentLocId = $user->location_id;
            $currentWorkingName = \App\Models\Location::find($currentLocId)?->name ?? 'Unknown Location';
        }
    @endphp

    var $userMenuDropdown = $('.dropdown.user-menu');
    var $userMenu = $userMenuDropdown.find('.dropdown-menu');

    if ($userMenuDropdown.length) {

        // =========================================================================
        // 1. THE TRIGGER: Two-line Name & Office in the Top Navbar
        // =========================================================================
        var $userToggle = $userMenuDropdown.find('> a.dropdown-toggle');
        var $hiddenXs = $userToggle.find('.hidden-xs');
        
        if ($hiddenXs.length) {
            var userName = $hiddenXs.text().trim() || '{{ addslashes($user->username) }}';
            
            var twoLineHtml = '<span style="display:inline-block; vertical-align:middle; text-align:left; line-height:1.2; padding-left:5px;">' +
                              '<strong>' + userName + '</strong><br>' +
                              '<small><i class="fas fa-map-marker-alt"></i> {{ addslashes($currentWorkingName) }}</small>' +
                              '</span>';
            
            $hiddenXs.replaceWith('<span class="hidden-xs">' + twoLineHtml + '</span>');
        }

        // =========================================================================
        // 2. BUILD THE INJECTION HTML (Using pure JS for HTML strings to prevent Blade escaping)
        // =========================================================================
        var injectedHtml = '';

        @if($activeLocations->count() >= 1)
            injectedHtml += '<li class="divider"></li>';
            injectedHtml += '<li class="dropdown-header">{{ __('office_membership::member.menu_choose_context') }}</li>';

            // Global Admin Option
            @if($isAdmin)
                var isGlobalActive = !'{{ $currentLocId }}';
                injectedHtml += '<li>' +
                    '<a href="#" onclick="event.preventDefault(); document.getElementById(\'switch-context-form-global\').submit();">' +
                        '<i class="fas fa-globe ' + (isGlobalActive ? 'text-aqua' : 'text-muted') + ' fa-fw"></i> ' +
                        '<span class="' + (isGlobalActive ? 'text-bold' : '') + '">{{ __('office_membership::member.menu_global_overview') }}</span>' +
                        (isGlobalActive ? ' <i class="fas fa-check pull-right text-aqua"></i>' : '') +
                    '</a>' +
                    '<form id="switch-context-form-global" action="{{ route("gov.membership.switch") }}" method="POST" style="display:none;">' +
                        '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                        '<input type="hidden" name="location_id" value="0">' +
                    '</form>' +
                '</li>';
            @endif

            // Office Locations Loop
            @foreach($activeLocations as $loc)
                var isCurrent_{{ $loc->id }} = {{ ($currentLocId === $loc->id) ? 'true' : 'false' }};
                injectedHtml += '<li>' +
                    '<a href="#" onclick="event.preventDefault(); document.getElementById(\'switch-context-form-{{ $loc->id }}\').submit();">' +
                        '<i class="fas fa-building ' + (isCurrent_{{ $loc->id }} ? 'text-aqua' : 'text-muted') + ' fa-fw"></i> ' +
                        '<span class="' + (isCurrent_{{ $loc->id }} ? 'text-bold' : '') + '">{{ addslashes($loc->name) }}</span>' +
                        (isCurrent_{{ $loc->id }} ? ' <i class="fas fa-check pull-right text-aqua"></i>' : '') +
                    '</a>' +
                    '<form id="switch-context-form-{{ $loc->id }}" action="{{ route("gov.membership.switch") }}" method="POST" style="display:none;">' +
                        '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                        @if($isAdmin)
                            '<input type="hidden" name="location_id" value="{{ $loc->id }}">' +
                        @else
                            @php $memId = $activeMemberships->where('location_id', $loc->id)->first()->id ?? 0; @endphp
                            '<input type="hidden" name="membership_id" value="{{ $memId }}">' +
                        @endif
                    '</form>' +
                '</li>';
            @endforeach
        @endif

        // My Memberships Link
        if (!$('#gov-memberships-dropdown-item').length) {
            injectedHtml += '<li class="divider"></li>' +
                '<li id="gov-memberships-dropdown-item">' +
                '<a href="{{ route("gov.membership.index") }}">' +
                    '<i class="fas fa-id-badge text-aqua fa-fw"></i> {{ __('office_membership::member.menu_my_memberships') }}' +
                '</a>' +
            '</li>';
        }

        // =========================================================================
        // 3. INJECT HTML SAFELY ABOVE THE LOGOUT BUTTON
        // =========================================================================
        var $logoutForm = $userMenu.find('#logout-form');
        
        if ($logoutForm.length) {
            var $logoutLi = $logoutForm.closest('li');
            var $prevDivider = $logoutLi.prev('.divider');
            
            // Insert before the divider directly above the logout button, or just before logout button
            if ($prevDivider.length) {
                $(injectedHtml).insertBefore($prevDivider);
            } else {
                $(injectedHtml).insertBefore($logoutLi);
            }
        } else {
            $userMenu.append(injectedHtml);
        }
        
        console.log("Gov-Store: Theme-Compliant User-Menu injection complete.");
    }
});
</script>
@endauth