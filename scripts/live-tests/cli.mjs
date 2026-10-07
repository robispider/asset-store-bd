#!/usr/bin/env node

/**
 * GovStore Live Testing CLI
 * Usage:
 *   node scripts/live-tests/cli.mjs validate
 *   node scripts/live-tests/cli.mjs list [--feature <name>]
 *   node scripts/live-tests/cli.mjs discover [--environment <env>]
 *   node scripts/live-tests/cli.mjs plan [--suite <name>] [--test <id>]
 *   node scripts/live-tests/cli.mjs run [--suite <name>] [--test <id>] [--dataset <id>] [--seed <num>] [--headless]
 *   node scripts/live-tests/cli.mjs report --run <runId>
 */

import path from 'node:path';
import fs from 'node:fs';
import os from 'node:os';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

import { TestLoader } from './core/loader.mjs';
import { TestValidator } from './core/validator.mjs';
import { ExecutionPlanner } from './core/planner.mjs';
import { VariableContext } from './core/variables.mjs';
import { LocatorResolver } from './core/locators.mjs';
import { AssertionEngine } from './actions/assertions.mjs';
import { CaptureEngine } from './actions/capture.mjs';
import { BrowserActionEngine } from './actions/browser.mjs';
import { StepInterpreter } from './core/interpreter.mjs';
import { EventLogger } from './reporters/events.mjs';
import { ResultsReporter } from './reporters/results.mjs';
import { renderHtmlReport } from './reporters/html.mjs';
import { spawnSync } from 'node:child_process';
import { PhpBridge } from './bridge/php-bridge.mjs';
import { DeterministicRandom, resolveData } from './core/generators.mjs';

const LOCAL_HOST_PATTERN = /^(localhost|127\.0\.0\.1|\[::1\]|[a-z0-9-]+\.(local|localhost|test))$/i;

/** Initial scope is local/nonproduction only; a CLI base URL never authorizes another installation. */
export function assertLocalTarget(baseUrl) {
  const host = new URL(baseUrl).hostname;
  const extra = (process.env.LIVE_TEST_ALLOWED_HOSTS || '').split(',').map(h => h.trim().toLowerCase()).filter(Boolean);
  if (!LOCAL_HOST_PATTERN.test(host) && !extra.includes(host.toLowerCase())) {
    throw new Error(`Target host '${host}' is not on the local environment allowlist`);
  }
}

function gitInfo() {
  const git = (...a) => {
    try { return execFileSync('git', a, { encoding: 'utf8' }).trim(); } catch { return null; }
  };
  return {
    revision: git('rev-parse', 'HEAD'),
    dirtyFiles: (git('status', '--porcelain') || '').split('\n').filter(Boolean).length
  };
}

function hashTree(dir) {
  const hash = crypto.createHash('sha256');
  const walk = (d) => {
    if (!fs.existsSync(d)) return;
    const entries = fs.readdirSync(d, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name));
    for (const e of entries) {
      const p = path.join(d, e.name);
      if (e.isDirectory()) walk(p);
      else hash.update(e.name).update(fs.readFileSync(p));
    }
  };
  walk(dir);
  return hash.digest('hex').slice(0, 16);
}

function readJson(file, fallback) {
  try { return JSON.parse(fs.readFileSync(file, 'utf8')); } catch { return fallback; }
}

function baseJourneyId(id) {
  return String(id || '').replace(/\[case_\d+\]$/, '');
}

function writeHtml(runDir) {
  const html = renderHtmlReport({
    results: readJson(path.join(runDir, 'results.json'), { summary: {}, results: [] }),
    provenance: readJson(path.join(runDir, 'provenance.json'), {}),
    cleanup: readJson(path.join(runDir, 'cleanup.json'), {}),
    ownership: readJson(path.join(runDir, 'ownership.json'), [])
  });
  fs.writeFileSync(path.join(runDir, 'report.html'), html, 'utf8');
}

