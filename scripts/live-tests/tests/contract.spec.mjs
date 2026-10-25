import http from 'node:http';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { TestLoader } from '../core/loader.mjs';
import { TestValidator } from '../core/validator.mjs';
import { ExecutionPlanner } from '../core/planner.mjs';
import { VariableContext } from '../core/variables.mjs';
import { DeterministicRandom } from '../core/generators.mjs';
import { LocatorResolver } from '../core/locators.mjs';
import { AssertionEngine } from '../actions/assertions.mjs';
import { CaptureEngine } from '../actions/capture.mjs';
import { BrowserActionEngine } from '../actions/browser.mjs';
import { StepInterpreter } from '../core/interpreter.mjs';
import { EventLogger } from '../reporters/events.mjs';
import { PhpBridge } from '../bridge/php-bridge.mjs';
import { redactObject } from '../reporters/redaction.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { assertMatchingTarget } from '../core/target.mjs';

async function runContractTests() {
  console.log('=== Running GovStore Live Test Runner Contract Tests ===\n');

  const baseDir = process.cwd();
  const loader = new TestLoader(baseDir);
  const validator = new TestValidator(loader);
  assert.deepEqual(assertMatchingTarget('http://snipeit.local/', { appUrl: 'http://snipeit.local' }), {
    browserOrigin: 'http://snipeit.local', bridgeOrigin: 'http://snipeit.local'
  });
  assertMatchingTarget('http://localhost:80', { appUrl: 'http://localhost' });
  for (const [target, appUrl] of [
    ['http://118.179.221.116:8051', 'http://snipeit.local'],
    ['http://localhost:8051', 'http://localhost'],
    ['https://snipeit.local', 'http://snipeit.local'],
    ['http://snipeit.local', undefined],
    ['http://snipeit.local', 'file:///app'],
    ['http://name:secret@snipeit.local', 'http://snipeit.local']
  ]) {
    assert.throws(() => assertMatchingTarget(target, { appUrl }), err => err.code === 'preflight.target-mismatch');
  }

  // 1. Validator Tests: Unknown action rejected
  console.log('1. Testing validation rejection of unknown action...');
  const invalidJourney = {
    schemaVersion: 1,
    id: 'test.invalid-action',
    title: 'Invalid Action Test',
    execution: { capability: 'observe' },
    steps: [
      { action: 'nonExistentAction', target: 'something' }
    ]
  };
  const valErrors = validator.validateJourney(invalidJourney);
  assert(valErrors.length > 0, 'Validator must reject unknown action');
  assert(valErrors.some(e => e.includes("Unknown action 'nonExistentAction'")));
  console.log('   [PASS] Unknown action correctly rejected.');

  // 2. Validator Tests: Cyclic flow detection
  console.log('2. Testing cyclic flow detection...');
  const cyclicJourney = {
    schemaVersion: 1,
    id: 'test.cyclic',
    title: 'Cyclic Flow Test',
    execution: { capability: 'observe' },
    steps: [
      { use: 'flowA' }
    ]
  };
  // Mock loader for flowA -> flowB -> flowA
  const mockLoader = {
    loadFlow: (id) => {
      if (id === 'flowA') return { schemaVersion: 1, id: 'flowA', steps: [{ use: 'flowB' }] };
      if (id === 'flowB') return { schemaVersion: 1, id: 'flowB', steps: [{ use: 'flowA' }] };
      throw new Error(`Unknown flow ${id}`);
    },
    loadObservationContracts: () => loader.loadObservationContracts()
  };
  const cyclicValidator = new TestValidator(mockLoader);
  const cyclicErrors = cyclicValidator.validateJourney(cyclicJourney);
  assert(cyclicErrors.some(e => e.includes('Cyclic flow reference detected')));
  console.log('   [PASS] Cyclic flow correctly detected and rejected.');

  // 3. Variable Context Tests
  console.log('3. Testing Variable Context resolution and interpolation...');
  const varCtx = new VariableContext({
    run: { id: 'run-123' },
    fixtures: { item: { id: 456, name: 'Paper' } },
    actors: { user: { username: 'testuser' } }
  });
  assert.equal(varCtx.interpolate('${fixtures.item.id}'), 456);
  assert.equal(varCtx.interpolate('Hello ${actors.user.username}!'), 'Hello testuser!');
  assert.throws(() => varCtx.interpolate('${invalid.foo}'), /Unknown variable namespace/);
  console.log('   [PASS] Variable resolution works as specified.');

  // 4. Deterministic Random Generator Tests
  console.log('4. Testing Deterministic Generators...');
  const rng1 = new DeterministicRandom(12345);
  const rng2 = new DeterministicRandom(12345);
  const text1 = rng1.uniqueMarkedText('REQ', 6);
  const text2 = rng2.uniqueMarkedText('REQ', 6);
  assert.equal(text1, text2, 'Same seed must generate identical marked text');
  console.log(`   [PASS] Deterministic RNG generated: ${text1}`);

  // 5. Miniature HTTP Server + Real Browser Execution Tests
  console.log('5. Starting miniature local HTTP app for real-browser testing...');
  const server = http.createServer((req, res) => {
    if (req.url === '/login' && req.method === 'GET') {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(`
        <!DOCTYPE html>
        <html>
          <body>
            <form action="/login" onsubmit="event.preventDefault(); document.cookie = 'auth=true; path=/'; window.location = '/dashboard';">
              <input name="username" id="username" />
              <input name="password" type="password" id="password-field" />
              <button id="submit" type="submit">Login</button>
            </form>
          </body>
        </html>
      `);
      return;
    }

    if (req.url === '/dashboard') {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.write(`
        <!DOCTYPE html>
        <html>
          <body>
            <header class="main-header"><div class="navbar-custom-menu"><ul>
              <li class="dropdown notifications-menu"><a href="#" class="dropdown-toggle">Inbox</a></li>
              <li id="user-menu" class="dropdown user user-menu"><a href="#" class="dropdown-toggle">Profile</a></li>
            </ul></div></header>
            <h1>Dashboard</h1>
      `);
      // The menu can render before the document finishes and its controls initialize.
      setTimeout(() => res.end('<div id="dashboard-ready">Controls ready</div></body></html>'), 500);
      return;
    }

    if (req.url.startsWith('/consumables/')) {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(`
        <!DOCTYPE html>
        <html>
          <body>
            <h1 class="pagetitle">EXP-1234 A4 Paper</h1>
            <div class="box-profile"><span class="qty">95</span></div>
          </body>
        </html>
      `);
      return;
    }

    if (req.url === '/gov-requests/admin') {
      res.writeHead(403, { 'Content-Type': 'text/plain' });
      res.end('Forbidden');
      return;
    }

    if (['/admin/catalog/import', '/admin/catalog/collections', '/import', '/directory'].includes(req.url)) {
      res.writeHead(403, { 'Content-Type': 'text/plain' });
      res.end('Forbidden');
      return;
    }

    if (req.url === '/popup') {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end('<a id="open-form" target="_blank" href="/form">Open form</a>');
      return;
    }

    if (req.url === '/form') {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end('<!DOCTYPE html><html><body><input id="note" name="note" required /><input type="file" id="csv-upload"><a id="ready-link" href="/dashboard">Open dashboard</a></body></html>');
      return;
    }

    if (req.url === '/select2') {
      // A select2-shaped widget: the real select is followed by a container whose dropdown lists AJAX-like results.
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(`<!DOCTYPE html><html><body>
        <select id="item" name="item"><option value=""></option></select><span class="select2 select2-container"><span class="select2-selection">pick</span></span>
        <script>
          const data = { m1: 'Model 1 (Asset Model)', m10: 'Model 10 (Asset Model)', m11: 'Model 11 (Asset Model)' };
          document.querySelector('.select2-selection').addEventListener('click', () => {
            const box = document.createElement('span');
            box.className = 'select2-container select2-container--open';
            box.innerHTML = '<input class="select2-search__field"><ul></ul>';
            document.body.append(box);
            box.querySelector('input').addEventListener('input', e => setTimeout(() => {
              const ul = box.querySelector('ul'); ul.innerHTML = '';
              for (const [value, text] of Object.entries(data)) if (text.includes(e.target.value)) {
                const li = document.createElement('li'); li.className = 'select2-results__option'; li.textContent = text;
                li.addEventListener('click', () => { document.getElementById('item').append(new Option(text, value, true, true)); box.remove(); });
                ul.append(li);
              }
            }, 50));
          });
        </script></body></html>`);
      return;
    }
    if (req.url === '/download') {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end('<!DOCTYPE html><html><body><a id="csv" href="/files/issued.csv">CSV</a></body></html>');
      return;
    }
    if (req.url === '/files/issued.csv') {
      res.writeHead(200, { 'Content-Type': 'text/csv', 'Content-Disposition': 'attachment; filename="issued.csv"' });
      res.end('﻿tag,kind\n090708102641020001,govt\nGS-09694,standard\n');
      return;
    }
    if (req.url === '/forms') {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(`<!DOCTYPE html><html><body>
        <form method="post" action="/accepted"><button id="good" type="submit">Go</button></form>
        <form method="post" action="/post-only"><button id="bad" type="submit">Go</button></form>
        <form method="get" action="/flash-error"><button id="flash" type="submit">Go</button></form>
        <form method="post" action="/accepted"><input name="must" required><button id="blocked" type="submit">Go</button></form>
        <p id="ref">Reference GR-2026-000042 posted</p></body></html>`);
      return;
    }
    if (req.url.split('?')[0] === '/flash-error') {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end('<div class="alert-danger">INTERNAL-CANARY reference 11111111-aaaa-4bbb-8ccc-222222222222</div>');
      return;
    }
    if (req.url.split('?')[0] === '/persistent-status') {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end('<div class="govorg"><section class="gs-box"><div class="gs-alert--danger">An earlier independent starter job failed</div></section><p id="saved">Office change saved</p><form action="/persistent-status"><button id="save-status" type="submit">Save</button></form></div>');
      return;
    }
    if (req.url === '/accepted' && req.method === 'POST') {
      res.writeHead(303, { Location: '/form' });
      res.end();
      return;
    }
    if (req.url === '/post-only' && req.method === 'POST') {
      // Mimics back() to a POST-only route: the browser lands on a 405 page.
      res.writeHead(303, { Location: '/post-only' });
      res.end();
      return;
    }
    if (req.url === '/post-only') {
      res.writeHead(405, { 'Content-Type': 'text/plain' });
      res.end('Method Not Allowed');
      return;
    }

    res.writeHead(404);
    res.end('Not Found');
  });

  await new Promise(resolve => server.listen(9876, resolve));
  const testBaseUrl = 'http://localhost:9876';

  const browser = await chromium.launch({ headless: true });
  const tmpRunDir = path.join(baseDir, 'storage', 'app', 'private', 'live-tests', 'contract_test_run');
  const eventLogger = new EventLogger(tmpRunDir);

  const locResolver = new LocatorResolver(loader, 'en-US');
  const assertions = new AssertionEngine();
  const captureEngine = new CaptureEngine();
  const actions = new BrowserActionEngine(assertions, captureEngine, locResolver);

  // Mock observation bridge for contract test
  const mockBridge = new PhpBridge({
    mockMode: true,
    mockData: {
      observations: {
        'inventory.consumable': {
          id: 1234,
          name: 'EXP-1234 A4 Paper',
          businessFingerprint: 'dummy-fingerprint'
        }
      }
    }
  });

  const interpreter = new StepInterpreter(actions, mockBridge, eventLogger);

  // Test Case A: Synthetic Passing Journey
  console.log('   Running synthetic passing journey with real browser...');
  const passingCase = {
    journeyId: 'contract.synthetic-pass',
    title: 'Synthetic Passing Test',
    steps: [
      { action: 'goto', url: '/login' },
      { action: 'fill', target: 'auth.username', value: 'alice' },
      { action: 'fill', target: 'auth.password', value: 'secret123' },
      { action: 'submitForm', target: 'auth.submit' },
      { action: 'wait', target: 'auth.userDropdown', state: 'visible' },
      { action: 'assert', target: 'auth.userDropdown', test: 'visible' },
      { action: 'assert', target: '#dashboard-ready', test: 'visible' },
      { action: 'goto', url: '/consumables/1234' },
      { action: 'assert', target: 'consumable.name', test: 'text', contains: 'EXP-1234 A4 Paper' },
      { action: 'capture', target: 'consumable.name', as: 'savedName' }
    ]
  };

  const passVarCtx = new VariableContext();
  const page1 = await browser.newPage();
  const passResult = await interpreter.executeJourney(page1, passingCase, passVarCtx, testBaseUrl);
  await page1.close();

  if (passResult.status !== 'passed') {
    console.error('passResult failure details:', passResult.error, 'step:', passResult.failedStep);
  }
  assert.equal(passResult.status, 'passed', 'Synthetic journey must pass');
  assert.equal(passVarCtx.captured.savedName, 'EXP-1234 A4 Paper', 'Capture must save extracted value');
  console.log('   [PASS] Synthetic journey executed cleanly and passed.');

  // Test Case B: Synthetic Failing Journey (Deliberate Wrong Text)
  console.log('   Running synthetic failing journey (deliberate assertion mismatch)...');
  const failingCase = {
    journeyId: 'contract.synthetic-fail',
    title: 'Synthetic Deliberate Failure Test',
    steps: [
      { action: 'goto', url: '/consumables/1234' },
      { action: 'assert', target: 'consumable.name', test: 'text', equals: 'INCORRECT_TEXT' }
    ]
  };

  const failVarCtx = new VariableContext();
  const page2 = await browser.newPage();
  const failResult = await interpreter.executeJourney(page2, failingCase, failVarCtx, testBaseUrl);
  await page2.close();

  assert.equal(failResult.status, 'failed', 'Deliberate mismatch must report failed status');
  assert(failResult.error.includes('Assertion failed: Target \'consumable.name\' text mismatch'));
  console.log(`   [PASS] Precise failure caught: "${failResult.error}"`);

  // Test Case C: independent actor sessions, captured value reuse across actors, ledger, probe
  console.log('   Running multi-actor session / ledger / probe journey...');
  const ledger = [];
  const sessionContexts = [];
  const multiInterpreter = new StepInterpreter(actions, mockBridge, eventLogger, {
    ledger,
    accessMode: 'shadow',
    newPage: async () => {
      const c = await browser.newContext();
      sessionContexts.push(c);
      return await c.newPage();
    }
  });
  const multiCtx = new VariableContext({ data: { token: 'CANARY-VALUE' } });
  const multiPage = await browser.newPage();
  const multiResult = await multiInterpreter.executeJourney(multiPage, {
    journeyId: 'contract.multi-actor',
    steps: [
      { action: 'actor', name: 'alice' },
      { action: 'goto', url: '/form' },
      { action: 'fill', target: '#note', value: '${data.token}' },
      { action: 'capture', target: '#note', value: true, as: 'noteFromAlice' },
      { action: 'ledger', kind: 'thing', id: '${captured.noteFromAlice}', effect: 'synthetic' },
      { action: 'actor', name: 'bob' },
      { action: 'goto', url: '/form' },
      { action: 'assert', target: '#note', test: 'value', equals: '' },
      { action: 'fill', target: '#note', value: '${captured.noteFromAlice}' },
      { action: 'assert', target: '#note', test: 'value', equals: 'CANARY-VALUE' },
      { action: 'assert', target: '#note', test: 'valid' },
      { action: 'probe', path: '/gov-requests/admin', expectStatusByMode: { shadow: [403], enforce: [403] }, as: 'status' }
    ]
  }, multiCtx, testBaseUrl);
  if (multiResult.status !== 'passed') console.error('multi failure:', multiResult.error);
  assert.equal(multiResult.status, 'passed', 'Multi-actor journey must pass');
  assert.equal(sessionContexts.length, 2, 'Each actor must get its own browser context');
  assert.equal(multiCtx.captured.status, 403, 'Probe status must be captured');
  assert.equal(ledger.length, 1, 'Ledger entry must be recorded');
  assert.equal(ledger[0].actor, 'alice', 'Ledger must attribute the acting session');
  console.log('   [PASS] Actor isolation, capture reuse, probe and ledger work.');

  // Test Case D: a probe with the wrong expectation and an out-of-allowlist path must fail precisely
  console.log('   Running probe false-pass guards...');
  const probePage = await browser.newPage();
  const wrongProbe = await multiInterpreter.executeJourney(probePage, {
    journeyId: 'contract.probe-wrong',
    steps: [{ action: 'probe', path: '/gov-requests/admin', expectStatus: [200] }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(wrongProbe.status, 'failed');
  assert.equal(wrongProbe.failureClass, 'assertion');
  const badPath = await multiInterpreter.executeJourney(probePage, {
    journeyId: 'contract.probe-bad-path',
    steps: [{ action: 'probe', path: '/admin/users', expectStatus: [200] }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(badPath.status, 'failed');
  assert(/outside the allowlist/.test(badPath.error));
  for (const path of ['/admin/catalog/import', '/admin/catalog/collections', '/import', '/directory']) {
    const allowedReadonlyPath = await multiInterpreter.executeJourney(probePage, {
      journeyId: `contract.probe-allowed-${path.replace(/[^a-z0-9]+/gi, '-')}`,
      steps: [{ action: 'probe', path, expectStatus: [403] }]
    }, new VariableContext(), testBaseUrl);
    assert.equal(allowedReadonlyPath.status, 'passed', `Read-only admin probe should be allowlisted: ${path}`);
  }
  await probePage.close();
  console.log('   [PASS] Wrong probe expectation fails; only the explicit read-only admin roots are allowlisted.');

  // Test Case E: cleanup failure converts an otherwise passing journey into a failure
  console.log('   Running cleanup-failure journey...');
  const cleanupPage = await browser.newPage();
  const cleanupResult = await multiInterpreter.executeJourney(cleanupPage, {
    journeyId: 'contract.cleanup-fail',
    steps: [{ action: 'goto', url: '/form' }],
    cleanup: { steps: [{ action: 'assert', target: '#does-not-exist', test: 'visible' }] }
  }, new VariableContext(), testBaseUrl);
  assert.equal(cleanupResult.status, 'failed', 'Cleanup failure must not be reported as pass');
  assert.equal(cleanupResult.failureClass, 'cleanup');
  assert.equal(cleanupResult.cleanup.status, 'failed');
  await cleanupPage.close();
  console.log('   [PASS] Cleanup failure is reported as failed.');

  // Test Case F: HTML validity assertions, count and waitObserve timeout
  const validityPage = await browser.newPage();
  const validity = await multiInterpreter.executeJourney(validityPage, {
    journeyId: 'contract.validity',
    steps: [
      { action: 'goto', url: '/form' },
      { action: 'assert', target: '#note', test: 'invalid' },
      { action: 'assert', target: '#note', test: 'count', equals: 1 }
    ]
  }, new VariableContext(), testBaseUrl);
  assert.equal(validity.status, 'passed', validity.error);
  const wrongValidity = await multiInterpreter.executeJourney(validityPage, {
    journeyId: 'contract.validity-wrong',
    steps: [{ action: 'goto', url: '/form' }, { action: 'assert', target: '#note', test: 'valid' }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(wrongValidity.status, 'failed', 'valid assertion on an invalid field must fail');
  const ambiguous = await multiInterpreter.executeJourney(validityPage, {
    journeyId: 'contract.ambiguous',
    steps: [{ action: 'goto', url: '/dashboard' }, { action: 'assert', target: 'li, h1', test: 'text', contains: 'x' }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(ambiguous.status, 'failed', 'Ambiguous locators must fail, never pick the first');
  await validityPage.close();
  console.log('   [PASS] Validity, count and ambiguity checks behave strictly.');

  // Test Case G: secrets never reach events/results
  console.log('   Running secret-redaction canary check...');
  const canary = 'CANARY-SECRET-9f3a7c';
  const canaryResult = await multiInterpreter.executeJourney(await browser.newPage(), {
    journeyId: 'contract.canary',
    steps: [{ action: 'goto', url: '/form' }, { action: 'fill', target: '#note', value: '${actors.a.password}' }, { action: 'assert', target: '#note', test: 'value', equals: 'wrong' }]
  }, new VariableContext({ actors: { a: { password: canary } } }), testBaseUrl);
  assert.equal(canaryResult.status, 'failed');
  const eventsText = fs.readFileSync(path.join(tmpRunDir, 'events.jsonl'), 'utf8');
  assert(!eventsText.includes(canary), 'Event stream must not carry the canary secret');
  assert(!JSON.stringify(redactObject(canaryResult)).includes(canary), 'Recorded result must not carry the canary secret');
  const redacted = JSON.stringify(redactObject({ actors: { a: { password: canary } }, text: 'password=' + canary }));
  assert(!redacted.includes(canary), 'Redaction must remove canary secrets');
  console.log('   [PASS] Secrets are redacted.');

  // Test Case I: steps added for asset tagging — select2, download, submitForm landing status, when, matches,
  // greaterThan, capture pattern and the date generator's format.
  console.log('   Running select2 / download / submit-status / condition checks...');
  const extraPage = await browser.newPage();
  const extras = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.tagging-steps',
    steps: [
      { action: 'goto', url: '/select2' },
      { action: 'select2', target: '#item', search: 'Model 1', option: 'Model 1' },
      { action: 'assert', target: '#item', test: 'value', equals: 'm1' },
      { action: 'goto', url: '/download' },
      { action: 'download', target: '#csv', as: 'csv' },
      { action: 'compare', actual: '${captured.csv.rowCount}', equals: 2 },
      { action: 'compare', actual: '${captured.csv.text}', contains: 'GS-09694' },
      { action: 'goto', url: '/forms' },
      { action: 'capture', target: '#ref', pattern: '(GR-\\d{4}-\\d{6})', as: 'number' },
      { action: 'compare', actual: '${captured.number}', matches: '^GR-2026-000042$' },
      { action: 'compare', actual: 7, greaterThan: 3 },
      { action: 'assert', target: '#missing-element', test: 'visible', when: { actual: '${captured.number}', equals: 'something else' } },
      { action: 'submitForm', target: '#good', expectStatus: [200] },
      { action: 'assert', test: 'url', contains: '/form' }
    ]
  }, new VariableContext(), testBaseUrl);
  assert.equal(extras.status, 'passed', extras.error);
  const ambiguousSelect = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.select2-ambiguous',
    steps: [{ action: 'goto', url: '/select2' }, { action: 'select2', target: '#item', search: 'Model', option: 'Model' }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(ambiguousSelect.status, 'failed', 'A select2 option that is neither exact nor unique must fail');
  const firstVisibleChoice = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.select2-first',
    steps: [{ action: 'goto', url: '/select2' }, { action: 'select2', target: '#item', search: 'Model', selection: 'first', as: 'choice' },
      { action: 'compare', actual: '${captured.choice.value}', equals: 'm1' }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(firstVisibleChoice.status, 'passed', firstVisibleChoice.error);
  const popupResult = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.popup',
    steps: [{ action: 'goto', url: '/popup' }, { action: 'openPopup', target: '#open-form' },
      { action: 'fill', target: '#note', value: 'same actor' }, { action: 'assert', target: '#note', test: 'value', equals: 'same actor' },
      { action: 'actor', name: 'default' }, { action: 'assert', test: 'url', contains: '/popup' }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(popupResult.status, 'passed', popupResult.error);
  const loadedLink = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.link-load',
    steps: [{ action: 'goto', url: '/form' }, { action: 'click', target: '#ready-link', waitForNavigation: true },
      { action: 'assert', target: '#dashboard-ready', test: 'visible' }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(loadedLink.status, 'passed', loadedLink.error);
  await extraPage.goto(testBaseUrl + '/form');
  await actions.executeStep(extraPage, { action: 'upload', target: '#csv-upload', filename: 'fixture.csv',
    inlineCsv: [{ Name: '${data.item}', Quantity: 3 }] }, new VariableContext({ data: { item: 'Fixture, One' } }), testBaseUrl);
  const uploaded = await extraPage.locator('#csv-upload').evaluate(async el => ({ name: el.files[0].name, text: await el.files[0].text() }));
  assert.equal(uploaded.name, 'fixture.csv');
  assert(uploaded.text.includes('"Fixture, One",3'), 'Generated CSV upload must escape values and interpolate fixture inputs');
  const deadlineResult = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.deadline', execution: { timeoutMs: 5 },
    steps: [{ action: 'wait', timeMs: 30 }],
    cleanup: { steps: [{ action: 'capture', value: true, as: 'cleanupRan' }] }
  }, new VariableContext(), testBaseUrl);
  assert.equal(deadlineResult.status, 'failed');
  assert.match(deadlineResult.error, /execution deadline/);
  assert.equal(deadlineResult.variables.captured.cleanupRan, true, 'Supported cleanup still runs after the execution deadline');
  const landedOnError = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.submit-405',
    steps: [{ action: 'goto', url: '/forms' }, { action: 'submitForm', target: '#bad', expectStatus: [200] }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(landedOnError.status, 'failed');
  assert.match(landedOnError.error, /status 405/);
  const refusedAction = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.refused-flash',
    steps: [{ action: 'goto', url: '/forms' }, { action: 'submitForm', target: '#flash', rejectFlashErrors: true }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(refusedAction.status, 'failed');
  assert.match(refusedAction.error, /11111111-aaaa-4bbb-8ccc-222222222222/);
  assert(!refusedAction.error.includes('INTERNAL-CANARY'), 'Only the reference, never internal error text, belongs in the runner failure');
  const independentStatus = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.independent-job-status',
    steps: [{ action: 'goto', url: '/persistent-status' }, { action: 'submitForm', target: '#save-status', rejectFlashErrors: true },
      { action: 'assert', target: '#saved', test: 'visible' }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(independentStatus.status, 'passed', independentStatus.error);
  const blockedSubmit = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.submit-blocked',
    steps: [{ action: 'goto', url: '/forms' }, { action: 'submitForm', target: '#blocked', timeoutMs: 2000 }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(blockedSubmit.status, 'failed', 'A submit stopped by browser validation must not pass as a navigation');
  const wrongCompare = await multiInterpreter.executeJourney(extraPage, {
    journeyId: 'contract.greater-than', steps: [{ action: 'compare', actual: 3, greaterThan: 3 }]
  }, new VariableContext(), testBaseUrl);
  assert.equal(wrongCompare.status, 'failed');
  await extraPage.close();
  const datedRng = new DeterministicRandom(1);
  assert.match(datedRng.date(0, 'MMYY'), /^\d{4}$/);
  assert.equal(datedRng.date(0, 'YYYY-MM-DD'), datedRng.date(0));
  assert(validator.validateJourney({ schemaVersion: 1, id: 'x.when', execution: { capability: 'observe' }, steps: [{ action: 'reload', when: { actual: 'a' } }] })
    .some(e => e.includes("'when' requires")));
  console.log('   [PASS] select2, download, submit status, when, matches, greaterThan, capture pattern and date format work.');

  // Test Case H: validator rejects ledger in observe journeys and write journeys without cleanup
  const ledgerObs = validator.validateJourney({ schemaVersion: 1, id: 'x.obs', execution: { capability: 'observe' }, steps: [{ action: 'ledger', kind: 'a', id: '1' }] });
  assert(ledgerObs.some(e => e.includes('must not record ownership-ledger')));
  const noCleanup = validator.validateJourney({ schemaVersion: 1, id: 'x.w', execution: { capability: 'write-fixtures' }, steps: [{ action: 'reload' }] });
  assert(noCleanup.some(e => e.includes("must declare a 'cleanup'")));
  console.log('   [PASS] Validator enforces observe/write policy.');

  for (const c of sessionContexts) await c.close();
  await browser.close();
  await new Promise(resolve => server.close(resolve));

  // Clean up test run directory
  fs.rmSync(tmpRunDir, { recursive: true, force: true });

  console.log('\n=== All Phase 1 Contract Tests Passed Successfully! ===\n');
}

runContractTests().catch(err => {
  console.error('\n[CONTRACT TEST FAILED]:', err);
  process.exit(1);
});
