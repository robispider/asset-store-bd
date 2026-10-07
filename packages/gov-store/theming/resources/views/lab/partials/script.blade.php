<script nonce="{{ csrf_token() }}">
document.addEventListener('DOMContentLoaded', function () {
    var GS = window.GS || {};
    var panelOf = function (el) { return el.closest('[data-skin]') || document.documentElement; };

    // Charts: palette and frame from the panel's own tokens; redrawn by the gsTheme plugin on mode change.
    if (window.Chart) {
        document.querySelectorAll('.js-gs-lab-chart').forEach(function (canvas) {
            var data = JSON.parse(canvas.getAttribute('data-chart'));
            var type = canvas.getAttribute('data-type');
            var colors = GS.theme ? GS.theme.palette(data.labels.length, canvas) : [];
            var dataset = { label: 'Assets by division', data: data.series, backgroundColor: colors };
            if (type === 'line') {
                dataset.backgroundColor = 'transparent';
                dataset.borderColor = colors[0];
                dataset.pointBackgroundColor = colors[0];
            }
            new window.Chart(canvas, {
                type: type,
                data: { labels: data.labels, datasets: [dataset] },
                options: { responsive: true, maintainAspectRatio: false, legend: { display: type === 'pie' }, plugins: { gsTheme: { recolorData: type === 'line' ? false : 'all' } } }
            });
        });
    }

    // select2 / datepicker popups render inside the panel so they inherit its theme.
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-gs-lab-select2').each(function () {
            jQuery(this).select2({ dropdownParent: jQuery(panelOf(this)), width: '100%' });
        });
    }
    if (window.jQuery && jQuery.fn.datepicker) {
        jQuery('.js-gs-lab-date').each(function () {
            jQuery(this).datepicker({ container: '#' + panelOf(this).id, format: 'yyyy-mm-dd', autoclose: true });
        });
    }

    // "Show token names" overlay.
    document.querySelectorAll('[data-gs-lab-tokens]').forEach(function (box) {
        box.addEventListener('change', function () {
            document.body.classList.toggle('gs-lab-show-tokens', box.checked);
        });
    });
});
</script>
