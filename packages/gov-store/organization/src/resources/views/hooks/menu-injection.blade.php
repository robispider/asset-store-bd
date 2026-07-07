<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    
    console.log("Gov-Organization: Checking menu authorizations.");

    // 1. INJECT SIDEBAR "ORGANIZATION SETUP" (Only for ICT Officers / Superadmins)
    @if(auth()->user()->isSuperUser() || auth()->user()->hasAccess('admin') || \GovStore\Organization\Models\IctJurisdiction::where('user_id', auth()->id())->exists())
        if ($('.sidebar-menu').length) {
            var activeClass = window.location.pathname.includes('gov-store/admin/organization') ? 'active' : '';
            var sidebarLink = '<li class="' + activeClass + '" id="gov-organization-sidebar-item">' +
                '<a href="{{ route('gov.org.provisioning.index') }}">' +
                    '<i class="fas fa-sitemap fa-fw"></i>' +
                    '<span>Organization Setup</span>' +
                '</a>' +
            '</li>';
            
            $('.sidebar-menu').append(sidebarLink);
            console.log("Gov-Organization: 'Organization Setup' injected.");
        }
    @endif

    // 2. INJECT SIDEBAR "MY OFFICE" (Only for delegated Office Administrators)
    @php
        $isOfficeAdmin = \GovStore\Organization\Models\LocationProfile::where('office_admin_id', auth()->id())->exists();
    @endphp
    @if(auth()->user()->isSuperUser() || auth()->user()->hasAccess('admin') || $isOfficeAdmin)
        if ($('.sidebar-menu').length) {
            var activeClass = window.location.pathname.includes('gov-store/office') ? 'active' : '';
            var officeLink = '<li class="' + activeClass + '" id="gov-myoffice-sidebar-item">' +
                '<a href="{{ route('gov.org.config.index') }}">' +
                    '<i class="fas fa-hotel fa-fw"></i>' +
                    '<span>My Office Setup</span>' +
                '</a>' +
            '</li>';
            
            $('.sidebar-menu').append(officeLink);
            console.log("Gov-Organization: 'My Office Setup' injected.");
        }
    @endif


    // 1. INJECT SIDEBAR ADMIN SETTINGS (Only for Admins/Superadmins)
    @if(auth()->user()->isSuperUser() || auth()->user()->hasAccess('admin') || auth()->user()->hasAccess('superuser'))
        console.log("Gov-Store: User is verified as Admin/Superuser.");
        
        if ($('.sidebar-menu').length) {
            var approvalsActive = window.location.pathname.includes('gov-requests/admin') && !window.location.pathname.includes('settings') ? 'active' : '';
            var fulfillmentActive = window.location.pathname.includes('gov-requests/fulfillment') ? 'active' : '';
            var locationsActive = window.location.pathname.includes('settings/locations') ? 'active' : '';
            var policiesActive = window.location.pathname.includes('settings/policies') ? 'active' : '';
            
            // Checking active states for our two organization links
            var setupActive = window.location.pathname.includes('admin/organization') && !window.location.pathname.includes('jurisdictions') ? 'active' : '';
            var jurisdictionsActive = window.location.pathname.includes('admin/organization/jurisdictions') ? 'active' : '';
            
            var sidebarLink = `
                <li class="${approvalsActive}" id="gov-approvals-sidebar-item">
                    <a href="{{ route('gov.requests.admin.index') }}">
                        <i class="fas fa-clipboard-check fa-fw"></i>
                        <span>Gov Approvals</span>
                    </a>
                </li>
                <li class="${fulfillmentActive}" id="gov-fulfillment-sidebar-item">
                    <a href="{{ route('gov.requests.fulfillment.index') }}">
                        <i class="fas fa-shipping-fast fa-fw"></i>
                        <span>Fulfillment Queue</span>
                    </a>
                </li>
                <li class="${locationsActive}" id="gov-locations-sidebar-item">
                    <a href="{{ route('gov.requests.admin.locations.index') }}">
                        <i class="fas fa-map-marked-alt fa-fw"></i>
                        <span>Office Assignments</span>
                    </a>
                </li>
                <li class="${policiesActive}" id="gov-policies-sidebar-item">
                    <a href="{{ route('gov.requests.admin.policies.index') }}">
                        <i class="fas fa-tags fa-fw"></i>
                        <span>Category Policies</span>
                    </a>
                </li>
                <li class="${setupActive}" id="gov-setup-sidebar-item">
                    <a href="{{ route('gov.org.provisioning.index') }}">
                        <i class="fas fa-sitemap fa-fw"></i>
                        <span>Organization Setup</span>
                    </a>
                </li>
                <li class="${jurisdictionsActive}" id="gov-jurisdictions-sidebar-item">
                    <a href="{{ route('gov.org.jurisdictions.index') }}">
                        <i class="fas fa-shield-alt fa-fw"></i>
                        <span>ICT Jurisdictions</span>
                    </a>
                </li>
            `;
            
            if ($('.sidebar-menu li.firstnav').length) {
                $('.sidebar-menu li.firstnav').after(sidebarLink);
            } else {
                $('.sidebar-menu').prepend(sidebarLink);
            }
            console.log("Gov-Store: Sidebar links appended successfully.");
        } else {
            console.log("Gov-Store: ERROR - '.sidebar-menu' not found.");
        }
    @endif
});
</script>