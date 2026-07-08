<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    
    console.log("Gov-TenantScope: Checking dynamic menus.");

    // INJECT SIDEBAR "TENANT SCOPING" (Strictly for Superadmins)
    @if(auth()->user()->isSuperUser())
        if ($('.sidebar-menu').length) {
            var activeClass = window.location.pathname.includes('gov-store/admin/scope') ? 'active' : '';
            var sidebarLink = '<li class="' + activeClass + '" id="gov-tenantscope-sidebar-item">' +
                '<a href="{{ route('gov.scope.index') }}">' +
                    '<i class="fas fa-user-lock fa-fw"></i>' +
                    '<span>Tenant Scoping</span>' +
                '</a>' +
            '</li>';
            
            $('.sidebar-menu').append(sidebarLink);
            console.log("Gov-TenantScope: 'Tenant Scoping' injected.");
        }
    @endif
});
</script>