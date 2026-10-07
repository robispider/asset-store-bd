@auth
<script nonce="{{ csrf_token() }}">
document.addEventListener('DOMContentLoaded', function () {
    $(document).ajaxError(function (event, xhr) {
        var data = xhr.responseJSON;
        if (!data || !data.reason_key) return;
        var content = document.querySelector('.content');
        if (!content) return;
        var panel = document.getElementById('gov-access-inline');
        if (!panel) {
            panel = document.createElement('div'); panel.id = 'gov-access-inline';
            panel.className = 'alert alert-warning'; panel.setAttribute('role', 'alert'); content.prepend(panel);
        }
        panel.hidden = false;
        panel.textContent = (data.action || '') + ' ' + data.reason + ' ' + (data.next_step || '') + ' ' + (data.reference_id || '');
        (data.helpers || []).forEach(function (helper) {
            var person = document.createElement('p'); person.textContent = helper.name; panel.appendChild(person);
        });
        var link = document.createElement('a'); link.className = 'btn btn-default';
        link.href = data.review_url || data.access_url;
        link.textContent = data.review_url ? @json(__('tenantops::access.national_review')) : @json(__('tenantops::access.request'));
        panel.appendChild(link);
    });
});
</script>
@endauth
