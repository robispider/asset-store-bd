import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import { chromium } from 'playwright';
import { inspectInterface } from '../bridge/interface-inspections.mjs';
import { interfaceNumber } from '../core/interface-values.mjs';
import { browserJourneyBlock } from '../bridge/browser-bridge.mjs';
import { CaptureEngine } from '../actions/capture.mjs';
import { VariableContext } from '../core/variables.mjs';
import { redactObject } from '../reporters/redaction.mjs';

test('personal verification codes stay usable in memory but never enter evidence', async () => {
  const vars = new VariableContext({});
  const engine = new CaptureEngine();
  const result = await engine.capture(null, { as: 'personalCode', value: 'Z8P Q6W', sensitive: true }, null, vars);
  assert.equal(vars.interpolate('${captured.personalCode}'), 'Z8P Q6W');
  assert(!JSON.stringify(redactObject({ result, compact: 'Z8PQ6W', variables: vars.captured })).includes('Z8P'));
  assert(!JSON.stringify(redactObject({ url: '/join?code=Z8P+Q6W', encoded: 'Z8P%20Q6W' })).includes('Z8P'));
  await assert.rejects(engine.capture(null, { as: 'bad', value: 'F7R T4V', sensitive: true, pattern: '^never$' }, null, vars), error => !error.message.includes('F7R'));
});

test('interface inspection reopens actor-scoped screens and never substitutes a database observation', async () => {
  const requests = [];
  const server = http.createServer((req, res) => {
    requests.push({ method: req.method, path: req.url });
    const employee = req.headers.cookie === 'actor=employee';
    res.setHeader('content-type', 'text/html; charset=utf-8');
    if (req.url === '/gov-requests/my-requests/42' && employee) return res.end(
      '<div data-request-number="LIVE-42" data-status-key="request_received"><table><tr data-line-id="9"><td>Paper</td><td>2</td><td>2</td><td>2</td></tr></table><div id="history">Employee acknowledged; Keeper issued</div></div>');
    if (req.url === '/gov-store/operations/kardex/consumable/5' && !employee) return res.end(
      '<div class="gs-kpis">' + ['১০০', '+৬', '−২', '১০৪'].map(x => `<span class="gs-kpi__value">${x}</span>`).join('') + '</div>');
    res.statusCode = 403; res.end('Forbidden');
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const baseUrl = `http://127.0.0.1:${server.address().port}`;
  const browser = await chromium.launch();
  try {
    const employee = await browser.newContext();
    const keeper = await browser.newContext();
    await employee.addCookies([{ name: 'actor', value: 'employee', url: baseUrl }]);
    const employeePage = await employee.newPage();
    const keeperPage = await keeper.newPage();
    const received = await inspectInterface(employeePage, 'ui.request', { id: 42, view: 'my-requests' }, baseUrl);
    assert.equal(received.status, 'request_received');
    assert.equal(received.lines[0].issued, '2');
    assert.equal(received.acknowledgeAvailable, false);
    assert.equal(received.evidenceSource, 'rendered-interface');
    assert.equal(employeePage.url(), 'about:blank', 'Readback must preserve the actor workflow page');
    const stock = await inspectInterface(keeperPage, 'ui.stockCard', { type: 'consumable', id: 5 }, baseUrl);
    assert.equal(stock.closing, 104); assert.equal(stock.issued, 2);
    await assert.rejects(inspectInterface(keeperPage, 'ui.request', { id: 42, view: 'my-requests' }, baseUrl), /screen unavailable \(403\)/);
    await assert.rejects(inspectInterface(employeePage, 'document.record', {}, baseUrl), /Unsupported interface inspection/);
    assert(requests.every(request => request.method === 'GET'), 'Inspections must not mutate the application');
    assert.equal(browserJourneyBlock({}, { cases: [{ steps: [{ action: 'inspect' }] }] }, 'shadow'), null);
    assert.equal(browserJourneyBlock({}, { cases: [{ steps: [{ action: 'observe' }] }] }, 'shadow')[0], 'fixtures.database-observation-required');
    assert.equal(interfaceNumber('১,২৩৪.৫০ units'), 1234.5);
    assert.throws(() => interfaceNumber('unavailable'), /does not contain a number/);
  } finally {
    await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
});
