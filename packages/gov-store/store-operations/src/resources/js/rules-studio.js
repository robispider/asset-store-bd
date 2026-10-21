/* Rules studio (product rules hub + policy editor). Every page works without it: context forms
   submit, rule rows are links and the editor saves. This only makes those steps immediate. */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function () {
        // Context pickers: changing a select reloads the trace; changing the item clears the model.
        document.querySelectorAll('form[data-rst-context]').forEach(function (form) {
            form.querySelectorAll('[data-rst-submit]').forEach(function (button) { button.hidden = true; });
            form.addEventListener('change', function (event) {
                var clears = event.target.getAttribute('data-rst-clears');
                if (clears && document.getElementById(clears)) document.getElementById(clears).value = '';
                form.submit();
            });
        });

        // Tree filter: hide nodes whose text does not match; keep ancestors of matches visible.
        document.querySelectorAll('[data-rst-filter]').forEach(function (input) {
            var root = document.getElementById(input.getAttribute('data-rst-filter'));
            if (!root) return;
            input.addEventListener('input', function () {
                var query = input.value.trim().toLowerCase();
                root.querySelectorAll('li').forEach(function (li) { li.removeAttribute('data-rst-hide'); });
                if (!query) return;
                Array.prototype.slice.call(root.querySelectorAll('li')).reverse().forEach(function (li) {
                    var own = li.querySelector(':scope > [data-rst-filter-text]');
                    var match = own && own.getAttribute('data-rst-filter-text').toLowerCase().indexOf(query) !== -1;
                    var visibleChild = li.querySelector(':scope li:not([data-rst-hide])');
                    if (!match && !visibleChild) li.setAttribute('data-rst-hide', '');
                });
                root.querySelectorAll('details').forEach(function (details) {
                    details.open = !!details.querySelector('li:not([data-rst-hide])');
                });
            });
        });

        // View A: selecting a rule shows how it was decided without reloading.
        document.querySelectorAll('[data-rst-rule]').forEach(function (row) {
            row.addEventListener('click', function (event) {
                var code = row.getAttribute('data-rst-rule');
                var panel = document.querySelector('[data-rst-why="' + code + '"]');
                if (!panel) return;
                event.preventDefault();
                document.querySelectorAll('[data-rst-rule]').forEach(function (other) { other.removeAttribute('aria-current'); });
                row.setAttribute('aria-current', 'true');
                document.querySelectorAll('[data-rst-why]').forEach(function (other) { other.hidden = other !== panel; });
                if (window.history && window.history.replaceState) window.history.replaceState(null, '', row.href);
                panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        });

        // Editor D: recompute each row's result and explanation as choices change.
        // Mirrors ProfileAdminController::editorOutcome(); keep the two in step.
        var textNode = document.getElementById('rst-editor-text');
        if (!textNode) return;
        var text = JSON.parse(textNode.textContent);
        var form = document.getElementById('rst-editor-form');
        function fill(template, values) {
            return template.replace(/:(\w+)/g, function (all, key) { return values[key] !== undefined ? values[key] : all; });
        }
        function outcome(row) {
            var checked = row.querySelector('[data-rst-choice]:checked');
            var choice = checked ? checked.value : 'INHERIT';
            var above = row.getAttribute('data-above') || null;
            var source = row.getAttribute('data-above-source');
            var result = choice === 'INHERIT' ? above : choice;
            var note, tone = 'info';
            if (choice === 'INHERIT') note = above ? fill(text.follows, { source: source }) : text.nobody;
            else if (choice === above) note = text.repeats;
            else if (choice === 'DISABLE' && above === 'ENFORCE') { note = fill(text.overrides, { source: source }); tone = 'warn'; }
            else if (choice === 'DISABLE' && above === 'DISABLE') note = text.already_off;
            else if (choice === 'DISABLE') note = text.off_unset;
            else if (above === 'DISABLE') { note = fill(text.turns_back_on, { source: source }); tone = 'good'; }
            else { note = text.adds; tone = 'good'; }

            var label = row.getAttribute('data-none-label');
            if (result === 'DISABLE') label = row.getAttribute('data-off-label');
            if (result === 'ENFORCE') {
                label = row.getAttribute('data-on-label');
                var monthsInput = row.querySelector('[data-rst-months]');
                var value = choice === 'ENFORCE' && monthsInput ? monthsInput.value : row.getAttribute('data-above-months');
                if (value !== null && value !== '') label += ' · ' + fill(text.months, { months: parseInt(value, 10) || 0 });
            }
            var config = row.querySelector('[data-rst-config]');
            if (config) config.classList.toggle('active', choice === 'ENFORCE');

            var pill = row.querySelector('[data-rst-result]');
            pill.textContent = label;
            pill.className = 'rst-st rst-st--' + (result === 'ENFORCE' ? 'on' : result === 'DISABLE' ? 'off' : 'none');
            var noteEl = row.querySelector('[data-rst-note]');
            noteEl.textContent = note;
            noteEl.className = 'rst-note rst-note--' + tone;
            // Only a change in what the storekeeper gets counts: on vs not on, or other warranty months.
            var months = row.querySelector('[data-rst-months]');
            var differs = (result === 'ENFORCE') !== (above === 'ENFORCE')
                || (choice === 'ENFORCE' && above === 'ENFORCE' && !!months && String(parseInt(months.value, 10)) !== row.getAttribute('data-above-months'));
            row.classList.toggle('rst-ed-row--changed', differs);
            row.classList.toggle('rst-ed-row--mine', choice !== 'INHERIT');

            return { differs: differs, warn: tone === 'warn' };
        }
        function refresh() {
            var changes = 0, warnings = 0;
            form.querySelectorAll('[data-rst-row]').forEach(function (row) {
                var state = outcome(row);
                if (state.differs) changes++;
                if (state.warn) warnings++;
            });
            form.querySelector('[data-rst-summary]').textContent = fill(text.summary, { count: changes });
            form.querySelector('[data-rst-warn]').textContent = warnings ? fill(text.warn, { count: warnings }) : text.no_warn;
        }
        form.addEventListener('change', refresh);
        form.addEventListener('input', function (event) { if (event.target.hasAttribute('data-rst-months')) refresh(); });
    });
})();
