import fs from 'node:fs';
import path from 'node:path';
import { inspectInterface } from '../bridge/interface-inspections.mjs';

/**
 * GovStore Live Testing - Step Interpreter
 * Executes steps (browser, observation, session, ledger, probe, poll) and a journey's cleanup,
 * logs events and classifies failures.
 */

// Read-only authenticated probes are limited to GovStore surfaces and the
// native import/catalog entry points covered by the seeded user-story suite.
const PROBE_PREFIXES = ['/gov-requests', '/gov-store', '/admin/catalog', '/import', '/directory'];
const SENSITIVE_FLOWS = new Set(['auth.login']);

export function classifyFailure(message = '') {
  if (/^(Assertion|Comparison) failed/.test(message)) return 'assertion';
  if (/PHP Bridge|fixtures\./.test(message)) return 'fixture-or-adapter';
  if (/Timeout|waitFor|net::|Target page/.test(message)) return 'application-response';
  if (/not registered|Unsupported|Unknown|must specify/.test(message)) return 'schema-or-authoring';
  return 'runner';
}

export class StepInterpreter {
  /**
   * @param options.newPage   async (actorName) => Page : opens an isolated browser context for an actor
   * @param options.ledger    array receiving ownership-ledger entries
   * @param options.artifactsDir  private directory for failure screenshots
   */
  constructor(browserActionEngine, phpBridge, eventLogger, options = {}) {
    this.browserActions = browserActionEngine;
    this.phpBridge = phpBridge;
    this.eventLogger = eventLogger;
    this.options = options;
  }

