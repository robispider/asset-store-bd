{{--
    Area picker: select2 over the organization geo search (server-side jurisdiction scope).
    Each result shows its name, type and parent path so places with the same name can be told apart.
    Usage: window.govorgGeoSelect('#geo_area_id', { dropdownParent: $('#dialog') }) after including this partial once.
--}}
<script>
window.govorgGeoSelect = window.govorgGeoSelect || function (selector, options) {
    var $el = $(selector);
    if (!$el.length || !$.fn.select2) { return $el; }
    function render(item) {
        if (!item.id) { return item.text; }
        var $row = $('<span></span>');
        $('<strong></strong>').text(item.text).appendTo($row);
        if (item.type) { $('<span class="govorg-muted"></span>').text(' · ' + item.type).appendTo($row); }
        if (item.path && item.path.length) { $('<span class="govorg-meta"></span>').text(item.path.join(' › ')).appendTo($row); }
        return $row;
    }
    $el.select2($.extend({
        width: '100%',
        minimumInputLength: 2,
        placeholder: @json(__('organization_labels::office.geo_search_placeholder')),
        language: { inputTooShort: function () { return @json(__('organization_labels::office.geo_search_short')); } },
        ajax: {
            url: @json(route('gov.org.provisioning.geo-search')),
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data }; },
            cache: true
        },
        templateResult: render
    }, options || {}));
    return $el;
};
</script>
