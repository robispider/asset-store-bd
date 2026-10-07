import fs from 'node:fs';
import path from 'node:path';

/**
 * GovStore Live Testing - Step Interpreter
 * Executes steps (browser, observation, session, ledger, probe, poll) and a journey's cleanup,
 * logs events and classifies failures.
 */

const PROBE_PREFIXES = ['/gov-requests', '/gov-store'];
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

    switch (step.action) {
      case 'observe': {
        const args = varContext.interpolateDeep(step.args || {});
        const data = await this.phpBridge.observe(step.check, args);
        varContext.setObserved(step.as, data);
        return { action: 'observe', check: step.check, as: step.as, result: data };
      }

      case 'actor': {
        const name = String(varContext.interpolate(step.name));
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
        this.options.ledger?.push({ ...entry, recordedAt: new Date().toISOString() });
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
        const response = await page.context().request.get(new URL(route, baseUrl).toString(), { maxRedirects: 0 });
        const status = response.status();
        if (step.as) varContext.setCaptured(step.as, status);
        const mode = this.options.accessMode;
        const expected = [].concat(step.expectStatusByMode?.[mode] ?? step.expectStatus ?? []);
        if (expected.length && !expected.includes(status)) {
          throw new Error(`Assertion failed: probe ${route} returned ${status}, expected one of ${expected.join(',')} (access mode: ${mode})`);
        }
        return { action: 'probe', channel: 'http', path: route, status };
      }

      default:
        return await this.browserActions.executeStep(page, step, varContext, baseUrl);
    }
  }

  async executeJourney(page, journeyCase, varContext, baseUrl = 'http://snipeit.local') {
    const journeyId = journeyCase.journeyId || journeyCase.caseId;
    const caseId = journeyCase.caseId || journeyId;
    const startTime = Date.now();
    const session = { current: page, pages: new Map([['default', page]]), actor: 'default' };
    let executed = 0;
    let failure = null;

    this.eventLogger.log('test_started', { journeyId, caseId, title: journeyCase.title });

    try {
      for (const step of journeyCase.steps) {
        executed++;
        this.eventLogger.log('step_started', { journeyId, caseId, stepIndex: executed, action: step.action, flow: step._flow, actor: session.actor });
        const result = await this.runStep(session, step, varContext, baseUrl);
        this.eventLogger.log('step_finished', { journeyId, caseId, stepIndex: executed, status: 'passed', result });
      }
    } catch (err) {
      const step = journeyCase.steps[executed - 1];
      failure = { error: err.message, failedStepIndex: executed, failedStep: step, failureClass: classifyFailure(err.message) };
      this.eventLogger.log('step_failed', { journeyId, caseId, stepIndex: executed, error: err.message, failureClass: failure.failureClass, step });

      if (this.options.artifactsDir && !SENSITIVE_FLOWS.has(step?._flow)) {
        try {
          fs.mkdirSync(this.options.artifactsDir, { recursive: true });
          const file = `${caseId.replace(/[^a-z0-9._-]/gi, '_')}-step${executed}.png`;
          await session.current.screenshot({ path: path.join(this.options.artifactsDir, file), fullPage: true });
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
