@auth
<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    
    console.log("Gov-Store: Starting dynamic layout injection checks...");

    // =========================================================================
    // 1. USER DROP-DOWN LINK: Inject My Office Memberships safely after "Edit Profile"
    // =========================================================================
    if ($('.dropdown.user-menu .dropdown-menu').length) {
        var memActive = window.location.pathname.includes('gov-store/my-memberships') ? 'style="font-weight: bold; background:#eee;"' : '';
        var link = '<li Handy-id="gov-memberships-dropdown-item" ' + memActive + '>' +
            '<a href="{{ route("gov.membership.index") }}">' +
                '<i class="fas fa-id-badge fa-fw"></i> My Office Memberships' +
            '</a>' +
        '</li>';
        
        var profileLink = $('.dropdown.user-menu .dropdown-menu a[href*="profile"]').parent();
        if (profileLink.length) {
            profileLink.after(link);
            console.log("Gov-Store: Successfully injected memberships link after profile.");
        } else {
            $('.dropdown.user-menu .dropdown-menu').append(link);
        }
    }

    // =========================================================================
    // 2. TOP-BAR MULTI-OFFICE CONTEXT SWITCHER (PURE MEMBERSHIP ID LOGIC)
    // =========================================================================
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

        // Read the pure working membership ID from the session
        $currentMembershipId = session('gov_working_membership_id');
        
        $currentWorkingName = null;
        if ($currentMembershipId) {
            $activeMem = $activeMemberships->where('id', $currentMembershipId)->first();
            $currentWorkingName = $activeMem ? $activeMem->location->name : null;
        } elseif ($isAdmin) {
            // Superadmins without an active membership show Global
            $currentWorkingName = null;
        } else {
            // Fallback for native users without memberships
            $currentWorkingName = \App\Models\Location::find($user->location_id)?->name;
        }
    @endphp

    @if($activeLocations->count() > 0)
        var navList = $('.navbar-custom-menu ul.nav').length ? $('.navbar-custom-menu ul.nav') : $('.navbar-custom-menu ul');
        
        if (navList.length && !$('#gov-context-switcher-item').length) {
            
            var activeLabel = '{{ $currentWorkingName }}' ? '{{ $currentWorkingName }}' : 'Global Overview';
            var activeClassLabel = '{{ $currentWorkingName }}' ? 'text-yellow' : 'text-green';

            var dropdownHtml = '<li class="dropdown" id="gov-context-switcher-item" style="border-right: 1px solid rgba(255,255,255,0.1);">' +
                '<a href="#" class="dropdown-toggle" data-toggle="dropdown" style="color: white; font-weight: bold; padding: 15px 15px;">' +
                    '<i class="fas fa-hotel"></i> &nbsp;Working As: <span class="' + activeClassLabel + '">' + activeLabel + '</span> &nbsp;<span class="caret"></span>' +
                '</a>' +
                '<ul class="dropdown-menu" style="background-color: #fff; width: 280px; padding: 5px 0;">' +
                    '<li class="header" style="padding: 8px 15px; font-size: 11px; color: #777; border-bottom: 1px solid #f4f4f4; background-color: #fafafa; font-weight: bold;">CHOOSE WORKING CONTEXT</li>';

            @if($isAdmin)
                var isGlobalActive = !'{{ $currentWorkingName }}';
                dropdownHtml += '<li style="border-bottom: 1px dashed #eee;">' +
                    '<a href="#" onclick="event.preventDefault(); document.getElementById(\'switch-context-form-global\').submit();" style="padding: 10px 15px; display: block; clear: both; font-weight: bold; color: #3c8dbc; background: ' + (isGlobalActive ? '#eef7ff' : 'transparent') + '">' +
                        '<i class="fas fa-globe" style="margin-right: 10px;"></i>' +
                        '🌎 Global Overview (All Offices)' +
                    '</a>' +
                    '<form id="switch-context-form-global" action="{{ route("gov.membership.switch") }}" method="POST" style="display:none;">' +
                        '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                        '<input type="hidden" name="location_id" value="0">' +
                    '</form>' +
                '</li>';
            @endif

            @foreach($activeLocations as $loc)
                @php 
                    // Check if this location matches the currently active membership
                    $isCurrent = $currentMembershipId && $activeMemberships->where('id', $currentMembershipId)->first()?->location_id === $loc->id; 
                    
                    // Fallback visual check for Superadmins who don't have an active membership id but are viewing a specific location
                    if (!$currentMembershipId && $isAdmin && session('gov_working_location_id') == $loc->id) {
                        $isCurrent = true;
                    }
                @endphp
                
                dropdownHtml += '<li style="border-bottom: 1px solid #f9f9f9;">' +
                    '<a href="#" onclick="event.preventDefault(); document.getElementById(\'switch-context-form-{{ $loc->id }}\').submit();" style="padding: 10px 15px; display: block; clear: both; font-weight: normal; line-height: 1.42857143; color: #333; white-space: nowrap; background: {{ $isCurrent ? "#f4f4f4" : "transparent" }}">' +
                        '<i class="fas fa-map-marker-alt {{ $isCurrent ? "text-green" : "text-muted" }}" style="margin-right: 10px;"></i>' +
                        '<strong>' + '{{ addslashes($loc->name) }}' + '</strong>' +
                    '</a>' +
                    '<form id="switch-context-form-{{ $loc->id }}" action="{{ route("gov.membership.switch") }}" method="POST" style="display:none;">' +
                        '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                        
                        // FOR ADMINS: Pass location_id. FOR STAFF: Pass specific membership_id.
                        @if($isAdmin)
                            '<input type="hidden" name="location_id" value="{{ $loc->id }}">' +
                        @else
                            @php $memId = $activeMemberships->where('location_id', $loc->id)->first()->id; @endphp
                            '<input type="hidden" name="membership_id" value="{{ $memId }}">' +
                        @endif
                        
                    '</form>' +
                '</li>';
            @endforeach

            dropdownHtml += '</ul></li>';

            navList.first().prepend(dropdownHtml);
            console.log("Gov-Store: Multi-office context switcher successfully injected.");
        }
    @endif

    // =========================================================================
    // 3. ADMIN SIDEBAR: Inject Emergency Overrides console strictly for system Superusers
    // =========================================================================
    @if(auth()->user()->isSuperUser())
        if ($('.sidebar-menu').length && !$('#gov-override-sidebar-item').length) {
            var activeClass = window.location.pathname.includes('override/console') ? 'active' : '';
            var sidebarLink = '<li class="' + activeClass + '" id="gov-override-sidebar-item">' +
                '<a href="{{ route("gov.membership.override.console") }}">' +
                    '<i class="fas fa-shield-alt fa-fw"></i>' +
                    '<span>Membership Overrides</span>' +
                '</a>' +
            '</li>';
            
            if ($('#gov-jurisdictions-sidebar-item').length) {
                $('#gov-jurisdictions-sidebar-item').after(sidebarLink);
            } else {
                $('.sidebar-menu').append(sidebarLink);
            }
            console.log("Gov-Membership: Emergency Console link injected successfully.");
        }
    @endif

    // =========================================================================
    // 4. INJECT STAFF MANAGEMENT LINK (For Office Admins - RESOLVED VIA SINGLETON)
    // =========================================================================
    @php
        // Pure Singleton Resolution: Pulls the active context location ID from memory
        $context = app(\GovStore\TenantScope\Contexts\TenantContext::class);
        
        $isAdminOfActiveContext = false;
        if ($context->isActive && $context->locationId) {
            $isAdminOfActiveContext = \GovStore\Organization\Models\LocationProfile::where('location_id', $context->locationId)
                ->where('office_admin_id', auth()->id())
                ->exists();
        }
    @endphp

    @if($isAdminOfActiveContext)
        if ($('.sidebar-menu').length) {
            var staffActive = window.location.pathname.includes('gov-store/office/staff') ? 'active' : '';
            var staffLink = '<li class="' + staffActive + '">' +
                '<a href="{{ route("gov.membership.admin.index") }}">' +
                    '<i class="fas fa-users-cog fa-fw"></i>' +
                    '<span>Staff Management</span>' +
                '</a>' +
            '</li>';
            
            var setupMenu = $('.sidebar-menu a[href*="gov-store/office"]').filter(function() {
                return $(this).attr('href').endsWith('gov-store/office') || $(this).attr('href').endsWith('gov-store/office/');
            });

            if (setupMenu.length) {
                setupMenu.parent().after(staffLink);
            } else {
                $('.sidebar-menu').append(staffLink);
            }
            console.log("Gov-Membership: Staff Management link injected successfully.");
        }
    @endif
});
</script>
@endauth