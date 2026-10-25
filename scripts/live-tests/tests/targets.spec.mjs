import assert from 'node:assert/strict';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { BrowserBridge, browserJourneyBlock, parsePrivateCsv, validateAccountProfile } from '../bridge/browser-bridge.mjs';
import { loadTargets, publicTargets } from '../core/targets.mjs';
import { redactObject } from '../reporters/redaction.mjs';
import { startDashboard } from '../dashboard/server.mjs';

const datasetId = '01a12efd-3c2a-71cd-ad5e-d624ca18c6bf';
const csv = 'username,password,name,company_id,home_office_id,office_roles,oversight_role\n'
  + 'officeadmin,TARGET-ADMIN-PASS,Admin,9,160,,\n'
  + 'keeper,TARGET-KEEPER-PASS,Keeper,9,160,storekeeper,\n'
  + 'employee,TARGET-EMPLOYEE-PASS,Employee,9,160,,\n';
const abilities = { 'organization.office.manage': ['office_admin'], 'requests.submit': ['authenticated'],
  'storeops.documents.post': ['storekeeper'], 'catalog.master.manage': ['superuser'] };
const users = { root: 'ROOT-CANARY-PASSWORD', officeadmin: 'TARGET-ADMIN-PASS', keeper: 'TARGET-KEEPER-PASS', employee: 'TARGET-EMPLOYEE-PASS' };
let ready = true, creations = 0;
const server = http.createServer(async (req, res) => {
  const actor = (req.headers.cookie || '').match(/actor=([^;]+)/)?.[1];
  const html = content => { res.writeHead(200, { 'content-type': 'text/html' }); res.end(content); };
  const redirect = (location, extra = {}) => { res.writeHead(302, { location, ...extra }); res.end(); };
  // Snipe-IT header, with a gov dropdown beside the account menu (the login wait must not be ambiguous).
  const menu = '<header class="main-header"><div class="navbar-custom-menu"><ul><li class="dropdown notifications-menu"><a href="#" class="dropdown-toggle">Inbox</a></li>'
    + '<li class="dropdown user user-menu"><a href="#" class="dropdown-toggle">Account</a></li></ul></div></header>';
  if (req.url === '/login' && req.method === 'GET') return html('<form method="post" action="/login"><input name="username"><input name="password" type="password"><button type="submit">Login</button></form>');
  if (req.url === '/login') {
    let body = ''; for await (const chunk of req) body += chunk;
    const fields = new URLSearchParams(body);
    if (users[fields.get('username')] !== fields.get('password')) return redirect('/login');
    return redirect('/home', { 'set-cookie': `actor=${fields.get('username')}; Path=/; HttpOnly` });
  }
  if (!actor) return redirect('/login');
  if (req.url === '/home') return html(menu);
  if (req.url === '/gov-store/admin/experiments' && actor === 'root') {
    if (req.method === 'POST') { creations++; ready = true; return redirect(`/gov-store/admin/experiments/${datasetId}`); }
    return html(menu + '<form method="POST" action="/gov-store/admin/experiments"><input id="label"><select id="profile"><option value="quick">Quick</option></select><input id="seed"><button type="submit">Populate</button></form>'
      + (ready ? `<table><tbody><tr><td><a href="/gov-store/admin/experiments/${datasetId}">Target dataset</a></td><td>Quick</td><td>ready</td></tr></tbody></table>` : '<table></table>'));
  }
  if (req.url === `/gov-store/admin/experiments/${datasetId}` && actor === 'root') return html(menu + `<strong id="experiment-status">ready</strong><a href="/gov-store/admin/experiments/${datasetId}/accounts">Accounts</a>`);
  if (req.url === `/gov-store/admin/experiments/${datasetId}/accounts` && actor === 'root') {
    res.writeHead(200, { 'content-type': 'text/csv', 'content-disposition': 'attachment; filename=accounts.csv' }); return res.end(csv);
  }
  if (req.url === '/gov-store/access/matrix' && actor === 'root') return html('<a href="/gov-store/access/matrix?format=csv">Export</a><table><tbody>'
    + Object.keys(abilities).map(ability => `<tr><th scope="row">${ability}</th><td>Roles</td></tr>`).join('') + '</tbody></table>');
  if (req.url === '/gov-store/access/matrix?format=csv' && actor === 'root') {
    res.writeHead(200, { 'content-type': 'text/csv', 'content-disposition': 'attachment; filename=permissions.csv' });
    return res.end('Ability,Roles\n' + Object.entries(abilities).map(([ability, roles]) => `${ability},${roles.join('|')}`).join('\n'));
  }
  if (req.url === '/gov-store/access') {
    const role = { root: 'superuser', officeadmin: 'office_admin', keeper: 'storekeeper', employee: 'authenticated' }[actor];
    const allowed = Object.entries(abilities).filter(([, roles]) => actor === 'root' || roles.includes(role) || roles.includes('authenticated')).map(([ability]) => ability);
    const id = { root: 1, officeadmin: 2, keeper: 3, employee: 4 }[actor];
    // Bengali visible labels exercise language-independent actor/capability verification.
    return html(menu + `<div class="tenant-scope-theme" data-access-office="${actor === 'root' ? '' : 160}" data-access-actor="${id}" data-access-roles='${JSON.stringify(['authenticated', role])}'><div class="box-body"><p>কার্যালয়: ${actor === 'root' ? '—' : 160}</p><ul>`
      + allowed.map(ability => `<li data-access-ability="${ability}">অনুমতি</li>`).join('') + '</ul></div></div>');
  }
  res.writeHead(403); res.end('Forbidden');
});

