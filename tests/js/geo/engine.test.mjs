import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { loadGeo } from './load.mjs';
import { repo } from './schema.mjs';

const G = loadGeo();
const plain = v => JSON.parse(JSON.stringify(v));
const payload = (values, layer = { format: 'integer', aggregation: 'sum' }, extra = {}) => ({
  layer, stats: {}, ...extra,
  areas: Object.fromEntries(values.map((v, i) => [String(i + 1), v === null ? { state: 'no_data', value: null } : { state: 'ok', value: v }]))
});

test('scale: single value gets one bin (initiative 30 finding)', () => {
  const s = G.scale({ type: 'quantile', steps: 5 }, payload([55, 55, 55, null]));
  assert.equal(s.type, 'single');
  assert.equal(s.value, 55);
  assert.deepEqual(plain(s.classify(55)), { bin: 4, index: 0 });
});

test('scale: progress intent uses the fixed 0–100% domain only for percent layers', () => {
  const pct = G.scale({ type: 'sequential', steps: 5, domain: [0, 1] }, payload([0.1, 0.5, 0.9], { format: 'percent', aggregation: 'ratio' }));
  assert.deepEqual(plain(pct.breaks), [0, 0.2, 0.4, 0.6, 0.8, 1]);
  assert.equal(pct.classify(0.5).index, 2);
  assert.equal(pct.classify(1).index, 4);
  const qty = G.scale({ type: 'sequential', steps: 5, domain: [0, 1] }, payload([10, 20, 30, 40, 50, 60]));
  assert.equal(qty.breaks[0], 10);
  assert.equal(qty.breaks.at(-1), 60);
});

test('scale: quantile tones spread from 1 to 7 and empty/none classify nothing', () => {
  const s = G.scale({ type: 'quantile', steps: 3 }, payload([1, 2, 3, 4, 5, 6]));
  assert.equal(s.bins, 3);
  assert.deepEqual([0, 1, 2].map(s.tone), [1, 4, 7]);
  assert.equal(s.classify(1).bin, 1);
  assert.equal(s.classify(6).bin, 7);
  assert.equal(G.scale({ type: 'quantile' }, payload([null, null])).type, 'empty');
  assert.deepEqual(plain(G.scale({ type: 'none' }, payload([3])).classify(3)), {});
});

test('scale: tied values fall back to breaks over distinct values', () => {
  const s = G.scale({ type: 'quantile', steps: 4 }, payload([6, 6, 6, 6, 6, 6, 7, 13]));
  assert.ok(s.bins >= 2);
  assert.notEqual(s.classify(6).bin, s.classify(13).bin);
});

test('scale: diverging centres on the national ratio', () => {
  const s = G.scale({ type: 'diverging', reference: 'national' }, payload([0.2, 0.5, 0.8], { format: 'percent', aggregation: 'ratio' }, { stats: { national: 0.5 } }));
  assert.equal(s.ref, 0.5);
  assert.equal(s.classify(0.5).div, 0);
  assert.equal(s.classify(0.8).div, 3);
  assert.equal(s.classify(0.2).div, -3);
});

test('scale: threshold and categorical', () => {
  const t = G.scale({ type: 'threshold', thresholds: [10, 20] }, payload([5, 15, 25]));
  assert.deepEqual(plain(t.breaks), [5, 10, 20, 25]);
  assert.equal(t.classify(15).index, 1);
  const c = G.scale({ type: 'categorical' }, payload([0, 0.5, 1]));
  assert.deepEqual([0, 0.5, 1].map(v => c.classify(v).index), [0, 1, 2]);
});

test('camera: base pads 4%, clamp limits zoom, zoom keeps the anchor', () => {
  const base = G.camera.base('0 0 1000 500');
  assert.deepEqual(plain(base), { x: -40, y: -40, w: 1080, h: 580 });
  const tiny = G.camera.clamp({ x: 0, y: 0, w: 1, h: 0.5 }, base);
  assert.equal(tiny.w, 1080 / 14);
  const z = G.camera.zoom(base, 2, { x: 0, y: 0 });
  assert.deepEqual(plain(z), { x: -20, y: -20, w: 540, h: 290 });
  assert.equal(G.camera.level(z, base), 2);
  const fit = G.camera.fit([[0, 0, 100, 100], [200, 0, 100, 50]]);
  assert.equal(fit.x, -24);
  assert.equal(G.camera.lerp(base, z, 0.5).w, 810);
  assert.equal(G.camera.ease(0), 0);
  assert.equal(G.camera.ease(1), 1);
});

