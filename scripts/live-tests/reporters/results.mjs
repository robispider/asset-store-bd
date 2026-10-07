import fs from 'node:fs';
import path from 'node:path';
import { redactObject } from './redaction.mjs';

/**
 * GovStore Live Testing - Results Reporter
 * Produces structured results.json and terminal output.
 */
export class ResultsReporter {
  constructor(runDir) {
    this.runDir = runDir;
    this.resultsFile = path.join(runDir, 'results.json');
    this.startTime = Date.now();
    this.testResults = [];
  }

  recordTestResult(testResult) {
    this.testResults.push(redactObject(testResult));
  }

  save() {
    const durationMs = Date.now() - this.startTime;
    const summary = {
      total: this.testResults.length,
      passed: this.testResults.filter(t => t.status === 'passed').length,
      failed: this.testResults.filter(t => t.status === 'failed').length,
      blocked: this.testResults.filter(t => t.status === 'blocked').length,
      skipped: this.testResults.filter(t => t.status === 'skipped').length,
      flaky: this.testResults.filter(t => t.flaky).length,
      interrupted: this.testResults.filter(t => t.status === 'interrupted').length,
      inconclusive: this.testResults.filter(t => t.status === 'inconclusive').length,
      durationMs
    };

    const payload = {
      summary,
      results: this.testResults
    };

    fs.writeFileSync(this.resultsFile, JSON.stringify(payload, null, 2), 'utf8');
    return payload;
  }

  printSummary() {
    const summary = {
      total: this.testResults.length,
      passed: this.testResults.filter(t => t.status === 'passed').length,
      failed: this.testResults.filter(t => t.status === 'failed').length,
      blocked: this.testResults.filter(t => t.status === 'blocked').length,
      skipped: this.testResults.filter(t => t.status === 'skipped').length,
      flaky: this.testResults.filter(t => t.flaky).length,
      interrupted: this.testResults.filter(t => t.status === 'interrupted').length,
      inconclusive: this.testResults.filter(t => t.status === 'inconclusive').length,
      durationMs: Date.now() - this.startTime
    };

    console.log('\n================ Live Test Execution Summary ================');
    console.log(`Total Journeys : ${summary.total}`);
    console.log(`Passed         : ${summary.passed}`);
    console.log(`Failed         : ${summary.failed}`);
    console.log(`Blocked        : ${summary.blocked}`);
    console.log(`Skipped        : ${summary.skipped}`);
    console.log(`Flaky (retried): ${summary.flaky}`);
    console.log(`Interrupted    : ${summary.interrupted}`);
    console.log(`Inconclusive   : ${summary.inconclusive}`);
    console.log(`Duration       : ${(summary.durationMs / 1000).toFixed(2)}s`);
    console.log('=============================================================\n');

    for (const res of this.testResults) {
      const badge = res.status === 'passed' ? '[PASS]' : res.status === 'failed' ? '[FAIL]' : `[${res.status.toUpperCase()}]`;
      console.log(`${badge}${res.flaky ? ' [FLAKY: passed on attempt ' + res.attempts + ']' : ''} ${res.journeyId} (${res.durationMs || 0}ms)`);
      if (res.error) {
        console.log(`       Error: ${res.error}`);
      }
      if (res.failedStep) {
        console.log(`       Failed at step: ${JSON.stringify(res.failedStep)}`);
      }
    }
  }
}
