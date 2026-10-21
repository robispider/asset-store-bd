/* Persist disclosure IDs only; the server reauthorizes and rerenders every navigation. */
(function () {
    'use strict';
    function start() {
        const page = document.querySelector('[data-portfolio-key]');
        if (!page) return;
        const key = 'gs-portfolio-open-' + page.dataset.portfolioKey;
        let open = new Set();
        try {
            const saved = JSON.parse(sessionStorage.getItem(key) || '[]');
            if (Array.isArray(saved)) open = new Set(saved.filter(id => /^portfolio-details-\d+$/.test(id)).slice(-200));
        } catch (error) { /* Storage can be unavailable; disclosures still work. */ }
        page.querySelectorAll('[data-monitor-toggle]').forEach(function (button) {
            const id = button.getAttribute('aria-controls');
            if (open.has(id) && button.getAttribute('aria-expanded') === 'false') button.click();
            button.addEventListener('click', function () {
                if (button.getAttribute('aria-expanded') === 'true') open.add(id); else open.delete(id);
                try { sessionStorage.setItem(key, JSON.stringify(Array.from(open).slice(-200))); } catch (error) { /* Optional preference. */ }
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