  async runStep(session, step, varContext, baseUrl) {
    const page = session.current;

    // Explicit applicability condition: the step only applies in the listed access modes.
    if (step.modes && !step.modes.includes(this.options.accessMode)) {
      return { action: step.action, skipped: true, reason: `applies only in access mode(s) ${step.modes.join(',')}; installation is ${this.options.accessMode}` };
    }

    // Explicit data condition: { actual, equals | notEquals } decided at run time (e.g. a first-run-only setup step).
    if (step.when) {
      const actual = varContext.interpolate(step.when.actual);
      const holds = step.when.equals !== undefined
        ? String(actual) === String(varContext.interpolate(step.when.equals))
        : String(actual) !== String(varContext.interpolate(step.when.notEquals));
      if (!holds) return { action: step.action || step._flow, skipped: true, reason: `condition not met: ${JSON.stringify(step.when)} (actual ${JSON.stringify(actual)})` };
    }

    switch (step.action) {
      case 'openPopup': {
        this.browserActions.locators.vars = varContext;
        const target = String(varContext.interpolate(step.target));
        const [popup] = await Promise.all([page.waitForEvent('popup'), this.browserActions.locators.resolve(page, target).click()]);
        await popup.waitForLoadState('load');
        if (new URL(popup.url()).origin !== new URL(baseUrl).origin) throw new Error('Unsupported: popup left the selected target');
        session.current = popup;
        return { action: 'openPopup', path: new URL(popup.url()).pathname, actor: session.actor };
      }
      case 'inspect':
      case 'waitInspect': {
        const args = varContext.interpolateDeep(step.args || {});
        const deadline = Date.now() + (step.timeoutMs || 15000);
        let data;
        do {
          data = await inspectInterface(page, step.check, args, baseUrl);
          const actual = step.path?.split('.').reduce((o, k) => o?.[k], data);
          if (step.action === 'inspect' || String(actual) === String(varContext.interpolate(step.equals))) {
            if (step.as) varContext.setObserved(step.as, data);
            return { action: step.action, check: step.check, channel: 'browser-interface', result: data };
          }
          await page.waitForTimeout(step.intervalMs || 500);
        } while (Date.now() < deadline);
        throw new Error(`Assertion failed: interface ${step.check}.${step.path} did not become the expected value; last ${JSON.stringify(data)}`);
      }
      case 'observe': {
        const args = varContext.interpolateDeep(step.args || {});
        const data = await this.phpBridge.observe(step.check, args);
        varContext.setObserved(step.as, data);
        return { action: 'observe', check: step.check, as: step.as, result: data };
      }

      case 'actor': {
        const name = String(varContext.interpolate(step.name));
        if (step.fresh && session.pages.has(name)) {
          await session.pages.get(name).context().close();
          session.pages.delete(name);
        }
        if (!session.pages.has(name)) {
          if (!this.options.newPage) throw new Error(`Unsupported: no session factory to open actor '${name}'`);
          session.pages.set(name, await this.options.newPage(name));
        }
        session.current = session.pages.get(name);
        session.actor = name;
        return { action: 'actor', name };
      }

      case 'ledger': {
        const entry = varContext.interpolateDeep({
          kind: step.kind,
          id: step.id,
          actor: session.actor,
          effect: step.effect || null,
          cleanup: step.cleanup || 'retained'
        });
        const previous = this.options.ledger?.find(e => e.kind === entry.kind && String(e.id) === String(entry.id));
        if (previous && entry.cleanup === 'removed') {
          previous.cleanup = 'removed';
          previous.cleanupVerifiedAt = new Date().toISOString();
        } else {
          this.options.ledger?.push({ ...entry, recordedAt: new Date().toISOString() });
        }
        return { action: 'ledger', ...entry };
      }

      case 'waitObserve': {
        const args = varContext.interpolateDeep(step.args || {});
        const expected = varContext.interpolate(step.equals);
        const deadline = Date.now() + (step.timeoutMs || 15000);
        let last;
        while (Date.now() < deadline) {
          last = await this.phpBridge.observe(step.check, args);
          const actual = step.path.split('.').reduce((o, k) => (o == null ? o : o[k]), last);
          if (String(actual) === String(expected)) {
            if (step.as) varContext.setObserved(step.as, last);
            return { action: 'waitObserve', check: step.check, path: step.path, actual };
          }
          await new Promise(r => setTimeout(r, step.intervalMs || 500));
        }
        throw new Error(`Assertion failed: observation ${step.check}.${step.path} did not become "${expected}" within ${step.timeoutMs || 15000}ms; last: ${JSON.stringify(last)}`);
      }

      case 'probe': {
        // Authenticated read-only server probe using the current actor's session cookies. GET only,
        // allowlisted prefixes, never follows redirects so denials are visible as statuses.
        const route = String(varContext.interpolate(step.path));
        if ((step.method || 'GET') !== 'GET') throw new Error(`Unsupported: probe method '${step.method}' (GET only)`);
        if (!PROBE_PREFIXES.some(p => route === p || route.startsWith(`${p}/`))) {
          throw new Error(`Unsupported: probe path '${route}' is outside the allowlist`);
        }
        let status;
        if (this.options.interfaceOnly) {
          const screen = await page.context().newPage();
          try {
            const response = await screen.goto(new URL(route, baseUrl).toString(), { waitUntil: 'load' });
            let request = response.request();
            while (request.redirectedFrom()) request = request.redirectedFrom();
            status = (await request.response()).status();
          } finally { await screen.close(); }
        } else {
          const response = await page.context().request.get(new URL(route, baseUrl).toString(), { maxRedirects: 0 });
          status = response.status();
        }
        if (step.as) varContext.setCaptured(step.as, status);
        const mode = this.options.accessMode;
        const expected = [].concat(step.expectStatusByMode?.[mode] ?? step.expectStatus ?? []);
        if (expected.length && !expected.includes(status)) {
          throw new Error(`Assertion failed: probe ${route} returned ${status}, expected one of ${expected.join(',')} (access mode: ${mode})`);
        }
        return { action: 'probe', channel: this.options.interfaceOnly ? 'browser-navigation' : 'http', path: route, status };
      }

      default:
        return await this.browserActions.executeStep(page, step, varContext, baseUrl);
    }
  }

