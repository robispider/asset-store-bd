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
const queues = await bridge.observe('request.queues', { userId: resolved.actors.employee.id, officeId: resolved.office.id });
assert(queues.pendingPrimary >= 0 && queues.pendingFinal >= 0 && queues.awaitingFulfilment >= 0, 'queue projection must expose numeric counts');
const committeeContext = await bridge.resolveContext({
  office: { requirements: { operational: true } },
  actors: { registrar: { capabilities: { allOf: ['committee.manage', 'committee.view'], noneOf: ['committee.types.manage'] } } },
  records: {}
});
const committeeRecord = await bridge.observe('committee.record', {
  officeId: committeeContext.office.id,
  companyId: committeeContext.office.company_id
});
assert(committeeRecord.id && committeeRecord.number, 'a visible committee record must resolve for its office');
assert(committeeRecord.seatCount >= 0 && committeeRecord.orderCount >= 0 && committeeRecord.historyCount >= 0,
  'committee record projection must expose numeric, read-only detail counts');
// Area-scoped actors (activeMembership: false) resolve without an office membership; office actors stay members.
const org = await bridge.resolveContext({
  office: {},
  actors: {
    ict: { role: 'ict_officer', activeMembership: false, capabilities: { allOf: ['organization.office.manage'], noneOf: ['organization.national.manage'] } },
    admin: { role: 'office_admin', capabilities: { allOf: ['organization.office.manage'], noneOf: ['access.matrix'] } }
  },
  records: {}
});
assert.notEqual(org.actors.ict.id, org.actors.admin.id, 'area and office actors must be distinct users');
const adminAssignments = await bridge.observe('actor.assignments', { id: org.actors.admin.id });
assert(adminAssignments.office_ids.map(Number).includes(org.office.id), 'an office actor must be a member of the resolved office');
const registry = await bridge.observe('organization.registry', { userId: org.actors.ict.id });
assert.equal(registry.all, registry.action + registry.live + registry.paused, 'registry tabs must partition the area scope');
assert.equal(registry.action, registry.setup + registry.none, 'needs action = setting up + not onboarded');
const office = await bridge.observe('organization.office', { id: registry.sampleOfficeId ?? org.office.id });
assert(office.done >= 0 && office.done <= 4 && office.badgeStatus === `office_${office.status}`, 'office readiness projection must be consistent');
await bridge.observe('organization.national', {});
assert.equal((await bridge.observe('organization.location', { name: 'LIVE-NO-SUCH-LOCATION' })).count, 0);
await bridge.preflight();

const after = await bridge.fingerprint();
delete before.status; delete after.status;
assert.deepEqual(after, before, 'discovery and observation must not change protected tables');

console.log('Bridge read-only contract tests passed.');
