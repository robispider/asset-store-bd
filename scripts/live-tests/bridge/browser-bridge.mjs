import Papa from 'papaparse';
import { assertMatchingTarget } from '../core/target.mjs';
import { registerSecret } from '../reporters/redaction.mjs';

const ROOT = '/gov-store/admin/experiments';
const MATRIX = '/gov-store/access/matrix';
const fail = (code, message) => { throw Object.assign(new Error(message), { code }); };
const uuid = value => /\/[0-9a-f-]{36}$/i.test(value) ? value.split('/').pop() : null;

export function validateAccountProfile(profile, baseUrl) {
  assertMatchingTarget(baseUrl, { appUrl: profile?.baseUrl });
  if (!['local', 'staging', 'testing'].includes(profile.environment)) {
    fail('credentials.environment-required', 'Declare local, staging or testing in the target account profile. Production testing is unsupported.');
  }
  if ((!profile.bootstrap?.username && !(profile.database && Number.isSafeInteger(profile.bootstrap?.userId) && profile.bootstrap.userId > 0))
    || typeof profile.bootstrap?.password !== 'string' || !profile.bootstrap.password) {
    fail('credentials.bootstrap-required', 'Supply the target superadmin username and password for browser fixture discovery.');
  }
  for (const account of [profile.bootstrap, ...Object.values(profile.accounts || {})]) {
    registerSecret(account?.password);
  }
  return profile;
}

export function parsePrivateCsv(text, required) {
  const parsed = Papa.parse(text.replace(/^\uFEFF/, ''), { header: true, skipEmptyLines: 'greedy' });
  if (parsed.errors.length || required.some(name => !parsed.meta.fields?.includes(name))) {
    fail('fixtures.invalid-export', 'The target account or permission export has an unsupported CSV structure.');
  }
  for (const row of parsed.data) registerSecret(row.password);
  return parsed.data;
}

/** Check dependencies before resolving credentials; never substitute UI values for DB assertions. */
export function browserJourneyBlock(journey, plan, accessMode, hasDatabase = false) {
  const steps = plan.cases.flatMap(c => [...c.steps, ...(c.cleanup?.steps || [])]);
  if (!hasDatabase && steps.some(s => ['observe', 'waitObserve'].includes(s.action))) {
    return ['fixtures.database-observation-required', 'This journey requires independent database observations. Browser account mode cannot supply them; use the matching database bridge.'];
  }
  if (!hasDatabase && Object.keys(journey.fixtures?.records || {}).length) {
    return ['fixtures.record-discovery-required', 'This journey needs a record discovery adapter that is not available in browser account mode.'];
  }
  if (accessMode === 'unknown' && steps.some(s => s.modes || s.expectStatusByMode)) {
    return ['preflight.access-mode-unknown', 'Declare the target access mode before running mode-specific expectations.'];
  }
  return null;
}

/** Browser-only fixture discovery from the target installation, starting with supplied credentials. */
export class BrowserBridge {
  constructor({ browser, baseUrl, profile, aliases = {}, allowWrites = false, ownership = [], eventLogger, databaseBridge }) {
    this.browser = browser;
    this.baseUrl = new URL(baseUrl).origin;
    this.profile = validateAccountProfile(profile, baseUrl);
    this.aliases = aliases;
    this.allowWrites = allowWrites;
    this.ownership = ownership;
    this.eventLogger = eventLogger;
    this.verified = new Map();
    this.accounts = [];
    this.datasets = [];
    this.permissions = new Map();
    this.labels = new Map();
    this.databaseBridge = databaseBridge;
  }

  async newContext() {
    const context = await this.browser.newContext({ acceptDownloads: true });
    await context.route('**/*', route => {
      const request = route.request();
      // Prevent a redirected login or form from sending credentials to another origin.
      if (request.isNavigationRequest() && new URL(request.url()).origin !== this.baseUrl) return route.abort();
      return route.continue();
    });
    return context;
  }

