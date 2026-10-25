import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { ExecutionPlanner } from './planner.mjs';

export const EVIDENCE_LEVELS = ['entry', 'observation', 'function', 'connected'];

export function evidenceLevel(journey) {
  return journey.evidenceLevel || (journey.tags?.includes('entry-check') ? 'entry'
    : journey.execution?.capability === 'write-fixtures' ? 'function' : 'observation');
}

export function hashDefinitions(root) {
  const hash = crypto.createHash('sha256');
  function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
      const file = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(file);
      else hash.update(path.relative(root, file).replaceAll('\\', '/')).update('\0').update(fs.readFileSync(file)).update('\0');
    }
  }
  walk(root);
  return hash.digest('hex');
}

function readJson(file, fallback) {
  try { return JSON.parse(fs.readFileSync(file, 'utf8')); } catch { return fallback; }
}

function origin(url) { try { return new URL(url).origin; } catch { return null; } }

/** Pure projection: never updates old results or treats one target's evidence as another's. */
export function buildCoverage({ loader, runsDir, targetId = 'local', baseUrl, deploymentRevision,
  verificationChannel,
  definitionsHash = hashDefinitions(loader.liveRoot), registry = loader.loadJson(path.join(loader.liveRoot, 'functional-coverage.json')) }) {
  const planner = new ExecutionPlanner(loader);
  const definitions = loader.listJourneys().map(j => loader.loadJourneyByIdOrPath(j.id));
  const histories = new Map();
  let excludedRuns = 0;
  for (const runName of fs.existsSync(runsDir) ? fs.readdirSync(runsDir) : []) {
    const dir = path.join(runsDir, runName);
    const p = readJson(path.join(dir, 'provenance.json'), null);
    const result = readJson(path.join(dir, 'results.json'), null);
    if (!p || !result || (p.kind && p.kind !== 'business') || !Number.isFinite(Date.parse(p.startedAt))) { excludedRuns++; continue; }
    const actualTarget = p.targetId || (origin(p.baseUrl) === 'http://snipeit.local' ? 'local' : null);
    if (actualTarget !== targetId || (baseUrl && origin(baseUrl) !== origin(p.baseUrl))) continue;
    for (const r of result.results || []) {
      const key = r.caseId || r.journeyId;
      const staleReasons = [];
      if (verificationChannel && p.verificationChannel !== verificationChannel) staleReasons.push('verification-channel-differs');
      if (!deploymentRevision) staleReasons.push('requested-deployment-revision-unknown');
      if (p.definitionsHash !== definitionsHash) staleReasons.push('definitions-changed-or-unknown');
      if (!p.deployment?.revision) staleReasons.push('deployment-revision-unknown');
      else if (deploymentRevision && p.deployment.revision !== deploymentRevision) staleReasons.push('deployment-changed');
      const entry = { status: r.status, run: p.runId || runName, startedAt: p.startedAt,
        flaky: Boolean(r.flaky), skippedSteps: r.skippedSteps || 0, cleanup: r.cleanup?.status,
        evidenceLevel: r.evidenceLevel || 'unknown', staleReasons };
      if (!histories.has(key)) histories.set(key, []);
      histories.get(key).push(entry);
    }
  }
  const cases = new Map();
  for (const j of definitions) {
    for (const c of planner.planJourney(j).cases) {
      const history = (histories.get(c.caseId) || []).sort((a, b) => Date.parse(b.startedAt) - Date.parse(a.startedAt) || b.run.localeCompare(a.run));
      const latest = history[0] || null;
      cases.set(c.caseId, { id: c.caseId, journeyId: j.id, evidenceLevel: evidenceLevel(j),
        latest, lastSuccessfulRun: history.find(r => r.status === 'passed')?.run || null,
        executedAtLeastOnce: history.some(r => ['passed', 'failed'].includes(r.status)),
        status: !latest ? 'not-run' : latest.staleReasons.length ? 'stale' : latest.status });
    }
  }
  const functions = registry.functions.map(f => {
    const branches = f.branches.map(branch => {
      const refs = (branch.journeys || []).flatMap(ref => {
        const journeyId = typeof ref === 'string' ? ref : ref.id;
        return [...cases.values()].filter(c => c.journeyId === journeyId && (!ref.caseId || c.id === ref.caseId));
      });
      const required = EVIDENCE_LEVELS.indexOf(branch.requiredEvidence || 'function');
      // All mapped matrix cases are required. An old success never masks the latest failed attempt.
      const complete = refs.length > 0 && refs.every(c => c.status === 'passed' && !c.latest.flaky
        && !c.latest.skippedSteps && c.latest.cleanup !== 'failed'
        && EVIDENCE_LEVELS.indexOf(c.latest.evidenceLevel) >= required);
      const status = f.applicationStatus === 'unimplemented' ? 'application-unimplemented'
        : !refs.length ? 'missing' : complete ? 'passed'
          : refs.some(c => c.status === 'failed') ? 'failed'
            : refs.some(c => c.status === 'blocked') ? 'blocked'
              : refs.some(c => c.status === 'stale') ? 'stale'
                : refs.some(c => c.latest?.flaky) ? 'flaky'
                  : refs.some(c => c.latest?.skippedSteps) ? 'skipped'
                    : refs.some(c => c.status !== 'passed') ? 'not-run' : 'insufficient-evidence';
      return { ...branch, status, cases: refs.map(c => c.id) };
    });
    return { ...f, branches, complete: branches.every(b => b.status === 'passed') };
  });
  const branches = functions.flatMap(f => f.branches);
  return { targetId, baseUrl: baseUrl || null, deploymentRevision: deploymentRevision || null,
    verificationChannel: verificationChannel || null,
    definitionsHash, excludedRuns,
    summary: { functions: functions.length, completeFunctions: functions.filter(f => f.complete).length,
      branches: branches.length, passedBranches: branches.filter(b => b.status === 'passed').length,
      missingBranches: branches.filter(b => b.status === 'missing').length,
      implementedDefinitions: definitions.length, matrixCases: cases.size,
      executedAtLeastOnce: [...cases.values()].filter(c => c.executedAtLeastOnce).length,
      complete: functions.length > 0 && functions.every(f => f.complete) },
    packages: registry.packages.map(p => ({ id: p, functions: functions.filter(f => f.package === p).length,
      complete: functions.filter(f => f.package === p).length > 0 && functions.filter(f => f.package === p).every(f => f.complete) })),
    stories: registry.stories.map(s => ({ id: s, functions: functions.filter(f => f.stories.includes(s)).map(f => f.id),
      complete: functions.some(f => f.stories.includes(s)) && functions.filter(f => f.stories.includes(s)).every(f => f.complete) })),
    functions, cases: [...cases.values()] };
}
