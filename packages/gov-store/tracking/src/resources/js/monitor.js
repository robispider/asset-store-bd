/* Progressive enhancement: all tracking data and scope decisions are server-rendered. */
(function () {
    'use strict';
    function start() {
        document.querySelectorAll('[data-monitor-chart]').forEach(function (svg) {
            const crosshair = svg.querySelector('[data-chart-crosshair]');
            const tooltip = svg.parentElement.querySelector('[data-chart-tooltip]');
            const points = Array.from(svg.querySelectorAll('[data-chart-point]'));
            function show(x) {
                crosshair.setAttribute('x1', x); crosshair.setAttribute('x2', x); crosshair.setAttribute('visibility', 'visible');
                const nearest = points.reduce((best, point) => !best || Math.abs(Number(point.dataset.chartPoint) - x) < Math.abs(Number(best.dataset.chartPoint) - x) ? point : best, null);
                if (tooltip) tooltip.textContent = nearest?.querySelector('title')?.textContent || '';
            }
            function hide() { crosshair.setAttribute('visibility', 'hidden'); if (tooltip) tooltip.textContent = ''; }
            svg.addEventListener('pointermove', event => {
                const bounds = svg.getBoundingClientRect();
                show(Math.max(36, Math.min(636, 680 * (event.clientX - bounds.left) / bounds.width)));
            });
            svg.addEventListener('pointerleave', hide);
            svg.addEventListener('focusin', event => { if (event.target.dataset.chartPoint) show(Number(event.target.dataset.chartPoint)); });
            svg.addEventListener('focusout', hide);
        });
        document.querySelectorAll('[data-monitor-jump]').forEach(function (select) {
            select.addEventListener('change', function () { if (select.value) window.location.assign(select.value); });
        });
        document.querySelectorAll('[data-monitor-copy]').forEach(function (button) {
            button.addEventListener('click', async function () {
                const message = button.parentElement.querySelector('[data-copy-result]');
                try {
                    await navigator.clipboard.writeText(button.dataset.monitorCopy);
                    if (message) message.textContent = button.dataset.copySuccess || button.dataset.monitorCopy;
                } catch (error) {
                    if (message) message.textContent = button.dataset.copyFailure || button.dataset.monitorCopy;
                }
            });
        });
        document.querySelectorAll('[data-monitor-sortable]').forEach(function (frame) {
            const table = frame.matches('table') ? frame : frame.querySelector('table');
            if (!table || !table.tBodies[0]) return;
            frame.querySelectorAll('[data-monitor-sort]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const index = Number(button.dataset.monitorSort);
                    const descending = button.dataset.direction !== 'desc';
                    const rows = Array.from(table.tBodies[0].rows);
                    if (rows.some(row => row.cells.length <= index)) return;
                    rows.sort(function (a, b) {
                        const left = a.cells[index].dataset.sort ?? a.cells[index].textContent.trim();
                        const right = b.cells[index].dataset.sort ?? b.cells[index].textContent.trim();
                        return (descending ? -1 : 1) * left.localeCompare(right, undefined, { numeric: true });
                    });
                    rows.forEach(row => table.tBodies[0].appendChild(row));
                    table.querySelectorAll('th').forEach(th => th.removeAttribute('aria-sort'));
                    button.closest('th').setAttribute('aria-sort', descending ? 'descending' : 'ascending');
                    button.dataset.direction = descending ? 'desc' : 'asc';
                });
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
