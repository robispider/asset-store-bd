/**
 * Bridge read-only contract test (needs the local database).
 * Discovery, context resolution (including a deliberately unsatisfiable request), preflight and observations
 * must not change users, passwords, ownership manifest, runs, memberships or responsibilities, and must not
 * leak passwords through discovery output.
 */
import assert from 'node:assert/strict';
import { PhpBridge } from '../bridge/php-bridge.mjs';

const bridge = new PhpBridge();
const before = await bridge.fingerprint();

const datasets = await bridge.getDatasets();
assert(!JSON.stringify(datasets).toLowerCase().includes('password'), 'discover output must not expose credentials');

const resolved = await bridge.resolveContext({
  office: { requirements: { operational: true } },
  actors: { employee: { capabilities: { allOf: ['requests.submit'] } } },
  records: { item: { kind: 'consumable', minimumAvailable: 1 } }
});
assert(resolved.actors.employee.id, 'employee must resolve');
assert(resolved.office.id, 'office must resolve');

// Least privilege: the selected employee must not be a superuser.
const assignments = await bridge.observe('actor.assignments', { id: resolved.actors.employee.id });
assert.equal(assignments.is_superadmin, false, 'a superuser must not substitute for an ordinary actor');

// An impossible requirement yields a precise, countable setup failure instead of a guess.
await assert.rejects(
  bridge.resolveContext({ office: {}, actors: { nobody: { capabilities: { allOf: ['catalog.master.manage'] } } }, records: {} }),
  err => err.code === 'fixtures.unsatisfied-requirements' && err.search && err.search.users > 0
);

// Unknown capability aliases fail loudly.
await assert.rejects(
  bridge.resolveContext({ office: {}, actors: { x: { capabilities: { allOf: ['no.such.capability'] } } }, records: {} }),
  err => err.code === 'fixtures.unknown-capability'
);

// Distinct users across actors in one office.
const pair = await bridge.resolveContext({
  office: {},
  actors: { a: { capabilities: { allOf: ['requests.submit'] } }, b: { capabilities: { allOf: ['requests.submit'] } } },
  records: {}
});
assert.notEqual(pair.actors.a.id, pair.actors.b.id, 'actors must be distinct users');

await bridge.observe('request.progress', { id: resolved.actors.employee.id });
await bridge.preflight();

const after = await bridge.fingerprint();
delete before.status; delete after.status;
assert.deepEqual(after, before, 'discovery and observation must not change protected tables');

console.log('Bridge read-only contract tests passed.');
