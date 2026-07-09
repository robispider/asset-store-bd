@auth
<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    
    console.log("Gov-Store: Starting dynamic layout injection checks...");

    // 1. USER DROP-DOWN LINK: Inject My Office Memberships safely after "Edit Profile"
    if ($('.dropdown.user-menu .dropdown-menu').length) {
        var memActive = window.location.pathname.includes('gov-store/my-memberships') ? 'style="font-weight: bold; background:#eee;"' : '';
        var link = '<li Handy-id="gov-memberships-dropdown-item" ' + memActive + '>' +
            '<a href="{{ route("gov.membership.index") }}">' +
                '<i class="fas fa-id-badge fa-fw"></i> My Office Memberships' +
            '</a>' +
        '</li>';
        
        // Find the "Edit Profile" link (which always exists in Snipe-IT) and insert right after it
        var profileLink = $('.dropdown.user-menu .dropdown-menu a[href*="profile"]').parent();
        if (profileLink.length) {
            profileLink.after(link);
            console.log("Gov-Store: Successfully injected memberships link after profile.");
        } else {
            // Safe fallback
            $('.dropdown.user-menu .dropdown-menu').append(link);
            console.log("Gov-Store: Fallback - Appended memberships link to dropdown menu.");
        }
    }

    // 2. TOP-BAR MULTI-OFFICE CONTEXT SWITCHER
    @php
        $user = auth()->user();
        $isAdmin = $user->isSuperUser() || $user->hasAccess('admin');

        if ($isAdmin) {
            // Superadmins can toggle into ANY operational location in the system
            $activeLocations = \App\Models\Location::whereHas('profile', function($q) {
                $q->where('lifecycle_status', 'operational');
            })->get();
        } else {
            // Standard users are strictly restricted to locations where they hold active memberships
            $activeLocations = \App\Models\Location::whereIn('id', function($q) use ($user) {
                $q->select('location_id')
                  ->from('gov_office_memberships')
                  ->where('user_id', $user->id)
                  ->where('status', 'active');
            })->get();
        }

        $currentWorkingId = session('gov_working_location_id');
        $currentWorkingName = $currentWorkingId ? \App\Models\Location::find($currentWorkingId)?->name : null;
    @endphp

    // Render switcher if there are available locations to switch to
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

            // SUPERADMIN GLOBAL OVERVIEW ESCAPE LINK:
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
                @php $isCurrent = $currentWorkingId && (int)$loc->id === (int)$currentWorkingId; @endphp
                
                dropdownHtml += '<li style="border-bottom: 1px solid #f9f9f9;">' +
                    '<a href="#" onclick="event.preventDefault(); document.getElementById(\'switch-context-form-{{ $loc->id }}\').submit();" style="padding: 10px 15px; display: block; clear: both; font-weight: normal; line-height: 1.42857143; color: #333; white-space: nowrap; background: {{ $isCurrent ? "#f4f4f4" : "transparent" }}">' +
                        '<i class="fas fa-map-marker-alt {{ $isCurrent ? "text-green" : "text-muted" }}" style="margin-right: 10px;"></i>' +
                        '<strong>' + '{{ $loc->name }}' + '</strong>' +
                    '</a>' +
                    '<form id="switch-context-form-{{ $loc->id }}" action="{{ route("gov.membership.switch") }}" method="POST" style="display:none;">' +
                        '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                        '<input type="hidden" name="location_id" value="{{ $loc->id }}">' +
                    '</form>' +
                '</li>';
            @endforeach

            dropdownHtml += '</ul></li>';

            // Prepend directly into the first index of the navbar menu list
            navList.first().prepend(dropdownHtml);
            console.log("Gov-Store: Multi-office context switcher successfully injected.");
        }
    @endif

    // 3. ADMIN SIDEBAR: Inject Emergency Overrides console strictly for system Superusers
    @if(auth()->user()->isSuperUser())
        if ($('.sidebar-menu').length && !$('#gov-override-sidebar-item').length) {
            var activeClass = window.location.pathname.includes('override/console') ? 'active' : '';
            var sidebarLink = '<li class="' + activeClass + '" id="gov-override-sidebar-item">' +
                '<a href="{{ route('gov.membership.override.console') }}">' +
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
});
</script>
@endauth