await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const baseUrl = `http://127.0.0.1:${server.address().port}`;
const profile = { id: 'target-test', label: 'Test target', baseUrl, environment: 'testing', accessMode: 'enforce', bootstrap: { username: 'root', password: users.root } };
const browser = await chromium.launch({ headless: true });
const connections = [];
const make = options => { const bridge = new BrowserBridge({ browser, baseUrl, profile, ...options }); connections.push(bridge); return bridge; };
const root = path.resolve('storage/app/private/live-tests');
fs.mkdirSync(root, { recursive: true });
const temp = fs.mkdtempSync(path.join(root, 'target-contract-'));
let dashboard;
let dashboardRun;
const previousTargetsFile = process.env.LIVE_TEST_TARGETS_FILE;
try {
  const file = path.join(temp, 'targets.json');
  fs.writeFileSync(file, JSON.stringify({ targets: [profile] }));
  const targets = loadTargets(process.cwd(), file);
  assert.equal(targets.length, 1);
  const publicData = JSON.stringify(publicTargets(targets));
  assert(!publicData.includes(users.root) && !publicData.includes('bootstrap'));
  assert.throws(() => validateAccountProfile({ ...profile, environment: 'production' }, baseUrl));
  assert.throws(() => validateAccountProfile(profile, 'http://elsewhere.test'), err => err.code === 'preflight.target-mismatch');
  assert.equal(parsePrivateCsv('\uFEFFusername,password\r\n"test,user","p,ass"\r\n', ['username', 'password'])[0].username, 'test,user');
  assert.throws(() => parsePrivateCsv('username\nname', ['password']), err => err.code === 'fixtures.invalid-export');
  const plan = { cases: [{ steps: [{ action: 'observe' }], cleanup: {} }] };
  assert.equal(browserJourneyBlock({}, plan, 'enforce')[0], 'fixtures.database-observation-required');
  assert.equal(browserJourneyBlock({}, plan, 'enforce', true), null);
  const bridge = make();
  const preflight = await bridge.preflight();
  assert.equal(preflight.fixtureSource, 'target-browser');
  assert.equal(preflight.independentDatabaseObservations, false);
  const actors = { officeAdmin: { capabilities: { allOf: ['organization.office.manage'] } }, employee: { capabilities: { allOf: ['requests.submit'] } } };
  const resolved = await bridge.resolveContext({ actors });
  assert.equal(resolved.run.id, datasetId);
  assert.equal(resolved.actors.officeAdmin.username, 'officeadmin');
  assert.equal(resolved.actors.employee.username, 'employee', 'Ordinary journey must choose the least privileged actual actor');
  assert.equal(resolved.actors.employee.id, 4);
  const profileAdministrator = await bridge.resolveContext({ actors: {
    officeAdmin: { role: 'office_admin', capabilities: { allOf: ['organization.office.manage'] } }
  } });
  assert.equal(profileAdministrator.actors.officeAdmin.username, 'officeadmin',
    'Profile-assigned office administrator must be verified even when the export has no responsibility slug');
  assert(!JSON.stringify(redactObject(resolved)).includes(users.employee));
  await assert.rejects(bridge.resolveContext({ actors: { invalid: { capabilities: { allOf: ['catalog.master.manage'] } } } }), err => err.code === 'fixtures.unsatisfied-requirements');
  const privileged = await bridge.resolveContext({ actors: { admin: { role: 'superuser', allowSuperuser: true, capabilities: { allOf: ['catalog.master.manage'] } } } });
  assert.equal(privileged.actors.admin.username, 'root');
  await assert.rejects(bridge.observe('request.progress', {}), err => err.code === 'fixtures.database-observation-required');

  process.env.LIVE_TEST_TARGETS_FILE = file;
  dashboard = await startDashboard({ baseDir: process.cwd(), port: 0, quiet: true });
  const dashboardContext = await browser.newContext();
  const dashboardPage = await dashboardContext.newPage();
  await dashboardPage.goto(dashboard.url);
  await dashboardPage.locator('#target option[value="target-test"]').waitFor({ state: 'attached' });
  await dashboardPage.locator('#target').selectOption('target-test');
  assert.equal(await dashboardPage.locator('#baseUrl').inputValue(), baseUrl);
  assert.equal(await dashboardPage.locator('#baseUrl').getAttribute('readonly'), '');
  const api = (route, options = {}) => fetch(new URL(route, dashboard.url), { ...options,
    headers: { 'x-dashboard-token': dashboard.token, 'content-type': 'application/json' } });
  const publicState = await (await api('/api/state')).json();
  assert(!JSON.stringify(publicState).includes(users.root));
  const wrongTarget = await api('/api/run', { method: 'POST', body: JSON.stringify({ targetId: 'target-test', baseUrl: 'http://different.test', tests: ['user-stories.us-01'] }) });
  assert.equal(wrongTarget.status, 400);
  const launch = await api('/api/run', { method: 'POST', body: JSON.stringify({ targetId: 'target-test', baseUrl,
    tests: ['user-stories.us-01', 'user-stories.us-02', 'requests.catalog-progress-observation'] }) });
  assert.equal(launch.status, 200);
  dashboardRun = (await launch.json()).runId;
  let result;
  const deadline = Date.now() + 20000;
  do {
    result = await (await api(`/api/events?runId=${dashboardRun}`)).json();
    if (result.results?.summary && result.exitCode !== undefined && result.exitCode !== null) break;
    await new Promise(resolve => setTimeout(resolve, 100));
  } while (Date.now() < deadline);
  assert.equal(result.results?.summary.passed, 2, 'Configured target UI journeys should pass');
  assert.equal(result.results?.summary.blocked, 1, 'Missing independent database observation must remain blocked');
  assert.equal(result.provenance.targetId, profile.id);
  assert.equal(result.provenance.fixtureSource, 'target-browser');
  const reportDir = path.join(root, dashboardRun);
  assert(fs.existsSync(path.join(reportDir, 'report.html')), fs.readFileSync(path.join(reportDir, 'console.log'), 'utf8'));
  for (const file of ['provenance.json', 'results.json', 'events.jsonl', 'console.log', 'report.html']) {
    const output = fs.readFileSync(path.join(reportDir, file), 'utf8');
    for (const secret of Object.values(users)) assert(!output.includes(secret), 'Target credentials leaked to a report');
  }
  await dashboardContext.close();

  const db = {
    preflight: async () => ({ appUrl: baseUrl, database: 'target-fixture-database', production: false }),
    resolveContext: async (definition, id, extra) => {
      assert.equal(id, datasetId); assert.equal(extra.fixtureCredentials.keeper, users.keeper);
      return { run: { id }, office: { id: 160, company_id: 9 }, actors: Object.keys(definition.actors).length
        ? { keeper: { id: 3, username: 'keeper', password: users.keeper } } : {}, records: {} };
    },
    observe: async () => ({ quantity: 27 }),
    verifyAccount: async () => ({ id: 1, username: 'root', isSuperuser: true })
  };
  const hybrid = make({ databaseBridge: db });
  assert.equal((await hybrid.preflight()).independentDatabaseObservations, true);
  const keeperDef = { actors: { keeper: { capabilities: { allOf: ['storeops.documents.post'] } } } };
  assert.equal((await hybrid.resolveContext(keeperDef)).actors.keeper.id, 3);
  assert.equal((await hybrid.observe('inventory.consumable', {})).quantity, 27);
  assert.equal((await hybrid.resolveContext({ actors: { admin: { role: 'superuser', allowSuperuser: true } } })).actors.admin.id, 1);
  const numericBootstrap = make({ profile: { ...profile, database: { host: 'mock', database: 'mock', username: 'mock' },
    bootstrap: { userId: 1, password: users.root } }, databaseBridge: db });
  await numericBootstrap.preflight();
  assert.equal(numericBootstrap.profile.bootstrap.username, 'root');
  const wrong = make({ databaseBridge: { ...db, resolveContext: async () => ({ run: { id: 'another-dataset' } }) } });
  await wrong.preflight();
  await assert.rejects(wrong.resolveContext(keeperDef), err => err.code === 'fixtures.target-database-mismatch');
  const wrongActor = make({ databaseBridge: { ...db, resolveContext: async () => ({ run: { id: datasetId }, office: { id: 160, company_id: 9 }, actors: { keeper: { username: 'keeper', id: 999 } } }) } });
  await wrongActor.preflight();
  await assert.rejects(wrongActor.resolveContext(keeperDef), err => err.code === 'fixtures.target-database-mismatch');
  const badLogin = make({ profile: { ...profile, bootstrap: { username: 'root', password: 'WRONG-CANARY' } } });
  await assert.rejects(badLogin.preflight(), err => err.code === 'credentials.login-rejected');
  ready = false;
  await assert.rejects(make().preflight(), err => err.code === 'fixtures.no-ready-dataset');
  assert.equal(creations, 0);
  const ownership = [];
  await make({ allowWrites: true, ownership }).preflight();
  assert.equal(creations, 1);
  assert.equal(ownership[0].id, datasetId);
  assert.equal(ownership[0].cleanup, 'retained');
  console.log('Target profile, supplied login, target CSV discovery, least privilege, bilingual verification, database binding and fixture preparation tests passed.');
} finally {
  if (dashboard) await new Promise(resolve => dashboard.server.close(resolve));
  if (previousTargetsFile === undefined) delete process.env.LIVE_TEST_TARGETS_FILE;
  else process.env.LIVE_TEST_TARGETS_FILE = previousTargetsFile;
  for (const connection of connections) await connection.close();
  await browser.close();
  await new Promise(resolve => server.close(resolve));
  if (!path.resolve(temp).startsWith(root + path.sep)) throw new Error('Unsafe test cleanup path');
  fs.rmSync(temp, { recursive: true, force: true });
  if (dashboardRun) {
    const output = path.resolve(root, dashboardRun);
    if (!output.startsWith(root + path.sep) || !/^run_[A-Za-z0-9_-]+$/.test(dashboardRun)) throw new Error('Unsafe run cleanup path');
    fs.rmSync(output, { recursive: true, force: true });
  }
}