test('url: namespaced params round-trip and keep foreign params', () => {
  const qs = G.url.write('programme-map', { area: 3142, layer: 'value', filters: { code: 104, empty: '' }, select: [2390], mode: 'table', root: 9358 }, '?tab=where&gv-other[area]=5');
  assert.equal(qs, '?tab=where&gv-other[area]=5&gv-programme-map[area]=3142&gv-programme-map[layer]=value&gv-programme-map[mode]=table&gv-programme-map[select]=2390&gv-programme-map[f][code]=104');
  const back = G.url.read('programme-map', qs);
  assert.deepEqual(plain(back), { area: 3142, layer: 'value', filters: { code: '104' }, select: [2390], mode: 'table' });
  assert.equal(G.url.read('other', qs).area, 5);
  assert.equal(G.url.write('programme-map', { area: 9358, root: 9358 }, '?gv-programme-map[area]=1'), '');
  assert.equal(G.url.read('m', '?gv-m[area]=1;drop&gv-m[layer]=<x>').area, null);
});

test('config: locked keys refuse runtime changes; URL cannot pick a disallowed layer', () => {
  const cfg = JSON.parse(readFileSync(path.join(repo, 'tests/fixtures/geo-view/config.tracking.json'), 'utf8'));
  assert.equal(G.config.set(cfg, 'levels.max', 4), null);
  assert.equal(G.config.set(cfg, 'source.key', 'other.source'), null);
  assert.equal(G.config.set(cfg, 'size.height', 500).size.height, 500);
  assert.equal(cfg.size.height, 420);
  const s = G.config.initialState(cfg, { area: 3142, layer: 'secret_layer', filters: { code: '104' }, select: [], mode: 'table' });
  assert.equal(s.area, 3142);
  assert.equal(s.layer, null);
  assert.equal(s.mode, 'table');
  assert.equal(G.config.initialState({ ...cfg, url_state: false }, { area: 1 }).area, null);
});

test('config: help tips follow intent and toolbar', () => {
  const base = { intent: 'progress', toolbar: { layers: true, table: true }, panels: { legend: true, table: 'toggle' }, interaction: { select: 'single' } };
  assert.deepEqual(plain(G.config.helpKeys(base)), ['open', 'up', 'zoom', 'pan', 'read', 'legend', 'layer', 'table', 'states']);
  const compare = G.config.helpKeys({ ...base, interaction: { select: 'multi' }, toolbar: { search: true } });
  assert.equal(compare[1], 'compare');
  assert.ok(compare.includes('search'));
  assert.deepEqual(plain(G.config.helpKeys({ intent: 'pick', toolbar: {}, panels: { table: 'none' }, interaction: {} })), ['pick', 'up', 'zoom', 'pan', 'states']);
});

test('store: selection toggles, filters drop empties, subscribers see changes', () => {
  const st = G.store({ filters: { code: 1 } });
  const seen = [];
  st.subscribe((s, prev, a) => seen.push(a.type));
  st.dispatch({ type: 'toggle', id: 5 });
  assert.deepEqual(plain(st.get().select), [5]);
  st.dispatch({ type: 'toggle', id: 5 });
  assert.deepEqual(plain(st.get().select), []);
  st.dispatch({ type: 'toggle', id: 5, additive: true });
  st.dispatch({ type: 'toggle', id: 6, additive: true });
  assert.deepEqual(plain(st.get().select), [5, 6]);
  st.dispatch({ type: 'filters', filters: { code: '', plan: 'x' } });
  assert.deepEqual(plain(st.get().filters), { plan: 'x' });
  st.dispatch({ type: 'loaded', payload: { area: { id: 1 }, areas: { 5: {} } }, keepSelection: true });
  assert.deepEqual(plain(st.get().select), [5]);
  assert.deepEqual(seen, ['toggle', 'toggle', 'toggle', 'toggle', 'filters', 'loaded']);
});

test('format: Bengali digits and money units', () => {
  const bn = G.formatter('bn-BD');
  assert.equal(bn(220, 'integer'), '২২০');
  assert.equal(G.formatter('en')(10300000, 'money'), '৳1.03 crore');
  assert.equal(G.formatter('en')(0.023, 'percent'), '2.3%');
  assert.equal(G.formatter('en')(null, 'integer'), '—');
  const t = G.strings({ up_to: 'Up to :name' });
  assert.equal(t('up_to', { name: 'Barisal' }), 'Up to Barisal');
});
