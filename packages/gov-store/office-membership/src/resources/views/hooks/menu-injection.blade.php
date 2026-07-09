<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    @auth
    if ($('.dropdown.user-menu .dropdown-menu').length) {
        var memActive = window.location.pathname.includes('gov-store/my-memberships') ? 'style="font-weight: bold; background:#eee;"' : '';
        var link = '<li ' + memActive + '>' +
            '<a href="{{ route("gov.membership.index") }}">' +
                '<i class="fas fa-id-badge fa-fw"></i> My Office Memberships' +
            '</a>' +
        '</li>';
        
        // Find the "My Gov-Requests" link and insert this right after it
        var requestsLink = $('.dropdown.user-menu .dropdown-menu a[href*="my-requests"]').parent();
        if (requestsLink.length) {
            requestsLink.after(link);
        } else {
            $('.dropdown.user-menu .dropdown-menu').prepend(link);
        }
    }
    @endauth
});
</script>