{{-- Live duplicate check: same ministry + same area → list the existing offices with a link to each. --}}
<script>
$(function () {
    window.govorgGeoSelect('#geo_area_id');
    var $box = $('#govorg-duplicates');
    var $list = $box.find('[data-govorg-duplicate-list]');
    function check() {
        var companyId = $('#company_id').val();
        var geoAreaId = $('#geo_area_id').val();
        if (!companyId || !geoAreaId) { $box.prop('hidden', true); return; }
        $.getJSON(@json(route('gov.org.provisioning.check-duplicate')), { company_id: companyId, geo_area_id: geoAreaId })
            .done(function (data) {
                $list.empty();
                $.each(data || [], function (_, item) {
                    var $li = $('<li></li>');
                    $('<a></a>').attr('href', item.url).text(item.name).appendTo($li);
                    if (item.status) { $('<span class="govorg-muted"></span>').text(' · ' + item.status).appendTo($li); }
                    $list.append($li);
                });
                $box.prop('hidden', !(data && data.length));
            })
            .fail(function () { $box.prop('hidden', true); });
    }
    $('#company_id, #geo_area_id').on('change', check);
    check();
});
</script>
