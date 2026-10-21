/*
 * gs-geo — neutral geographic view engine (geo-view plan §7). Plain ES2019 IIFE modules concatenated in file
 * order into the lazy "geo" bundle. Pure modules export to globalThis.GsGeo so `node --test` can load them in a
 * vm context. The engine knows boundary files, a gs-geo-config/1 object and gs-geo-data/1 payloads, nothing else.
 */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};
    G.SVGNS = 'http://www.w3.org/2000/svg';

    G.clamp = function (v, lo, hi) { return Math.max(lo, Math.min(hi, v)); };

    /** el('div', {class:'x', text:'…', onclick:fn}, [children]) — text only via textContent. */
    G.el = function (tag, attrs, kids) {
        var doc = root.document;
        var svg = tag.indexOf('svg:') === 0;
        var n = svg ? doc.createElementNS(G.SVGNS, tag.slice(4)) : doc.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            var v = attrs[k];
            if (v === null || v === undefined || v === false) { return; }
            if (k === 'text') { n.textContent = v; } else if (k.indexOf('on') === 0) { n.addEventListener(k.slice(2), v); } else { n.setAttribute(k, v === true ? '' : v); }
        });
        [].concat(kids || []).forEach(function (k) {
            if (k === null || k === undefined || k === false) { return; }
            n.appendChild(k.nodeType ? k : doc.createTextNode(String(k)));
        });
        return n;
    };

    /** Default English strings; the server sends translated ones in config.strings. */
    G.STRINGS = {
        map: 'Map', table: 'Table', zoom_in: 'Zoom in', zoom_out: 'Zoom out', reset: 'Reset view', fullscreen: 'Full screen',
        layer: 'Show', all: 'All', area: 'Area', value: 'Value', offices: 'Offices', state: 'State', open: 'Open',
        up: 'Up one level', up_to: 'Up to :name', loading: 'Loading…', error: 'The map could not load. The table below has the same figures.',
        denied: 'This data isn’t available to you.', no_data: 'No data', no_access: 'Outside your access', no_boundary: 'No map shape',
        not_applicable: 'Not applicable', partial: 'Partly in your access', partial_note: 'Only offices in your access are counted',
        suppressed: 'Too few to show', here: 'At this level', unplaced: 'Not placed on the map', all_same: ':value · all areas with data',
        fallback: 'Showing :to because :from has no data here.', wheel_hint: 'Hold Ctrl and scroll to zoom', hint_drill: 'Click an area to open it.',
        hint_select: 'Click an area to select it.', hint_compare: 'Shift-click areas to compare them.', hint_pick: 'Click an area to choose it. Click again to open it.',
        shown: 'Showing :name: :count areas', national: 'National :value', vs: 'vs national', help: 'How to explore', help_close: 'Close help',
        help_new: 'New here? Press ? for help', help_foot: 'Press ? any time to show or hide this.', details: 'Offices in this area', none: 'None',
        selected: 'Selected', clear: 'Clear', search: 'Find an area', no_results: 'No matching area', map_label: 'Map. Arrow keys pan, plus and minus zoom, Escape goes up a level.',
        h_open: 'Open an area|Click it, or press Tab to reach it and Enter.', h_up: 'Go back up|Use the Up button on the map, the breadcrumb, or Esc.',
        h_zoom: 'Zoom|+ and − buttons, Ctrl + scroll, or pinch. Reset with 0.', h_pan: 'Move around|Drag the map, or use the arrow keys when the map has focus.',
        h_read: 'Read figures|Point at or tab to an area. The panel lists its offices.', h_legend: 'Legend|Point at a range to highlight its areas; click to keep it.',
        h_layer: 'Change measure|Pick another measure or filter in the toolbar.', h_search: 'Find|Type an area name in Find an area, then choose it.',
        h_table: 'Table|Switch to Table to see the same figures as a sortable list.', h_compare: 'Compare|Hold Shift and click areas to compare them side by side.',
        h_pick: 'Choose|Click an area to choose it. Click it again to open it.', h_states: 'Patterns|Hatched: outside your access. Grey: no data. Pale: only partly in your access.',
        export_csv: 'Download CSV', export_png: 'Download image', labels: 'Labels', compare: 'Compare', reference: 'Reference',
        none_cat: 'None', partial_cat: 'Partial', full: 'Full', flat: 'Show the whole country at this level'
    };

    G.strings = function (overrides) {
        var s = {};
        Object.keys(G.STRINGS).forEach(function (k) { s[k] = G.STRINGS[k]; });
        Object.keys(overrides || {}).forEach(function (k) { s[k] = overrides[k]; });
        return function (key, params) {
            var v = s[key] === undefined ? key : s[key];
            Object.keys(params || {}).forEach(function (p) { v = v.split(':' + p).join(params[p]); });
            return v;
        };
    };

    /** Number formatting in the viewer's locale (bn-BD gives Bengali digits). */
    G.formatter = function (locale) {
        var tag = /^bn/i.test(locale || '') ? 'bn-BD' : 'en-IN';
        var cache = {};
        var nf = function (d) {
            if (!cache[d]) { cache[d] = new Intl.NumberFormat(tag, { maximumFractionDigits: d, minimumFractionDigits: d }); }
            return cache[d];
        };
        var bn = tag === 'bn-BD';
        var fmt = function (v, format) {
            if (v === null || v === undefined || typeof v !== 'number' || isNaN(v)) { return '—'; }
            if (format === 'percent') { return nf(1).format(v * 100) + '%'; }
            if (format === 'decimal') { return nf(2).format(v); }
            if (format === 'money') {
                var unit = v >= 1e7 ? [v / 1e7, bn ? 'কোটি' : 'crore'] : v >= 1e5 ? [v / 1e5, bn ? 'লাখ' : 'lakh'] : [v, ''];
                return '৳' + nf(unit[1] ? 2 : 0).format(unit[0]) + (unit[1] ? ' ' + unit[1] : '');
            }
            return nf(0).format(Math.round(v));
        };
        fmt.int = function (v) { return nf(0).format(v); };
        fmt.short = function (v, format) { return format === 'percent' ? nf(0).format(v * 100) : fmt(v, format); };
        fmt.locale = tag;
        return fmt;
    };
})(typeof globalThis !== 'undefined' ? globalThis : window);
