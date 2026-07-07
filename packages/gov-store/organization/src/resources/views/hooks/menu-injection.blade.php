<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    console.log("Gov-Organization: Building dynamic provisioning menus.");

    if ($('.sidebar-menu').length) {
        var path = window.location.pathname;
        var isOrgActive = path.includes('gov-store/admin/organization') || path.includes('gov-store/office');
        var isRegistryActive = path.includes('admin/organization') && !path.includes('jurisdictions') && !path.includes('settings');
        var isJurisdictionsActive = path.includes('admin/organization/jurisdictions');
        var isOfficeSetupActive = path.includes('gov-store/office');

        @php
            $isIctOfficer = \GovStore\Organization\Models\IctJurisdiction::where('user_id', auth()->id())->exists();
            $isOfficeAdmin = \GovStore\Organization\Models\LocationProfile::where('office_admin_id', auth()->id())->exists();
            $isAdmin = auth()->user()->isSuperUser() || auth()->user()->hasAccess('admin');
        @endphp

        // Render "Office Provisioning" folder if the user holds any administrative/onboarding assignment
        @if($isAdmin || $isIctOfficer || $isOfficeAdmin)
            var orgMenu = '<li class="treeview ' + (isOrgActive ? 'active' : '') + '" id="gov-org-parent-menu">' +
                '<a href="#">' +
                    '<i class="fas fa-sitemap fa-fw"></i>' +
                    '<span>Office Provisioning</span>' +
                    '<span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>' +
                '</a>' +
                '<ul class="treeview-menu">';

            // 1. Office Registry (For ICT Officers & Admins)
            @if($isAdmin || $isIctOfficer)
                orgMenu += '<li class="' + (isRegistryActive ? 'active' : '') + '"><a href="{{ route("gov.org.provisioning.index") }}"><i class="fas fa-building fa-fw"></i> Office Registry</a></li>';
            @endif
            
            // 2. ICT Jurisdictions (Only for global Superadmins)
            @if($isAdmin)
                orgMenu += '<li class="' + (isJurisdictionsActive ? 'active' : '') + '"><a href="{{ route("gov.org.jurisdictions.index") }}"><i class="fas fa-shield-alt fa-fw"></i> ICT Jurisdictions</a></li>';
            @endif

            // 3. Local Office Settings Checklist (For local Office Admins and Superadmins)
            @if($isAdmin || $isOfficeAdmin)
                orgMenu += '<li class="' + (isOfficeSetupActive ? 'active' : '') + '"><a href="{{ route("gov.org.config.index") }}"><i class="fas fa-hotel fa-fw"></i> My Office Setup</a></li>';
            @endif

            orgMenu += '</ul></li>';

            // Place the Provisioning tree directly below the Store tree block (maintaining consistent spacing)
            if ($('#gov-store-parent-menu').length) {
                $('#gov-store-parent-menu').after(orgMenu);
            } else if ($('.sidebar-menu li.firstnav').length) {
                $('.sidebar-menu li.firstnav').after(orgMenu);
            } else {
                $('.sidebar-menu').prepend(orgMenu);
            }
            console.log("Gov-Organization: Folders successfully generated.");
        @endif
    }
});
</script>