  async executeJourney(page, journeyCase, varContext, baseUrl = 'http://snipeit.local') {
    const journeyId = journeyCase.journeyId || journeyCase.caseId;
    const caseId = journeyCase.caseId || journeyId;
    const startTime = Date.now();
    const deadline = startTime + (journeyCase.execution?.timeoutMs || 60000);
    const session = { current: page, pages: new Map([['default', page]]), actor: 'default' };
    let executed = 0;
    let skippedSteps = 0;
    let failure = null;

    this.eventLogger.log('test_started', { journeyId, caseId, title: journeyCase.title });

    try {
      for (const step of journeyCase.steps) {
        executed++;
        if (Date.now() > deadline) throw new Error('Timeout: journey execution deadline exceeded before the next action');
        this.eventLogger.log('step_started', { journeyId, caseId, stepIndex: executed, action: step.action, flow: step._flow, actor: session.actor });
        const result = await this.runStep(session, step, varContext, baseUrl);
        if (Date.now() > deadline) throw new Error('Timeout: journey execution deadline exceeded during the action');
        if (result?.skipped) skippedSteps++;
        this.eventLogger.log('step_finished', { journeyId, caseId, stepIndex: executed, status: result?.skipped ? 'skipped' : 'passed', result });
      }
    } catch (err) {
      const step = journeyCase.steps[executed - 1];
      failure = { error: err.message, failedStepIndex: executed, failedStep: step, failureClass: classifyFailure(err.message) };
      if (session.current.__liveNavigation) failure.navigation = session.current.__liveNavigation;
      this.eventLogger.log('step_failed', { journeyId, caseId, stepIndex: executed, error: err.message, failureClass: failure.failureClass, step });

      if (this.options.artifactsDir && !step?._sensitive && !step?.sensitive && !SENSITIVE_FLOWS.has(step?._flow)) {
        try {
          fs.mkdirSync(this.options.artifactsDir, { recursive: true });
          const file = `${caseId.replace(/[^a-z0-9._-]/gi, '_')}-step${executed}.png`;
          await session.current.screenshot({ path: path.join(this.options.artifactsDir, file), fullPage: true,
            mask: [session.current.locator('.phpdebugbar, .govmem-code, .govmem-code-input, input[type="password"], input[name="verification_code"], input[name="invitation_code"]')] });
          failure.screenshot = file;
        } catch { /* evidence is best-effort */ }
      }
    }

    // Cleanup always runs through supported UI steps; its failure is reported separately and never hidden.
    let cleanup = { status: 'not-required', steps: 0 };
    const cleanupSteps = journeyCase.cleanup?.steps || [];
    if (cleanupSteps.length) {
      cleanup = { status: 'passed', steps: cleanupSteps.length };
      for (const step of cleanupSteps) {
        try {
          await this.runStep(session, step, varContext, baseUrl);
        } catch (err) {
          cleanup = { status: 'failed', error: err.message };
          this.eventLogger.log('cleanup_failed', { journeyId, caseId, error: err.message });
          break;
        }
      }
    }

    const durationMs = Date.now() - startTime;
    const base = {
      journeyId,
      caseId,
      durationMs,
      stepsExecuted: executed,
      skippedSteps,
      cleanup,
      variables: { captured: varContext.captured, observed: varContext.observed }
    };

    let result;
    if (failure) {
      result = { ...base, status: 'failed', ...failure };
    } else if (cleanup.status === 'failed') {
      result = { ...base, status: 'failed', failureClass: 'cleanup', error: `Cleanup failed: ${cleanup.error}` };
    } else {
      result = { ...base, status: 'passed' };
    }

    for (const [name, p] of session.pages) {
      if (p !== page) await p.context().close().catch(() => {});
    }
    this.eventLogger.log('test_finished', { journeyId, caseId, status: result.status, durationMs, error: result.error });
    return result;
  }
}
