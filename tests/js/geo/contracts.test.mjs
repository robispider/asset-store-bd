import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { loadSchema, validate, repo } from './schema.mjs';

const fixture = name => JSON.parse(readFileSync(path.join(repo, 'tests/fixtures/geo-view', name), 'utf8'));
const config = loadSchema('gs-geo-config.schema.json');
const data = loadSchema('gs-geo-data.schema.json');

test('tracking preset config fixture validates', () => {
  assert.deepEqual(validate(config, fixture('config.tracking.json')), []);
});

test('data fixtures validate', () => {
  for (const name of ['data.country.json', 'data.no-access.json']) assert.deepEqual(validate(data, fixture(name)), [], name);
});

test('config rejects unknown keys and bad enums', () => {
  const bad = { ...fixture('config.tracking.json'), colour: 'red', intent: 'heatmap' };
  const errors = validate(config, bad);
  assert.ok(errors.some(e => e.includes('$.colour')));
  assert.ok(errors.some(e => e.includes('$.intent')));
});

test('a no_access area cannot be given unknown fields', () => {
  const payload = fixture('data.no-access.json');
  payload.areas['3142'].owner = 'Ministry';
  assert.ok(validate(data, payload).some(e => e.includes('owner')));
});

test('area keys must be numeric ids', () => {
  const payload = fixture('data.country.json');
  payload.areas.dhaka = payload.areas['3142'];
  assert.ok(validate(data, payload).some(e => e.includes('dhaka')));
});
