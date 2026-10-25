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
import { assertMatchingTarget } from './core/target.mjs';
import { loadTargets } from './core/targets.mjs';
import { BrowserBridge, browserJourneyBlock } from './bridge/browser-bridge.mjs';
import { redactString } from './reporters/redaction.mjs';
import { buildCoverage, hashDefinitions, evidenceLevel } from './core/coverage.mjs';
import { checkInventory, reconcileInventory } from './inventory.mjs';
import { lockWritingTarget } from './core/target-lock.mjs';

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
  return hashDefinitions(dir);
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
    coverage: readJson(path.join(runDir, 'coverage.json'), null),
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
  let loader = new TestLoader(baseDir);
  let validator = new TestValidator(loader);
  let planner = new ExecutionPlanner(loader);
  const phpBridge = new PhpBridge();

  switch (command) {
    case 'inventory': {
      if (!options.routes) throw new Error('inventory requires --routes <private route:list JSON file>');
      const registryFile = path.join(loader.liveRoot, 'functional-coverage.json');
      const routes = JSON.parse(fs.readFileSync(String(options.routes), 'utf8').replace(/^\uFEFF/, ''));
      const registry = reconcileInventory(routes, readJson(registryFile, {}));
      if (options.write) fs.writeFileSync(registryFile, JSON.stringify(registry, null, 2) + '\n');
      console.log(JSON.stringify({ functions: registry.functions.length, packages: registry.packages.length, written: Boolean(options.write) }));
      break;
    }
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

      const registry = loader.loadJson(path.join(loader.liveRoot, 'functional-coverage.json'));
      const inventoryErrors = [...validator.validateSchema('functional-coverage', registry), ...checkInventory(loader, registry)];
      totalChecked++;
      totalErrors += inventoryErrors.length;
      for (const e of inventoryErrors) console.error(`[FAIL] Functional coverage: ${e}`);
      if (!inventoryErrors.length) console.log(`[OK] Functional inventory: ${registry.functions.length} functions`);

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
      console.log('Discovering ready datasets through the application interface...');
      const targets = loadTargets(baseDir, options['targets-file']);
      const target = targets.find(t => t.id === (options.target || 'local'));
      if (!target) throw new Error('Select a configured nonproduction target with --target.');
      const browser = await chromium.launch({ headless: true });
      const bridge = new BrowserBridge({ browser, profile: target, baseUrl: target.baseUrl });
      try {
        await bridge.preflight();
        const datasets = bridge.datasets;
        console.log(`Found ${datasets.length} ready experiment dataset(s):`);
        for (const ds of datasets) {
          console.log(`  - ID: ${ds.id}`);
          console.log(`    Label: "${ds.label}" | Status: ${ds.status} | Source: application interface`);
        }
      } catch (err) {
        console.error(`Discovery error: ${err.message}`);
        process.exitCode = 1;
      } finally { await bridge.close(); await browser.close(); }
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
      const targets = loadTargets(baseDir, options['targets-file']);
      const selectedTarget = options.target ? targets.find(target => target.id === options.target)
        : targets.find(target => new URL(target.baseUrl).origin === new URL(options.baseUrl || 'http://snipeit.local').origin);
      if (options.target && !selectedTarget) throw new Error('The selected target is not defined in the private target file.');
      if (!selectedTarget) throw new Error('Interface-only live runs require a configured nonproduction target and browser login. No database fallback is used.');
      const seed = options.seed ? parseInt(options.seed, 10) : 20261006;
      const baseUrl = options.baseUrl || selectedTarget?.baseUrl || 'http://snipeit.local';
      const headless = !options.headed;
      const slowMo = options['slow-mo'] ? Math.min(parseInt(options['slow-mo'], 10) || 0, 2000) : 0;

      try {
        // A configured target explicitly identifies the authorized installation; ad-hoc URLs still use the allowlist.
        if (selectedTarget) assertMatchingTarget(baseUrl, { appUrl: selectedTarget.baseUrl });
        else assertLocalTarget(baseUrl);
      } catch (err) {
        console.error(err.message);
        process.exit(2);
      }

      const runId = /^run_[A-Za-z0-9_-]+$/.test(String(options['run-id'] || ''))
        ? options['run-id']
        : `run_${Date.now()}_${new DeterministicRandom(seed).uniqueMarkedText('R', 4)}`;
      const runDir = path.join(baseDir, 'storage', 'app', 'private', 'live-tests', runId);
      fs.mkdirSync(runDir, { recursive: true });
      const definitionSnapshot = path.join(runDir, 'definitions');
      fs.cpSync(loader.liveRoot, definitionSnapshot, { recursive: true, errorOnExist: true });
      loader = new TestLoader(baseDir, definitionSnapshot);
      validator = new TestValidator(loader);
      planner = new ExecutionPlanner(loader);

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
        definitionsHash: hashTree(loader.liveRoot),
        definitionsSource: 'private-run-snapshot',
        runtime: { node: process.version, platform: process.platform, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone, os: os.release() },
        selectedContexts: []
      };
      provenance.kind = 'business';
      provenance.runner = provenance.application;
      provenance.deployment = selectedTarget?.environment === 'local' || !selectedTarget
        ? { revision: provenance.runner.revision, source: 'authorized-local-workspace', dirtyFiles: provenance.runner.dirtyFiles }
        : { revision: selectedTarget.deploymentRevision || null, source: selectedTarget.deploymentRevision ? 'operator-target-profile' : 'unknown' };
      provenance.targetId = selectedTarget?.id || null;
      provenance.fixtureSource = 'target-browser';
      // Local-database mode: a configured local target for this same server supplies the superadmin that
      // "supplied superuser" actors need (dataset users are never superadmins), as --target does.
      if (!selectedTarget) {
        const origin = new URL(baseUrl).origin;
        const localProfile = targets.find(target => target.environment === 'local' && new URL(target.baseUrl).origin === origin);
        if (localProfile) {
          phpBridge.setSuperadmin(localProfile.bootstrap);
          provenance.superadminSource = `target:${localProfile.id}`;
        }
      }
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
      let browser;
      let fixtureBridge = phpBridge;
      const blockRun = async (code, message) => {
        console.error(`[BLOCKED] ${code}: ${message}`);
        for (const journeyId of journeyIds) {
          resultsReporter.recordTestResult({ journeyId, status: 'blocked', code, error: message });
        }
        eventLogger.log('run_blocked', { runId, code, error: message });
        provenance.finishedAt = new Date().toISOString();
        provenance.blocked = { code, error: message };
        resultsReporter.save();
        writePrivate('provenance.json', provenance);
        cleanup.retained = ownership.filter(entry => entry.cleanup !== 'removed');
        cleanup.policy = ownership.length ? 'supported-workflow-or-retain' : 'no-business-records-created';
        cleanup.status = ownership.length ? 'retained-records' : 'not-required';
        writePrivate('ownership.json', ownership);
        writePrivate('cleanup.json', cleanup);
        writeHtml(runDir);
        resultsReporter.printSummary();
        await fixtureBridge.close?.().catch(() => {});
        await browser?.close().catch(() => {});
        process.exit(2);
      };
      let preflight;
      try {
        if (options['allow-writes']) {
          const unlock = lockWritingTarget(path.dirname(runDir), baseUrl, runId);
          process.once('exit', unlock);
        }
        if (selectedTarget) {
          browser = await chromium.launch({ headless, slowMo });
          const aliases = loader.loadJson(path.join(loader.liveRoot, 'capabilities.json')).capabilities;
          fixtureBridge = new BrowserBridge({ browser, baseUrl, profile: selectedTarget, aliases,
            allowWrites: Boolean(options['allow-writes']), ownership, eventLogger });
          provenance.verificationChannel = 'browser-interface';
        }
        preflight = await fixtureBridge.preflight();
      } catch (err) {
        await blockRun(err.code || 'preflight.bridge-failed', `Preflight failed: ${redactString(err.message)}`);
      }
      provenance.preflight = { ...preflight, status: undefined };
      if (preflight.production) {
        await blockRun('preflight.production', 'Refusing to run: application reports a production environment');
      }
      try {
        provenance.targetCheck = assertMatchingTarget(baseUrl, preflight);
      } catch (err) {
        await blockRun(err.code, err.message);
      }
      const allowWrites = Boolean(options['allow-writes']);
      const retryReadonly = options['retry-readonly'] ? Math.min(parseInt(options['retry-readonly'], 10) || 0, 3) : 0;
      const allowExternalMail = Boolean(options['allow-external-mail']);
      const sinkSafe = allowExternalMail || ['log', 'array', 'null', 'mailpit', 'smtp-local'].includes(String(preflight.mailer));
      provenance.mailPolicy = { mailer: preflight.mailer, externalMailOverride: allowExternalMail, note: allowExternalMail ? 'Operator accepted external mail driver; seeded actors use undeliverable example.invalid addresses; delivery is not tested.' : 'local sink required' };

      browser ||= await chromium.launch({ headless, slowMo });
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
          if (selectedTarget) {
            const missing = browserJourneyBlock(journey, planner.planJourney(journey), preflight.accessMode, false);
            if (missing) { block(...missing); continue; }
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
            resolved = await fixtureBridge.resolveContext(journey.fixtures, datasetOverride);
          } catch (err) {
            console.error(`  [SETUP ERROR] Fixture resolution failed: ${err.message}`);
            const rejected = Object.entries(err.search?.rejected || {});
            if (rejected.length) console.error(`  Candidates rejected: ${rejected.map(([reason, count]) => `${reason} ×${count}`).join(', ')}`);
            if (err.search?.rejected?.['superadmin-not-supplied']) {
              console.error('  This journey needs a superadmin: add a local target for this server (bootstrap login) to storage/app/private/live-tests/targets.json, or run with --target.');
            }
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
            actors: Object.fromEntries(Object.entries(resolved.actors).map(([k, v]) => [k, v.id ?? v.username])),
            records: Object.fromEntries(Object.entries(resolved.records || {}).map(([k, v]) => [k, v.id])),
            search: resolved.search
          };
          provenance.selectedContexts.push(pinned);

          const plan = planner.planJourney(journey);
          if (selectedTarget && Object.entries(resolved.actors).some(([alias, actor]) => !actor.id
            && JSON.stringify(plan).includes('${actors.' + alias + '.id}'))) {
            block('fixtures.user-id-required', 'This journey requires an actor identity that the target My access page did not expose. Its interface identity adapter must be completed; database access is not a prerequisite.');
            continue;
          }
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
              const interpreter = new StepInterpreter(browserActions, fixtureBridge, eventLogger, {
                ledger: ownership,
                accessMode: preflight.accessMode,
                artifactsDir,
                interfaceOnly: true,
                newPage: async () => {
                  const c = selectedTarget ? await fixtureBridge.newContext() : await browser.newContext();
                  caseContexts.push(c);
                  return await c.newPage();
                }
              });

              const context = selectedTarget ? await fixtureBridge.newContext() : await browser.newContext();
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
            result.evidenceLevel = evidenceLevel(journey);
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
        await fixtureBridge.close?.();
        await browser.close();
      }

      cleanup.policy = ownership.length ? 'supported-workflow-or-retain' : 'no-business-records-created';
      cleanup.retained = ownership.filter(entry => entry.cleanup !== 'removed');
      cleanup.status = !ownership.length ? 'not-required' : cleanup.retained.length ? 'retained-records' : 'clean';

      const finalPayload = resultsReporter.save();
      provenance.finishedAt = new Date().toISOString();
      writePrivate('provenance.json', provenance);
      writePrivate('ownership.json', ownership);
      writePrivate('cleanup.json', cleanup);
      writePrivate('coverage.json', buildCoverage({ loader, runsDir: path.dirname(runDir),
        targetId: provenance.targetId || 'local', baseUrl, deploymentRevision: provenance.deployment?.revision,
        verificationChannel: 'browser-interface' }));
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
        if (prov.targetId) args.push('--target', prov.targetId);
        if (options['targets-file']) args.push('--targets-file', options['targets-file']);
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
      const targetId = options.target || 'local';
      const target = loadTargets(baseDir, options['targets-file']).find(t => t.id === targetId);
      if (options.target && !target) throw new Error('Unknown configured target');
      const coverage = buildCoverage({ loader, runsDir: path.join(baseDir, 'storage', 'app', 'private', 'live-tests'),
        targetId, baseUrl: target?.baseUrl || 'http://snipeit.local',
        verificationChannel: 'browser-interface',
        deploymentRevision: options.revision || target?.deploymentRevision || (targetId === 'local' ? gitInfo().revision : undefined) });
      console.log(JSON.stringify(options.summary ? { targetId, summary: coverage.summary, packages: coverage.packages, stories: coverage.stories } : coverage, null, 2));
      break;
    }

    default:
      console.log(`
GovStore Live Journey Testing CLI

Commands:
  validate                          Validate all schemas, journeys, flows, and contracts
  list [--feature <name>]           List all available journeys and suites
  discover [--target id]            Discover ready datasets through the application interface
  plan --suite <id> | --test <id>   View expanded execution plan without browser mutations
  run [--suite <id>] [--test <id>] [--tests a,b,c] [--baseUrl <url>] [--headed] [--slow-mo ms]  Run live browser journeys
      [--target <id>] [--targets-file <private-file>]  Use a configured nonproduction server and private browser login
  dashboard [--port 4780]           Local dashboard: start runs, watch progress, pick server URL
  report --run <runId> [--html]    View results, or write report.html
  replay --run <runId> [--failed-only] [--allow-writes]  Re-run journeys with fresh fixtures
  inventory --routes <file> [--write]  Reconcile effective route inventory (read-only unless --write)
  coverage [--target id] [--revision ref] [--summary]  Target-specific function/branch acceptance
`);
      break;
  }
}

main().catch(err => {
  console.error(`Fatal error: ${redactString(err.message)}`);
  process.exit(1);
});
