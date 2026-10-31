import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { buildCoverage } from '../core/coverage.mjs';
import { ExecutionPlanner } from '../core/planner.mjs';
import { TestLoader } from '../core/loader.mjs';
import { TestValidator } from '../core/validator.mjs';
import { lockWritingTarget } from '../core/target-lock.mjs';

const journey = { schemaVersion: 1, id: 'request.complete', title: 'Complete request', evidenceLevel: 'connected',
  execution: { capability: 'write-fixtures' }, cleanup: { policy: 'retained-with-report' },
  matrix: [{ kind: 'bulk' }, { kind: 'asset' }], steps: [{ action: 'compare', actual: 1, equals: 1 }] };
const loader = { listJourneys: () => [{ id: journey.id }], loadJourneyByIdOrPath: () => journey };
const registry = { packages: ['custom-requests'], stories: ['US-09'], functions: [{ id: 'receive', package: 'custom-requests', stories: ['US-09'],
  branches: [{ id: 'success', requiredEvidence: 'connected', journeys: [{ id: journey.id }] }] }] };

test('coverage retains every matrix branch and latest failures, excludes other targets and synthetic runs', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'gov-live-coverage-'));
  function run(id, date, statuses, extras = {}) {
    const dir = path.join(root, id); fs.mkdirSync(dir);
    fs.writeFileSync(path.join(dir, 'provenance.json'), JSON.stringify({ runId: id, targetId: 'staging',
      baseUrl: 'https://stage.test', kind: 'business', startedAt: date, definitionsHash: 'current',
      deployment: { revision: 'server-rev' }, ...extras }));
    fs.writeFileSync(path.join(dir, 'results.json'), JSON.stringify({ results: statuses.map((status, i) => ({
      journeyId: journey.id, caseId: `${journey.id}[case_${i}]`, status, evidenceLevel: 'connected', cleanup: { status: 'not-required' }
    })) }));
  }
  const coverage = (args = {}) => buildCoverage({ loader, registry, runsDir: root, targetId: 'staging',
    baseUrl: 'https://stage.test', definitionsHash: 'current', deploymentRevision: 'server-rev', ...args });
  try {
    run('z-old', '2026-10-20T00:00:00Z', ['passed', 'passed']);
    assert.equal(coverage().summary.complete, true);
    run('a-new', '2026-10-21T00:00:00Z', ['passed', 'failed']);
    assert.equal(coverage().summary.complete, false);
    assert.equal(coverage().functions[0].branches[0].status, 'failed');
    assert.equal(coverage().cases[1].lastSuccessfulRun, 'z-old');
    run('local-new', '2026-10-23T00:00:00Z', ['passed', 'passed'], { targetId: 'local' });
    run('synthetic-new', '2026-10-24T00:00:00Z', ['passed', 'passed'], { kind: 'synthetic' });
    assert.equal(coverage().functions[0].branches[0].status, 'failed');
    assert.equal(coverage().summary.executedAtLeastOnce, 2);
    assert.equal(coverage({ deploymentRevision: 'changed' }).functions[0].branches[0].status, 'stale');
    assert.equal(coverage({ deploymentRevision: undefined }).summary.complete, false);
    assert.equal(coverage({ definitionsHash: 'changed' }).summary.complete, false);
    const missing = structuredClone(registry); missing.functions[0].branches.push({ id: 'return', journeys: [] });
    assert.equal(coverage({ registry: missing }).summary.missingBranches, 1);
    assert.equal(coverage({ registry: missing }).summary.complete, false);
    const resultFile = path.join(root, 'a-new', 'results.json');
    for (const override of [{ evidenceLevel: 'entry' }, { skippedSteps: 1 }, { flaky: true }, { cleanup: { status: 'failed' } }]) {
      fs.writeFileSync(resultFile, JSON.stringify({ results: [0, 1].map(i => ({ journeyId: journey.id,
        caseId: `${journey.id}[case_${i}]`, status: 'passed', evidenceLevel: 'connected', ...override })) }));
      assert.equal(coverage().summary.complete, false, JSON.stringify(override));
    }
  } finally {
    assert(path.resolve(root).startsWith(path.resolve(os.tmpdir()) + path.sep));
    fs.rmSync(root, { recursive: true });
  }
});

test('preconditions and postconditions execute in order; flow defaults are values', () => {
  const planner = new ExecutionPlanner({ loadFlow: () => ({ params: { qty: { default: 2 } }, steps: [{ action: 'compare', actual: '${params.qty}', equals: 2 }] }) });
  const j = { ...journey, matrix: undefined, preconditions: [{ action: 'compare', actual: 1, equals: 1 }],
    steps: [{ use: 'example' }], postconditions: [{ action: 'compare', actual: 3, equals: 3 }] };
  const c = planner.planJourney(j).cases[0];
  assert.deepEqual(c.steps.map(s => s._phase), ['preconditions', 'steps', 'postconditions']);
  assert.equal(c.steps[1].actual, '2');
});

test('authored schemas reject ignored fields and invalid pre/post checks before browser execution', () => {
  const validator = new TestValidator(new TestLoader());
  const j = { ...journey, matrix: undefined, preconditions: [{ action: 'unknown-action' }] };
  assert(validator.validateJourney(j).some(e => e.includes('unknown-action')));
  assert(validator.validateJourney({ ...journey, unusedAssertion: true }).some(e => e.includes('unusedAssertion')));
  assert(validator.validateJourney({ ...journey, steps: [{ action: 'assert', target: 'css=body', test: 'unsupported' }] }).some(e => e.includes('Unknown assertion predicate')));
  assert(validator.validateJourney({ ...journey, steps: [{ action: 'observe', check: 'document.state', as: 'doc' }] })
    .some(e => e.includes('no implemented adapter')));
  const registryFile = path.join(process.cwd(), 'tests/live/functional-coverage.json');
  assert.deepEqual(validator.validateSchema('functional-coverage', JSON.parse(fs.readFileSync(registryFile))), []);
});

test('definition snapshots stay fixed and shared-target write runs exclude each other', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'gov-live-snapshot-'));
  try {
    const source = path.join(root, 'tests', 'live'); fs.mkdirSync(source, { recursive: true });
    fs.writeFileSync(path.join(source, 'test.json'), '{"value":1}');
    const snapshot = path.join(root, 'snapshot'); fs.cpSync(source, snapshot, { recursive: true });
    const frozen = new TestLoader(root, snapshot);
    fs.writeFileSync(path.join(source, 'test.json'), '{"value":2}');
    assert.equal(frozen.loadJson(path.join(snapshot, 'test.json')).value, 1);
    assert.throws(() => frozen.loadJson(path.join(root, 'snapshot-sibling', 'test.json')), /Path traversal violation/);
    const release = lockWritingTarget(root, 'http://stage.test', 'first');
    assert.throws(() => lockWritingTarget(root, 'http://stage.test/path', 'second'), error => error.code === 'preflight.target-busy');
    const other = lockWritingTarget(root, 'http://different.test', 'other'); other();
    release(); const again = lockWritingTarget(root, 'http://stage.test', 'second'); again();
  } finally {
    assert(path.resolve(root).startsWith(path.resolve(os.tmpdir()) + path.sep));
    fs.rmSync(root, { recursive: true });
  }
});
