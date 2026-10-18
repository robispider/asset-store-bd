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
            // Without JS the breakdown remains readable; enhance each pair into a disclosure.
            table.querySelectorAll('[data-monitor-toggle]').forEach(function (button) {
                const detail = document.getElementById(button.getAttribute('aria-controls'));
                if (!detail || button.closest('tr').nextElementSibling !== detail) return;
                function expand(open) {
                    button.setAttribute('aria-expanded', String(open));
                    button.setAttribute('aria-label', open ? button.dataset.collapseLabel : button.dataset.expandLabel);
                    button.querySelector('[data-monitor-toggle-icon]').textContent = open ? '−' : '+';
                    detail.hidden = !open;
                }
                expand(false);
                button.hidden = false;
                button.addEventListener('click', function () { expand(button.getAttribute('aria-expanded') !== 'true'); });
            });
            frame.querySelectorAll('[data-monitor-sort]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const index = Number(button.dataset.monitorSort);
                    const numeric = button.dataset.monitorSortType === 'number';
                    const descending = button.dataset.direction !== 'desc';
                    const groups = Array.from(table.tBodies[0].rows).filter(row => !row.hasAttribute('data-monitor-detail')).map(function (row) {
                        const next = row.nextElementSibling;
                        const toggle = row.querySelector('[data-monitor-toggle]');
                        const detail = next?.hasAttribute('data-monitor-detail') && toggle?.getAttribute('aria-controls') === next.id ? next : null;
                        return { row: row, detail: detail };
                    });
                    if (groups.some(group => group.row.cells.length <= index)) return;
                    groups.sort(function (a, b) {
                        const left = a.row.cells[index].dataset.sort ?? a.row.cells[index].textContent.trim();
                        const right = b.row.cells[index].dataset.sort ?? b.row.cells[index].textContent.trim();
                        const comparison = numeric ? Number(left) - Number(right) : left.localeCompare(right, undefined, { numeric: true });
                        return (descending ? -1 : 1) * comparison;
                    });
                    groups.forEach(function (group) {
                        table.tBodies[0].appendChild(group.row);
                        if (group.detail) table.tBodies[0].appendChild(group.detail);
                    });
                    table.querySelectorAll('th').forEach(th => th.removeAttribute('aria-sort'));
                    button.closest('th').setAttribute('aria-sort', descending ? 'descending' : 'ascending');
                    button.dataset.direction = descending ? 'desc' : 'asc';
                });
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
