/*
 * tenant-scope: floating navbar (docs/plans/topbar-navigation-plan.md §4.5).
 * Measures the bar into --gs-header-h, shades it once the page scrolls, hides it on
 * scroll-down / shows it on scroll-up on phones, and keeps bootstrap-table's sticky column
 * headers just below it instead of behind it.
 */
(function () {
    var html = document.documentElement;
    var header = document.querySelector('.main-header');
    if (!header || !html.hasAttribute('data-gs-sticky-header')) { return; }

    var phone = window.matchMedia('(max-width: 767px)');
    var height = header.offsetHeight;
    var hidden = false;
    var lastY = window.pageYOffset || 0;
    var ticking = false;

    function offset() { return hidden ? 0 : height; }

    // bootstrap-table reads stickyHeaderOffsetY on every scroll, so live instances follow too.
    function syncTables() {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.bootstrapTable) { return; }
        $.fn.bootstrapTable.defaults.stickyHeaderOffsetY = offset();
        $('table').each(function () {
            var table = $(this).data('bootstrap.table');
            if (table && table.options) { table.options.stickyHeaderOffsetY = offset(); }
        });
    }

    function measure() {
        height = header.offsetHeight;
        html.style.setProperty('--gs-header-h', height + 'px');
        syncTables();
    }

    function setHidden(next) {
        if (next === hidden) { return; }
        hidden = next;
        if (next) { html.setAttribute('data-gs-header-hidden', ''); } else { html.removeAttribute('data-gs-header-hidden'); }
        syncTables();
    }

    // Never hide while the user is working in the bar or the mobile sidebar is open.
    function mustStay() {
        return !!header.querySelector('.open, :focus-visible') || document.body.classList.contains('sidebar-open');
    }

    function update() {
        ticking = false;
        var y = Math.max(window.pageYOffset || 0, 0);
        if (y > 0) { html.setAttribute('data-gs-scrolled', ''); } else { html.removeAttribute('data-gs-scrolled'); }
        if (!phone.matches || y <= height || mustStay()) {
            setHidden(false);
            lastY = y;
            return;
        }
        if (Math.abs(y - lastY) < 8) { return; } // ignore jitter
        setHidden(y > lastY);
        lastY = y;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
    }, { passive: true });
    header.addEventListener('focusin', function () { setHidden(false); });
    var onBreakpoint = function () { setHidden(false); measure(); };
    phone.addEventListener ? phone.addEventListener('change', onBreakpoint) : phone.addListener(onBreakpoint);
    if ('ResizeObserver' in window) {
        new ResizeObserver(measure).observe(header);
    } else {
        window.addEventListener('resize', measure);
    }
    window.addEventListener('load', syncTables);

    measure();
    update();
})();
