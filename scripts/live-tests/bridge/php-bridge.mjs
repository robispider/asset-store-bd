import { execFile } from 'node:child_process';
import path from 'node:path';
import fs from 'node:fs';

const WAMP_PHP = 'C:/wamp64/bin/php/php8.4.15/php.exe';

/** PHP binary: LIVE_TEST_PHP env, then the documented local WAMP PHP, then PATH. */
function resolvePhp() {
  if (process.env.LIVE_TEST_PHP) return process.env.LIVE_TEST_PHP;
  return fs.existsSync(WAMP_PHP) ? WAMP_PHP : 'php';
}

/**
 * GovStore Live Testing - Node to PHP Process Bridge
 * Communicates with live-test-adapter.php via child process.
 */
export class PhpBridge {
  constructor(options = {}) {
    this.phpPath = options.phpPath || resolvePhp();
    this.adapterPath = options.adapterPath || path.resolve(process.cwd(), 'scripts/live-tests/bridge/live-test-adapter.php');
    this.mockMode = Boolean(options.mockMode);
    this.mockData = options.mockData || {};
  }

  async runAction(action, payload = {}) {
    if (this.mockMode) {
      if (action === 'datasets') {
        return this.mockData.datasets || { status: 'success', datasets: [{ id: 'mock-run-1', label: 'Mock Dataset', status: 'ready' }] };
      }
      if (action === 'resolveContext') {
        return this.mockData.resolvedContext || {
          status: 'success',
          run: { id: 'mock-run-1', label: 'Mock' },
          office: { id: 10, name: 'Mock Office', company_id: 1 },
          actors: {
            storekeeper: { id: 101, username: 'mockuser', display_name: 'Mock User', password: 'mockpassword' }
          },
          records: {
            item: { id: 201, name: 'Mock Consumable', quantity: 50, location_id: 10, company_id: 1 }
          }
        };
      }
      if (action === 'preflight') {
        return this.mockData.preflight || { status: 'success', environment: 'local', production: false, accessMode: 'shadow', mailer: 'log', queue: 'sync', readyDatasets: 1 };
      }
      if (action === 'observe') {
        const check = payload.check;
        return this.mockData.observations?.[check] || {
          id: payload.args?.id || 1,
          name: 'Mock Item',
          quantity: 50,
          businessFingerprint: 'mock-fingerprint-1234'
        };
      }
      return { status: 'success' };
    }

    const json = JSON.stringify(payload);
    const b64 = Buffer.from(json, 'utf8').toString('base64');
    const args = ['-d', 'xdebug.mode=off', this.adapterPath, action, b64];

    return new Promise((resolve, reject) => {
      execFile(this.phpPath, args, { encoding: 'utf8', timeout: 30000 }, (error, stdout, stderr) => {
        const trimmed0 = (stdout || '').trim();
        if (error && !trimmed0.startsWith('{')) {
          return reject(new Error(`PHP Bridge execution failed: ${error.message} - ${stderr || stdout}`));
        }

        const trimmed = (stdout || '').trim();
        if (!trimmed) {
          return reject(new Error(`PHP Bridge returned empty output. Stderr: ${stderr}`));
        }

        try {
          const response = JSON.parse(trimmed);
          if (response.status === 'error') {
            const err = new Error(`PHP Bridge error (${response.code || 'UNKNOWN'}): ${response.message}`);
            err.code = response.code;
            err.search = response.search;
            return reject(err);
          }
          resolve(response);
        } catch (e) {
          reject(new Error(`Failed to parse PHP Bridge response: "${trimmed}" - Error: ${e.message}`));
        }
      });
    });
  }

  async getDatasets() {
    const res = await this.runAction('datasets');
    return res.datasets || [];
  }

  async resolveContext(fixturesDef, datasetId = null) {
    const payload = {
      datasetId,
      office: fixturesDef?.office || {},
      actors: fixturesDef?.actors || {},
      records: fixturesDef?.records || {}
    };
    return await this.runAction('resolveContext', payload);
  }

  async fingerprint() {
    return await this.runAction('fingerprint');
  }

  async preflight() {
    return await this.runAction('preflight');
  }

  async observe(check, args = {}) {
    return await this.runAction('observe', { check, args });
  }
}
