// The built (minified) lazy bundles load and keep the engine's behaviour (geo-view plan §7.5).
// Skips when gs-theme:build has not run in this checkout.
import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { gzipSync } from 'node:zlib';
import { repo } from './schema.mjs';

const dir = path.join(repo, 'public/vendor/gs-theme');
const manifestFile = path.join(dir, 'manifest.json');
const manifest = existsSync(manifestFile) ? JSON.parse(readFileSync(manifestFile, 'utf8')) : null;
const lazy = manifest && manifest.lazy ? manifest.lazy : null;

test('built geo bundles load and behave like the sources', { skip: !lazy && 'run php artisan gs-theme:build first' }, () => {
  const context = vm.createContext({ Intl, URLSearchParams, Math, JSON, console, Promise, Map });
  context.globalThis = context;
  const geo = readFileSync(path.join(dir, lazy.geo), 'utf8');
  vm.runInContext(geo, context);
  const G = context.GsGeo;
  assert.equal(typeof G.View, 'function');
  assert.equal(typeof context.GsGeoView.get, 'function');
  const s = G.scale({ type: 'quantile', steps: 4 }, { layer: { format: 'integer' }, areas: { 1: { state: 'ok', value: 6 }, 2: { state: 'ok', value: 6 }, 3: { state: 'ok', value: 13 } } });
  assert.notEqual(s.classify(6).bin, s.classify(13).bin);
  assert.equal(G.url.read('m', G.url.write('m', { area: 5, root: 1, flat: 2 }, '')).flat, 2);
  assert.equal(G.formatter('bn-BD')(55, 'integer'), '৫৫');
  // The tools bundle extends the view without touching the core bundle.
  vm.runInContext(readFileSync(path.join(dir, lazy['geo-tools']), 'utf8'), context);
  assert.equal(typeof G.View.prototype.exportCsv, 'function');
  assert.equal(typeof G.View.prototype.buildExtras, 'function');
});

test('geo bundle stays within its size guard', { skip: !lazy && 'run php artisan gs-theme:build first' }, () => {
  const geo = readFileSync(path.join(dir, lazy.geo));
  // Plan §7.5 target: 45 KB minified / 15 KB gzip. Measured 48.1 KB / 15.4 KB (2026-10-22); the guard stops growth.
  assert.ok(geo.length <= 50_000, `geo bundle ${geo.length} bytes`);
  assert.ok(gzipSync(geo, { level: 9 }).length <= 16_000, 'geo bundle gzip');
});