  async login(context, account) {
    registerSecret(account.password);
    const page = await context.newPage();
    try {
      await page.goto(new URL('/login', this.baseUrl).href, { waitUntil: 'load' });
      if (new URL(page.url()).origin !== this.baseUrl) fail('credentials.target-changed', 'The login page redirected away from the authorized target.');
      await page.locator("input[name='username']").fill(account.username);
      await page.locator("input[name='password']").fill(account.password);
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'load', timeout: 30000 }),
        page.locator("form[action*='login'] button[type='submit']").click()
      ]);
      if (new URL(page.url()).pathname.replace(/\/$/, '') === '/login') {
        fail('credentials.login-rejected', 'The target rejected a supplied or discovered login. Check the target account, password, activation and any additional sign-in requirements.');
      }
      if (!await page.locator('li.user-menu > a, .navbar-custom-menu .dropdown-toggle').count()) {
        fail('credentials.login-incomplete', 'The target did not show an authenticated user menu after login.');
      }
      return page;
    } catch (error) {
      if (error.code) throw error;
      fail('credentials.login-incomplete', 'The target login could not complete. No login screenshot or credential details were recorded.');
    }
  }

  async download(page, selector) {
    const download = await Promise.all([
      page.waitForEvent('download', { timeout: 30000 }),
      page.locator(selector).click()
    ]).then(([value]) => value);
    const stream = await download.createReadStream();
    if (!stream) fail('fixtures.export-failed', 'The target export could not be read.');
    const chunks = [];
    let size = 0;
    for await (const chunk of stream) {
      const buffer = Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk);
      size += buffer.length;
      chunks.push(buffer);
      if (size > 5_000_000) fail('fixtures.export-too-large', 'The target export exceeds the supported size.');
    }
    return Buffer.concat(chunks).toString('utf8');
  }

  async discoverDatasets(page) {
    let next = new URL(ROOT, this.baseUrl).href;
    this.datasets = [];
    for (let count = 0; next && count < 50; count++) {
      const response = await page.goto(next, { waitUntil: 'load' });
      if (!response?.ok() || !await page.locator("form[method='POST'][action$='/experiments'], form[method='post'][action$='/experiments']").count()) {
        fail('credentials.superadmin-required', 'The supplied account cannot open experiment management on this target. A permitted nonproduction environment and an active superadmin are required.');
      }
      const rows = await page.locator('table tbody tr').evaluateAll(rows => rows.map(row => ({
        href: row.querySelector('td a')?.getAttribute('href'), label: row.querySelector('td')?.textContent.trim(),
        status: row.querySelectorAll('td')[2]?.textContent.trim()
      })));
      for (const row of rows) {
        if (row.status === 'ready' && row.href && uuid(new URL(row.href, this.baseUrl).pathname)) {
          const url = new URL(row.href, this.baseUrl);
          if (url.origin !== this.baseUrl) fail('fixtures.target-changed', 'A dataset link points to a different installation.');
          this.datasets.push({ id: uuid(url.pathname), label: row.label, status: row.status, url: url.href });
        }
      }
      const nextLink = page.locator('a[rel="next"]');
      const href = await nextLink.count() ? await nextLink.getAttribute('href') : null;
      next = href ? new URL(href, this.baseUrl).href : null;
      if (next && new URL(next).origin !== this.baseUrl) fail('fixtures.target-changed', 'Dataset pagination points to another installation.');
    }
  }

  async prepareDataset(page) {
    if (!this.allowWrites) fail('fixtures.no-ready-dataset', 'No ready dataset exists on this target. Enable fixture writes to create a marked Quick dataset through the UI, or supply an existing ready dataset.');
    await page.goto(new URL(ROOT, this.baseUrl).href);
    await page.locator('#label').fill(`LIVE browser fixture ${Date.now()}`);
    await page.locator('#profile').selectOption('quick');
    await page.locator('#seed').fill('2026');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load' }),
      page.locator("form[action$='/experiments'] button[type='submit']").click()
    ]);
    const id = uuid(new URL(page.url()).pathname);
    if (!id) fail('fixtures.population-rejected', 'The target did not create an experiment run. Review its UI error and any active operation.');
    this.ownership.push({ kind: 'experiment-dataset', id, actor: 'supplied-superadmin',
      cleanup: 'retained', effect: 'Marked Quick dataset created through the target UI for browser testing.' });
    this.eventLogger?.log('fixture_created', { kind: 'experiment-dataset', id, cleanup: 'retained' });
    const deadline = Date.now() + 600_000;
    let previous;
    while (Date.now() < deadline) {
      const status = (await page.locator('#experiment-status').textContent()).trim();
      if (status !== previous) this.eventLogger?.log('fixture_population', { dataset: id, status });
      previous = status;
      if (status === 'ready') return;
      if (!['pending', 'running'].includes(status)) fail('fixtures.population-failed', `The marked target dataset finished with status ${status}; it is retained for review.`);
      await page.waitForTimeout(2000);
      await page.reload({ waitUntil: 'load' });
    }
    fail('fixtures.population-timeout', 'The marked dataset did not become ready in ten minutes. Check the target experiment worker. The run is retained; no existing dataset was changed.');
  }

  async preflight() {
    const identity = this.databaseBridge ? await this.databaseBridge.verifyAccount(this.profile.bootstrap) : null;
    if (identity && !identity.isSuperuser) fail('credentials.superadmin-required', 'The supplied bootstrap account is not an active superadmin in the selected database.');
    if (identity) this.profile = { ...this.profile, bootstrap: { ...this.profile.bootstrap, username: identity.username || this.profile.bootstrap.username } };
    this.adminContext = await this.newContext();
    const page = await this.login(this.adminContext, this.profile.bootstrap);
    if (identity) {
      await page.goto(new URL('/gov-store/access', this.baseUrl).href, { waitUntil: 'load' });
      const signedInId = Number(await page.locator('.tenant-scope-theme').getAttribute('data-access-actor'));
      if (signedInId && signedInId !== identity.id) fail('credentials.target-database-mismatch', 'The browser and database identify different bootstrap users.');
    }
    await this.discoverDatasets(page);
    if (!this.datasets.length) {
      await this.prepareDataset(page);
      await this.discoverDatasets(page);
    }
    const response = await page.goto(new URL(MATRIX, this.baseUrl).href, { waitUntil: 'load' });
    if (!response?.ok()) fail('fixtures.permission-matrix-unavailable', 'The supplied superadmin cannot read the target permission matrix.');
    const labels = await page.locator('table tbody tr > th[scope="row"]').allTextContents();
    const definitions = parsePrivateCsv(await this.download(page, "a[href*='format=csv']"), ['Ability', 'Roles']);
    if (labels.length !== definitions.length || !labels.length) fail('fixtures.permission-matrix-unavailable', 'The target permission matrix and its CSV disagree.');
    definitions.forEach((definition, index) => {
      this.permissions.set(definition.Ability, definition.Roles.split('|').filter(Boolean));
      const label = labels[index].trim();
      if (this.labels.has(label)) fail('fixtures.ambiguous-ability-label', 'The target has ambiguous permission labels; browser role verification cannot proceed.');
      this.labels.set(label, definition.Ability);
    });
    const database = this.databaseBridge ? await this.databaseBridge.preflight() : null;
    if (database) assertMatchingTarget(this.baseUrl, database);
    return { ...database, appUrl: this.baseUrl, production: database?.production || false, environment: this.profile.environment,
      environmentSource: 'operator-declared; experiment-management access verified on target',
      accessMode: this.profile.accessMode || 'unknown', accessModeSource: 'operator-declared',
      mailer: 'unknown', queue: 'unknown', database: database?.database || 'not-accessed', readyDatasets: this.datasets.length,
      fixtureSource: 'target-browser', independentDatabaseObservations: Boolean(database) };
  }

  ability(name) {
    const definition = this.aliases[name];
    if (definition?.native) fail('fixtures.native-permission-required', 'This journey needs native permission verification that browser account discovery does not yet support.');
    const ability = definition?.ability || name;
    if (!this.permissions.has(ability)) fail('fixtures.unknown-capability', `The target permission matrix does not define capability ${name}.`);
    return ability;
  }

  async loadAccounts(dataset) {
    if (this.loadedDataset === dataset.id) return;
    const page = await this.adminContext.newPage();
    try {
      await page.goto(dataset.url, { waitUntil: 'load' });
      if ((await page.locator('#experiment-status').textContent()).trim() !== 'ready') {
        fail('fixtures.dataset-changed', 'The selected target dataset is no longer ready.');
      }
      const rows = parsePrivateCsv(await this.download(page, "a[href$='/accounts']"), ['username', 'password', 'home_office_id', 'office_roles', 'oversight_role']);
      // Company administrators have no home office; they are kept (officeId null) for company-scoped actors.
      this.accounts = rows.filter(row => row.username && row.password && (/^\d+$/.test(row.home_office_id) || row.oversight_role === 'company_admin')).map(row => ({
        username: row.username, password: row.password, display_name: row.name,
        officeId: /^\d+$/.test(row.home_office_id) ? Number(row.home_office_id) : null, companyId: Number(row.company_id),
        roles: ['authenticated', ...row.office_roles.split(',').map(r => r.trim()).filter(Boolean), ...(row.oversight_role ? [row.oversight_role] : [])]
      }));
      for (const supplied of Object.values(this.profile.accounts || {})) {
        const account = this.accounts.find(row => row.username === supplied.username);
        if (account && supplied.password) account.password = supplied.password;
      }
      this.loadedDataset = dataset.id;
      this.verified.clear();
    } finally { await page.close(); }
  }

  async verify(account) {
    if (this.verified.has(account.username)) return this.verified.get(account.username);
    const context = await this.newContext();
    try {
      const page = await this.login(context, account);
      const response = await page.goto(new URL('/gov-store/access', this.baseUrl).href, { waitUntil: 'load' });
      if (!response?.ok()) fail('fixtures.access-unavailable', 'A candidate cannot open My access on the target.');
      const semanticOffice = await page.locator('.tenant-scope-theme').getAttribute('data-access-office');
      const officeText = await page.locator('.tenant-scope-theme .box-body > p').first().textContent();
      const officeId = Number(semanticOffice ?? officeText.match(/:\s*(\d+)\s*$/)?.[1]);
      if (account.officeId && officeId !== account.officeId) fail('fixtures.office-mismatch', 'A candidate login resolved to a different working office on the target.');
      const semanticAbilities = await page.locator('[data-access-ability]').evaluateAll(nodes => nodes.map(node => node.getAttribute('data-access-ability')));
      const labels = await page.locator('.tenant-scope-theme .box-body > ul').first().locator('li').allTextContents();
      const abilities = semanticOffice !== null ? semanticAbilities : labels.map(label => this.labels.get(label.trim()));
      if (abilities.some(a => !a)) fail('fixtures.access-label-mismatch', 'My access contains a capability that cannot be matched to the target permission matrix.');
      const rolesText = await page.locator('.tenant-scope-theme').getAttribute('data-access-roles');
      const actorId = Number(await page.locator('.tenant-scope-theme').getAttribute('data-access-actor')) || null;
      const result = { account, abilities, id: actorId, roles: rolesText ? JSON.parse(rolesText) : account.roles };
      this.verified.set(account.username, result);
      return result;
    } finally { await context.close(); }
  }

  async resolveContext(fixtures, datasetId) {
    if (!this.databaseBridge && Object.keys(fixtures?.records || {}).length) fail('fixtures.record-discovery-required', 'Browser record discovery is not available for this journey.');
    const dataset = this.datasets.find(run => !datasetId || run.id === datasetId);
    if (!dataset) fail('fixtures.unsatisfied-requirements', 'The requested ready dataset does not exist on the target.');
    await this.loadAccounts(dataset);
    if (this.databaseBridge) {
      const credentials = Object.fromEntries(this.accounts.map(account => [account.username, account.password]));
      const suppliedSuperusers = Object.entries(fixtures.actors || {}).filter(([, definition]) => definition.role === 'superuser' && definition.allowSuperuser);
      if (suppliedSuperusers.some(([, definition]) => definition.office || definition.activeMembership)) {
        fail('fixtures.superuser-context-required', 'This journey requires an office-bound superadmin context; its browser context-selection adapter is not available.');
      }
      const ordinaryActors = Object.fromEntries(Object.entries(fixtures.actors || {}).filter(([alias]) => !suppliedSuperusers.some(([supplied]) => supplied === alias)));
      const resolved = await this.databaseBridge.resolveContext({ ...fixtures, actors: ordinaryActors }, dataset.id, { fixtureCredentials: credentials });
      if (resolved.run.id !== dataset.id) fail('fixtures.target-database-mismatch', 'The target browser and database resolved different datasets.');
      for (const [alias, actor] of Object.entries(resolved.actors)) {
        // The actor's own office (the resolved office, or an office record it is bound to); company-scoped actors need no office.
        const scoped = fixtures.actors[alias].activeMembership === false;
        const officeId = actor.office_id ?? resolved.office.id;
        const companyId = officeId === resolved.office.id ? resolved.office.company_id
          : Object.values(resolved.records || {}).find(record => record.id === officeId)?.company_id;
        const account = this.accounts.find(candidate => candidate.username === actor.username
          && (scoped || candidate.officeId === officeId) && candidate.companyId === (scoped ? resolved.office.company_id : companyId));
        if (!account) fail('fixtures.target-database-mismatch', 'The target database selected an account absent from this target dataset or office export.');
        const verified = await this.verify(account);
        if (verified.id && verified.id !== actor.id) fail('fixtures.target-database-mismatch', 'The target UI and database identify different signed-in users.');
        const required = (fixtures.actors[alias].capabilities?.allOf || []).filter(name => !this.aliases[name]?.native).map(name => this.ability(name));
        const denied = (fixtures.actors[alias].capabilities?.noneOf || []).filter(name => !this.aliases[name]?.native).map(name => this.ability(name));
        if (!required.every(ability => verified.abilities.includes(ability)) || denied.some(ability => verified.abilities.includes(ability))) {
          fail('fixtures.target-access-mismatch', 'The target UI does not confirm the abilities resolved from the selected database.');
        }
      }
      for (const [alias, definition] of suppliedSuperusers) {
        const identity = await this.databaseBridge.verifyAccount(this.profile.bootstrap);
        const verified = await this.verify({ ...this.profile.bootstrap, roles: ['superuser'], officeId: null });
        if (!identity.isSuperuser || (verified.id && verified.id !== identity.id) || !verified.roles.includes('superuser')) {
          fail('fixtures.target-database-mismatch', 'The supplied target superadmin does not match the selected database.');
        }
        const required = (definition.capabilities?.allOf || []).map(name => this.ability(name));
        const denied = (definition.capabilities?.noneOf || []).map(name => this.ability(name));
        if (!required.every(ability => verified.abilities.includes(ability)) || denied.some(ability => verified.abilities.includes(ability))) {
          fail('fixtures.unsatisfied-requirements', 'The supplied superadmin does not meet this journey capability requirement.');
        }
        resolved.actors[alias] = { ...this.profile.bootstrap, id: identity.id, identitySource: 'supplied-target-superadmin-verified-in-UI-and-database' };
      }
      return resolved;
    }
    const search = { offices: 0, users: 0, rejected: {} };
    for (const officeId of [...new Set(this.accounts.map(account => account.officeId))]) {
      if (fixtures?.office?.id && Number(fixtures.office.id) !== officeId) continue;
      search.offices++;
      const actors = {}, used = new Set();
      for (const [alias, definition] of Object.entries(fixtures?.actors || {})) {
        if (definition.emptyBasket) fail('fixtures.basket-discovery-required', 'This journey requires independent empty-basket fixture selection, which browser discovery does not yet support.');
        const required = (definition.capabilities?.allOf || []).map(name => this.ability(name));
        const denied = (definition.capabilities?.noneOf || []).map(name => this.ability(name));
        const candidates = this.accounts.filter(account => account.officeId === officeId && !used.has(account.username))
          .sort((a, b) => a.roles.length - b.roles.length || a.username.localeCompare(b.username));
        if (definition.allowSuperuser && !definition.activeMembership && !definition.office) {
          candidates.push({ ...this.profile.bootstrap, display_name: 'Supplied superadmin', roles: ['superuser'], officeId: null });
        }
        const matches = [];
        for (const account of candidates) {
          search.users++;
          try {
            const verified = await this.verify(account);
            if (verified.roles.includes('superuser') && !definition.allowSuperuser) continue;
            if (definition.role && !verified.roles.includes(definition.role)) continue;
            if (!required.every(ability => verified.abilities.includes(ability)) || denied.some(ability => verified.abilities.includes(ability))) continue;
            matches.push({ account, abilities: verified.abilities, id: verified.id });
          } catch (error) {
            if (!error.code) throw error;
            search.rejected[error.code] = (search.rejected[error.code] || 0) + 1;
          }
        }
        matches.sort((a, b) => a.abilities.length - b.abilities.length || a.account.username.localeCompare(b.account.username));
        const selected = matches[0]?.account;
        if (selected) {
          actors[alias] = { username: selected.username, password: selected.password, display_name: selected.display_name,
            id: matches[0].id, identitySource: 'target-account-export-and-My-access' };
          used.add(selected.username);
        }
        if (!actors[alias]) break;
      }
      if (Object.keys(actors).length === Object.keys(fixtures?.actors || {}).length) {
        const first = this.accounts.find(account => account.officeId === officeId);
        return { run: { id: dataset.id, label: dataset.label }, office: { id: officeId, company_id: first.companyId,
          name: `Target office ${officeId}` }, actors, records: {}, search };
      }
    }
    throw Object.assign(new Error('No target office and verified users satisfy this journey. Missing roles are not granted automatically.'), {
      code: 'fixtures.unsatisfied-requirements', search
    });
  }

  async observe(check, args) {
    if (this.databaseBridge) return await this.databaseBridge.observe(check, args);
    fail('fixtures.database-observation-required', 'Browser mode cannot supply independent database observations.');
  }
  async close() { await this.adminContext?.close(); }
}