function parseArgs(args) {
  const parsed = { command: args[0] || 'help', options: {} };
  for (let i = 1; i < args.length; i++) {
    const arg = args[i];
    if (arg.startsWith('--')) {
      const key = arg.slice(2);
      const next = args[i + 1];
      if (next && !next.startsWith('--')) {
        parsed.options[key] = next;
        i++;
      } else {
        parsed.options[key] = true;
      }
    }
  }
  return parsed;
}

async function main() {
  const { command, options } = parseArgs(process.argv.slice(2));
  const baseDir = process.cwd();
  const loader = new TestLoader(baseDir);
  const validator = new TestValidator(loader);
  const planner = new ExecutionPlanner(loader);
  const phpBridge = new PhpBridge();

  switch (command) {
    case 'validate': {
      console.log('Validating Live Testing JSON contracts and journeys...');
      let totalChecked = 0;
      let totalErrors = 0;

      const journeys = loader.listJourneys();
      for (const j of journeys) {
        totalChecked++;
        try {
          const journeyData = loader.loadJourneyByIdOrPath(j.id);
          const errors = validator.validateJourney(journeyData);
          if (errors.length > 0) {
            console.error(`[FAIL] ${j.id}:`);
            errors.forEach(e => console.error(`  - ${e}`));
            totalErrors += errors.length;
          } else {
            console.log(`[OK] Journey: ${j.id}`);
          }
        } catch (err) {
          console.error(`[ERROR] ${j.id}: ${err.message}`);
          totalErrors++;
        }
      }

      const suites = loader.listSuites();
      for (const s of suites) {
        totalChecked++;
        try {
          const suiteData = loader.loadSuite(s.id);
          const errors = validator.validateSuite(suiteData);
          if (errors.length > 0) {
            console.error(`[FAIL] Suite ${s.id}:`);
            errors.forEach(e => console.error(`  - ${e}`));
            totalErrors += errors.length;
          } else {
            console.log(`[OK] Suite: ${s.id}`);
          }
        } catch (err) {
          console.error(`[ERROR] Suite ${s.id}: ${err.message}`);
          totalErrors++;
        }
      }

      console.log(`\nValidation complete. Checked ${totalChecked} definitions. Errors: ${totalErrors}`);
      process.exit(totalErrors > 0 ? 1 : 0);
      break;
    }

    case 'list': {
      const featureFilter = options.feature;
      console.log('Available Live Test Journeys:');
      const journeys = loader.listJourneys();
      for (const j of journeys) {
        if (!featureFilter || j.features.includes(featureFilter)) {
          console.log(`  - [${j.capability}] ${j.id} : "${j.title}" (Features: ${j.features.join(', ') || 'none'})`);
        }
      }

      console.log('\nAvailable Suites:');
      const suites = loader.listSuites();
      for (const s of suites) {
        console.log(`  - ${s.id} : "${s.title}" (${s.journeys.length} journeys)`);
      }
      break;
    }

    case 'discover': {
      console.log('Discovering available seeded datasets and fixture status...');
      try {
        const datasets = await phpBridge.getDatasets();
        console.log(`Found ${datasets.length} ready experiment dataset(s):`);
        for (const ds of datasets) {
          console.log(`  - ID: ${ds.id}`);
          console.log(`    Label: "${ds.label}" | Profile: ${ds.profile} | Phase: ${ds.phase} | Created: ${ds.created_at}`);
        }
      } catch (err) {
        console.error(`Discovery error: ${err.message}`);
        process.exit(1);
      }
      break;
    }

    case 'plan': {
      const suiteId = options.suite;
      const testId = options.test;

      if (suiteId) {
        const suite = loader.loadSuite(suiteId);
        const plan = planner.planSuite(suite);
        console.log(JSON.stringify(plan, null, 2));
      } else if (testId) {
        const journey = loader.loadJourneyByIdOrPath(testId);
        const plan = planner.planJourney(journey);
        console.log(JSON.stringify(plan, null, 2));
      } else {
        console.error('Specify --suite <suiteId> or --test <journeyId>');
        process.exit(2);
      }
      break;
    }

    case 'run': {
      const suiteId = options.suite || 'smoke';
      const testId = options.test;
      const datasetOverride = options.dataset;
      const seed = options.seed ? parseInt(options.seed, 10) : 20261006;
      const baseUrl = options.baseUrl || 'http://snipeit.local';
      const headless = !options.headed;
      const slowMo = options['slow-mo'] ? Math.min(parseInt(options['slow-mo'], 10) || 0, 2000) : 0;

      try {
        assertLocalTarget(baseUrl);
      } catch (err) {
        console.error(err.message);
        process.exit(2);
      }

      const runId = /^run_[A-Za-z0-9_-]+$/.test(String(options['run-id'] || ''))
        ? options['run-id']
        : `run_${Date.now()}_${new DeterministicRandom(seed).uniqueMarkedText('R', 4)}`;
      const runDir = path.join(baseDir, 'storage', 'app', 'private', 'live-tests', runId);
      fs.mkdirSync(runDir, { recursive: true });

      // Uniqueness is separate from the deterministic seed: replays reuse seeded inputs but never collide with earlier runs.
      const uniqueSuffix = Date.now().toString(36).slice(-5).toUpperCase();
      const eventLogger = new EventLogger(runDir);
      const resultsReporter = new ResultsReporter(runDir);

      console.log(`\nStarting Live Test Execution`);
      console.log(`Run ID      : ${runId}`);
      console.log(`Target URL  : ${baseUrl}`);
      console.log(`Artifact Dir: ${runDir}\n`);

      eventLogger.log('run_started', { runId, suiteId, testId, tests: options.tests || null, seed, baseUrl });

      let interrupted = false;
      process.on('SIGINT', () => { interrupted = true; });
      process.on('SIGTERM', () => { interrupted = true; });

      // Ownership ledger: new identities created by journeys (none for observe-only runs). Cleanup is
      // supported-workflow only; anything unreversible is listed as retained, never repaired.
      const ownership = [];
      const cleanup = { policy: 'no-business-records-created', status: 'not-required', retained: [] };
      const provenance = {
        runId,
        startedAt: new Date().toISOString(),
        baseUrl,
        headed: !headless,
        seed,
        uniqueSuffix,
        suiteId,
        testId: testId || null,
        tests: options.tests ? String(options.tests).split(',') : null,
        datasetOverride: datasetOverride || null,
        application: gitInfo(),
        definitionsHash: hashTree(path.join(baseDir, 'tests', 'live')),
        runtime: { node: process.version, platform: process.platform, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone, os: os.release() },
        selectedContexts: []
      };
      const writePrivate = (name, data) => fs.writeFileSync(path.join(runDir, name), JSON.stringify(data, null, 2), 'utf8');

      // Determine list of journeys to run
      let journeyIds = [];
      if (options.tests) {
        // Comma-separated subset; keep suite order when --suite is also given, validate every id first.
        const wanted = String(options.tests).split(',').map(x => x.trim()).filter(Boolean);
        const known = new Set(loader.listJourneys().map(j => j.id));
        const unknown = wanted.filter(w => !known.has(w));
        if (unknown.length) { console.error(`Unknown journey(s): ${unknown.join(', ')}`); process.exit(2); }
        journeyIds = [...new Set(wanted)];
      } else if (testId) {
        journeyIds = [testId];
      } else {
        const suite = loader.loadSuite(suiteId);
        journeyIds = suite.journeys;
      }

      // Environment preflight: nonproduction, effective database/mode/mail/queue, ready datasets.
      let preflight;
      try {
        preflight = await phpBridge.preflight();
      } catch (err) {
        console.error(`Preflight failed: ${err.message}`);
        process.exit(2);
      }
      if (preflight.production) {
        console.error('Refusing to run: application reports a production environment');
        process.exit(2);
      }
      provenance.preflight = { ...preflight, status: undefined };
      const allowWrites = Boolean(options['allow-writes']);
      const retryReadonly = options['retry-readonly'] ? Math.min(parseInt(options['retry-readonly'], 10) || 0, 3) : 0;
      const allowExternalMail = Boolean(options['allow-external-mail']);
      const sinkSafe = allowExternalMail || ['log', 'array', 'null', 'mailpit', 'smtp-local'].includes(String(preflight.mailer));
      provenance.mailPolicy = { mailer: preflight.mailer, externalMailOverride: allowExternalMail, note: allowExternalMail ? 'Operator accepted external mail driver; seeded actors use undeliverable example.invalid addresses; delivery is not tested.' : 'local sink required' };

      const browser = await chromium.launch({ headless, slowMo });
      const artifactsDir = path.join(runDir, 'artifacts');

      try {
        for (const jId of journeyIds) {
          if (interrupted) {
            resultsReporter.recordTestResult({ journeyId: jId, status: 'interrupted', error: 'Run interrupted before start' });
            continue;
          }
          console.log(`\nExecuting: ${jId}...`);
          const journey = loader.loadJourneyByIdOrPath(jId);
          const block = (code, error) => {
            console.error(`  [BLOCKED] ${code}: ${error}`);
            resultsReporter.recordTestResult({ journeyId: jId, status: 'blocked', code, error });
          };

          const valErrors = validator.validateJourney(journey);
          if (valErrors.length > 0) {
            valErrors.forEach(e => console.error(`  - ${e}`));
            block('schema.invalid', `Validation failed: ${valErrors.join('; ')}`);
            continue;
          }

          const capability = journey.execution?.capability || 'observe';
          if (capability === 'write-fixtures') {
            if (!allowWrites) {
              block('policy.writes-not-allowed', 'write-fixtures journey requires --allow-writes');
              continue;
            }
            if (!sinkSafe) {
              block('preflight.mail-sink-unsafe', `mail driver '${preflight.mailer}' is not a local sink; refusing to trigger notifications (override with --allow-external-mail; delivery is never tested)`);
              continue;
            }
          }
          const wantMode = journey.execution?.accessMode;
          if (wantMode && wantMode !== 'any' && wantMode !== preflight.accessMode) {
            block('preflight.access-mode', `journey requires access mode '${wantMode}', installation is '${preflight.accessMode}'`);
            continue;
          }

          console.log(`  Resolving matching seeded context...`);
          let resolved = null;
          try {
            resolved = await phpBridge.resolveContext(journey.fixtures, datasetOverride);
          } catch (err) {
            console.error(`  [SETUP ERROR] Fixture resolution failed: ${err.message}`);
            resultsReporter.recordTestResult({
              journeyId: jId,
              status: 'blocked',
              code: err.code || 'fixtures.setup-failed',
              search: err.search,
              error: err.message
            });
            continue;
          }

          console.log(`  Context pinned: Office #${resolved.office.id} ("${resolved.office.name}")`);
          const pinned = {
            journeyId: jId,
            dataset: resolved.run?.id,
            office: resolved.office.id,
            actors: Object.fromEntries(Object.entries(resolved.actors).map(([k, v]) => [k, v.id])),
            records: Object.fromEntries(Object.entries(resolved.records || {}).map(([k, v]) => [k, v.id])),
            search: resolved.search
          };
          provenance.selectedContexts.push(pinned);

          const plan = planner.planJourney(journey);
          const activeLanguage = journey.execution?.language || 'en-US';

          for (let caseIndex = 0; caseIndex < plan.cases.length; caseIndex++) {
            const testCase = plan.cases[caseIndex];
            if (interrupted) {
              resultsReporter.recordTestResult({ journeyId: testCase.caseId, status: 'interrupted', error: 'Run interrupted' });
              continue;
            }

            // Per-case deterministic data; uniqueness comes from the seed and case index.
            const caseRng = new DeterministicRandom(seed + caseIndex);
            const { values: dataValues, generated } = resolveData(testCase.data, caseRng, uniqueSuffix);
            provenance.generatedData = { ...(provenance.generatedData || {}), [testCase.caseId]: generated };

            // Re-plan with generated values so ${params.*} resolve to the concrete inputs.
            const concrete = planner.planJourney({ ...journey, data: dataValues, matrix: journey.matrix ? [{ ...journey.matrix[caseIndex] }] : undefined }).cases[0];
            concrete.caseId = testCase.caseId;

            // Read-only journeys may be retried (--retry-readonly N) because a transient server error does not change
            // state; the result is flagged flaky and never counts as a clean first-attempt pass. Write journeys never retry.
            const maxAttempts = capability === 'observe' ? 1 + retryReadonly : 1;
            const ledgerBefore = ownership.length;
            const attemptLog = [];
            let result;

            for (let attempt = 1; attempt <= maxAttempts; attempt++) {
              const varContext = new VariableContext({
                run: resolved.run,
                actors: resolved.actors,
                fixtures: { office: resolved.office, ...resolved.records },
                data: { ...dataValues, ...(journey.matrix?.[caseIndex] || {}) }
              });

              const locators = new LocatorResolver(loader, activeLanguage);
              const browserActions = new BrowserActionEngine(new AssertionEngine(), new CaptureEngine(), locators);
              const caseContexts = [];
              const interpreter = new StepInterpreter(browserActions, phpBridge, eventLogger, {
                ledger: ownership,
                accessMode: preflight.accessMode,
                artifactsDir,
                newPage: async () => {
                  const c = await browser.newContext();
                  caseContexts.push(c);
                  return await c.newPage();
                }
              });

              const context = await browser.newContext();
              caseContexts.push(context);
              const page = await context.newPage();

              try {
                result = await interpreter.executeJourney(page, concrete, varContext, baseUrl);
              } finally {
                for (const c of caseContexts) await c.close().catch(() => {});
              }
              attemptLog.push({ attempt, status: result.status, error: result.error });
              if (result.status === 'passed') break;
            }

            result.pinned = pinned;
            result.generatedData = generated;
            result.attempts = attemptLog.length;
            if (attemptLog.length > 1) {
              result.attemptLog = attemptLog;
              if (result.status === 'passed') result.flaky = true;
            }
            resultsReporter.recordTestResult(result);

            // Anything recorded in the ledger and not cleaned up through a supported workflow is retained.
            for (const entry of ownership.slice(ledgerBefore)) {
              if (entry.cleanup !== 'removed') cleanup.retained.push(entry);
            }
          }
        }
      } finally {
        await browser.close();
      }

      cleanup.policy = ownership.length ? 'supported-workflow-or-retain' : 'no-business-records-created';
      cleanup.status = !ownership.length ? 'not-required' : cleanup.retained.length ? 'retained-records' : 'clean';

      const finalPayload = resultsReporter.save();
      provenance.finishedAt = new Date().toISOString();
      writePrivate('provenance.json', provenance);
      writePrivate('ownership.json', ownership);
      writePrivate('cleanup.json', cleanup);
      writeHtml(runDir);
      resultsReporter.printSummary();

      // 3 interrupted/inconclusive, then 1 failure, then 2 blocked coverage, else 0.
      const sm = finalPayload.summary;
      const exitCode = (interrupted || sm.interrupted > 0 || sm.inconclusive > 0) ? 3
        : sm.failed > 0 ? 1
        : sm.blocked > 0 ? 2
        : 0;
      process.exit(exitCode);
      break;
    }

    case 'report': {
      const runId = options.run;
      if (!runId) {
        console.error('Specify --run <runId>');
        process.exit(1);
      }
      const runDir = path.join(baseDir, 'storage', 'app', 'private', 'live-tests', runId);
      const resultsFile = path.join(runDir, 'results.json');
      if (!fs.existsSync(resultsFile)) {
        console.error(`Results file not found: ${resultsFile}`);
        process.exit(1);
      }
      if (options.html) {
        writeHtml(runDir);
        console.log(path.join(runDir, 'report.html'));
      } else {
        console.log(JSON.stringify(JSON.parse(fs.readFileSync(resultsFile, 'utf8')), null, 2));
      }
      break;
    }

    case 'replay': {
      // Re-run journeys of a previous run with the same seed/dataset preference. Fixtures are re-resolved
      // fresh, a new uniqueness suffix is used, and a write journey is never resubmitted against old state.
      const runId = options.run;
      if (!runId) { console.error('Specify --run <runId>'); process.exit(2); }
      const runDir = path.join(baseDir, 'storage', 'app', 'private', 'live-tests', runId);
      const prev = readJson(path.join(runDir, 'results.json'), null);
      const prov = readJson(path.join(runDir, 'provenance.json'), null);
      if (!prev || !prov) { console.error('Run not found or incomplete'); process.exit(2); }
      const selected = prev.results.filter(r => !options['failed-only'] || ['failed', 'blocked', 'interrupted'].includes(r.status));
      const ids = [...new Set(selected.map(r => baseJourneyId(r.journeyId)))].filter(Boolean);
      if (!ids.length) { console.log('Nothing to replay.'); break; }
      let worst = 0;
      for (const id of ids) {
        const args = [path.join(baseDir, 'scripts', 'live-tests', 'cli.mjs'), 'run', '--test', id, '--seed', String(prov.seed), '--baseUrl', prov.baseUrl];
        if (prov.datasetOverride) args.push('--dataset', prov.datasetOverride);
        if (loader.loadJourneyByIdOrPath(id).execution?.capability === 'write-fixtures') {
          if (!options['allow-writes']) { console.log('Skipping ' + id + ': replaying a write journey needs --allow-writes (fresh fixtures and baseline are used)'); continue; }
          args.push('--allow-writes');
          if (options['allow-external-mail']) args.push('--allow-external-mail');
        }
        const res = spawnSync(process.execPath, args, { stdio: 'inherit' });
        worst = Math.max(worst, res.status ?? 3);
      }
      process.exit(worst);
      break;
    }

    case 'dashboard': {
      const { startDashboard } = await import('./dashboard/server.mjs');
      await startDashboard({ baseDir, port: options.port ? parseInt(options.port, 10) : 4780 });
      return; // keep the server alive
    }

    case 'coverage': {
      // Distinct counts from definitions, the feature catalog and the most recent result of each journey.
      const catalog = readJson(path.join(baseDir, 'tests', 'live', 'feature-catalog.json'), { features: [] });
      const defined = loader.listJourneys().map(j => j.id);
      const runsDir = path.join(baseDir, 'storage', 'app', 'private', 'live-tests');
      const latest = {};
      if (fs.existsSync(runsDir)) {
        for (const d of fs.readdirSync(runsDir).filter(n => n.startsWith('run_')).sort()) {
          for (const r of readJson(path.join(runsDir, d, 'results.json'), { results: [] }).results) {
            latest[baseJourneyId(r.journeyId)] = { status: r.status, run: d };
          }
        }
      }
      console.log(JSON.stringify({
        implementedDefinitions: defined.length,
        executedAtLeastOnce: defined.filter(id => latest[id] && ['passed', 'failed'].includes(latest[id].status)).length,
        lastRunPassed: defined.filter(id => latest[id]?.status === 'passed').length,
        lastRunBlocked: defined.filter(id => latest[id]?.status === 'blocked').length,
        neverRun: defined.filter(id => !latest[id]),
        featuresWithoutJourneys: catalog.features.filter(f => !f.journeys?.length).map(f => f.id),
        perJourney: Object.fromEntries(defined.map(id => [id, latest[id] || 'never-run']))
      }, null, 2));
      break;
    }

    default:
      console.log(`
GovStore Live Journey Testing CLI

Commands:
  validate                          Validate all schemas, journeys, flows, and contracts
  list [--feature <name>]           List all available journeys and suites
  discover                          Discover seeded datasets via read-only PHP bridge
  plan --suite <id> | --test <id>   View expanded execution plan without browser mutations
  run [--suite <id>] [--test <id>] [--tests a,b,c] [--baseUrl <url>] [--headed] [--slow-mo ms]  Run live browser journeys
  dashboard [--port 4780]           Local dashboard: start runs, watch progress, pick server URL
  report --run <runId> [--html]    View results, or write report.html
  replay --run <runId> [--failed-only] [--allow-writes]  Re-run journeys with fresh fixtures
  coverage                          Defined / executed / passed / blocked / missing counts
`);
      break;
  }
}

main().catch(err => {
  console.error(`Fatal error: ${err.message}`);
  process.exit(1);
});
