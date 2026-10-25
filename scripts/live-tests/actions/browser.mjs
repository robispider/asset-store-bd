/**
 * GovStore Live Testing - Browser Action Engine
 */
import Papa from 'papaparse';

/** The HTTP redirect hops behind a navigation response, oldest first ("POST /path → 302"), for failure diagnosis. */
export async function redirectChain(response) {
  const hops = [];
  for (let request = response.request().redirectedFrom(); request; request = request.redirectedFrom()) {
    const status = (await request.response().catch(() => null))?.status();
    hops.unshift(`${request.method()} ${new URL(request.url()).pathname} → ${status ?? '?'}`);
  }
  return hops;
}

export class BrowserActionEngine {
  constructor(assertionEngine, captureEngine, locatorResolver) {
    this.assertions = assertionEngine;
    this.captureEngine = captureEngine;
    this.locators = locatorResolver;
  }

  async executeStep(page, step, varContext, baseUrl = 'http://snipeit.local') {
    const action = step.action;
    this.locators.vars = varContext;
    if (typeof step.target === 'string' && step.target.includes('${')) {
      step = { ...step, target: String(varContext.interpolate(step.target)) };
    }
    if (!page.__liveDialogs) {
      // Native confirm() dialogs from application pages are accepted and recorded, never silently ignored.
      page.__liveDialogs = [];
      page.on('dialog', d => { page.__liveDialogs.push({ type: d.type(), message: d.message() }); d.accept().catch(() => {}); });
      page.on('response', response => {
        if (response.request().isNavigationRequest() && response.frame() === page.mainFrame()) {
          page.__liveNavigation = { path: new URL(response.url()).pathname, status: response.status() };
        }
      });
    }

    switch (action) {
      case 'switchOffice': {
        const name = String(varContext.interpolate(step.officeName));
        await page.locator('#gov-office-chip > a.dropdown-toggle').click();
        const search = page.locator('#gs-office-search');
        const searches = await search.count() > 0 && await search.isVisible();
        if (searches) await search.fill(name);
        const choices = page.locator(searches ? '#gs-office-search-results button' : '#gov-office-chip .gs-office-picker-results button').filter({ hasText: name });
        await choices.first().waitFor({ state: 'visible', timeout: step.timeoutMs || 15000 });
        if (await choices.count() !== 1) throw new Error('Assertion failed: office picker did not expose exactly one matching office');
        const [response] = await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), choices.click()]);
        if (!response?.ok()) throw new Error(`Assertion failed: office switch returned ${response?.status()}`);
        return { action: 'switchOffice', officeName: name, channel: 'browser-interface' };
      }
      case 'goto': {
        let targetUrl = '';
        if (step.url) {
          targetUrl = String(varContext.interpolate(step.url));
          if (!targetUrl.startsWith('http://') && !targetUrl.startsWith('https://')) {
            targetUrl = new URL(targetUrl, baseUrl).toString();
          }
        } else if (step.route) {
          // Resolve named route with params
          targetUrl = this.resolveRoute(step.route, step.params || {}, varContext, baseUrl);
        } else {
          throw new Error('Goto action must specify "url" or "route"');
        }

        if (step.query) {
          const u = new URL(targetUrl);
          for (const [k, v] of Object.entries(varContext.interpolateDeep(step.query))) u.searchParams.set(k, String(v));
          targetUrl = u.toString();
        }
        if (new URL(targetUrl).origin !== new URL(baseUrl).origin) {
          throw new Error('Unsupported: journey navigation must stay on the selected target origin');
        }
        const response = await page.goto(targetUrl, { waitUntil: 'load' });
        const expected = [].concat(step.expectStatus ?? []).map(Number);
        if (new URL(targetUrl).pathname !== '/login' && new URL(page.url()).pathname === '/login') {
          throw new Error('Assertion failed: intended application screen redirected to login');
        }
        if (expected.length ? !expected.includes(response?.status()) : response?.status() >= 400) {
          throw new Error(`Assertion failed: application navigation returned ${response?.status()} at ${new URL(page.url()).pathname}`);
        }
        return { action: 'goto', url: targetUrl, status: response?.status() };
      }

      case 'click': {
        const locator = this.locators.resolve(page, step.target);
        if (step.waitForNavigation) {
          const [response] = await Promise.all([
            page.waitForNavigation({ waitUntil: 'load', timeout: step.timeoutMs || 30000 }),
            locator.click()
          ]);
          if (!response || response.status() >= 400) throw new Error(`Assertion failed: clicked navigation returned ${response?.status()}`);
          return { action: 'click', target: step.target, status: response.status(), url: page.url() };
        }
        if (step.waitForResponse) {
          // Register the network wait before the action; the request must be a non-GET to this path and succeed.
          const needle = String(varContext.interpolate(step.waitForResponse));
          const [response] = await Promise.all([
            page.waitForResponse(r => r.url().includes(needle) && r.request().method() !== 'GET', { timeout: step.timeoutMs || 15000 }),
            locator.click()
          ]);
          if (response.status() >= 400) {
            throw new Error(`Assertion failed: ${needle} responded ${response.status()}`);
          }
          return { action: 'click', target: step.target, response: { path: needle, status: response.status() } };
        }
        await locator.click();
        return { action: 'click', target: step.target };
      }

      case 'fill': {
        const locator = this.locators.resolve(page, step.target);
        const value = String(varContext.interpolate(step.value ?? ''));
        await locator.fill(value);
        return { action: 'fill', target: step.target };
      }

      case 'clear': {
        const locator = this.locators.resolve(page, step.target);
        await locator.clear();
        return { action: 'clear', target: step.target };
      }

      case 'select': {
        const locator = this.locators.resolve(page, step.target);
        const value = String(varContext.interpolate(step.value ?? ''));
        await locator.selectOption(value);
        return { action: 'select', target: step.target, value };
      }

      case 'check': {
        const locator = this.locators.resolve(page, step.target);
        await locator.check();
        return { action: 'check', target: step.target };
      }

      case 'uncheck': {
        const locator = this.locators.resolve(page, step.target);
        await locator.uncheck();
        return { action: 'uncheck', target: step.target };
      }

      case 'keyboard': {
        const key = step.key || 'Enter';
        await page.keyboard.press(key);
        return { action: 'keyboard', key };
      }

      case 'submitForm': {
        // Submit the form containing the target and wait for the resulting navigation.
        // Register the navigation wait before clicking: waitForLoadState alone resolves at once on the current page.
        const locator = this.locators.resolve(page, step.target);
        const navigation = page.waitForNavigation({ waitUntil: 'load', timeout: step.timeoutMs || 30000 }).catch(() => null);
        await locator.click();
        const response = await navigation;
        if (!response) throw new Error(`Assertion failed: submitting '${step.target}' did not load a new page (browser validation may have stopped it)`);
        // Optional expectStatus: the page the submission finally lands on (after redirects) must have one of these statuses.
        const expected = [].concat(step.expectStatus ?? []).map(Number);
        if (expected.length ? !expected.includes(response.status()) : response.status() >= 400) {
          throw new Error(`Assertion failed: submitting '${step.target}' landed on ${page.url()} with status ${response.status()}, expected ${expected.join(',')}`);
        }
        if (step.rejectFlashErrors) {
          // Inline job/status cards can describe an earlier independent failure.
          // Submission failures appear in the layout flash or the workspace's
          // immediate error summary, rather than inside an unrelated boxed panel.
          const errors = page.locator('.alert-danger:visible, .alert-error:visible, .cr > .gs-alert--danger:visible, .govmem > .gs-alert--danger:visible, .govorg > .gs-alert--danger:visible, .storeops-theme > .gs-alert--danger:visible');
          if (await errors.count()) {
            const text = await errors.allTextContents();
            const reference = text.join(' ').match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i)?.[0];
            throw new Error(`Assertion failed: application refused the submitted action${reference ? ` (reference ${reference})` : '; see private interface evidence'}`);
          }
        }
        return { action: 'submitForm', target: step.target, url: page.url(), status: response.status(), redirects: await redirectChain(response) };
      }

      case 'reload': {
        await page.reload({ waitUntil: 'load' });
        return { action: 'reload' };
      }

      case 'wait': {
        if (step.timeMs) {
          await page.waitForTimeout(step.timeMs);
        } else if (step.target) {
          const locator = this.locators.resolve(page, step.target);
          await locator.waitFor({ state: step.state || 'visible', timeout: step.timeoutMs || 30000 });
        } else {
          await page.waitForLoadState('networkidle');
        }
        return { action: 'wait' };
      }

      case 'select2': {
        // A select2-enhanced <select>: open its container, type the search, wait for the AJAX results and pick
        // the one option whose text contains `option` (strict: zero or several matches fail).
        const select = this.locators.resolve(page, step.target);
        await select.waitFor({ state: 'attached', timeout: step.timeoutMs || 15000 });
        const container = select.locator('xpath=following-sibling::span[contains(concat(" ", @class, " "), " select2-container ")][1]');
        if (!step.alreadyOpen) await container.locator('.select2-selection').click();
        const search = String(varContext.interpolate(step.search ?? ''));
        const field = page.locator('.select2-container--open input.select2-search__field');
        if (search) await field.fill(search);
        const wanted = String(varContext.interpolate(step.option ?? step.search ?? ''));
        const results = page.locator('.select2-container--open .select2-results__option:not(.loading-results):not(.select2-results__message)');
        if (step.selection === 'first') {
          const choice = results.first();
          await choice.waitFor({ state: 'visible', timeout: step.timeoutMs || 15000 });
          const label = (await choice.innerText()).trim();
          await choice.click();
          const value = await select.inputValue();
          if (!value) throw new Error('Assertion failed: the visible select2 choice did not set a value');
          if (step.as) varContext.setCaptured(step.as, { label, value });
          return { action: 'select2', target: step.target, selection: 'first', label, value };
        }
        const containing = results.filter({ hasText: wanted });
        await containing.first().waitFor({ state: 'visible', timeout: step.timeoutMs || 15000 });
        // Prefer the option that is exactly the wanted text (optionally followed by " (…)"); else a unique substring match.
        const texts = (await containing.allInnerTexts()).map(t => t.trim());
        const exact = texts.map((t, i) => (t === wanted || t.startsWith(`${wanted} (`)) ? i : -1).filter(i => i >= 0);
        const index = exact.length === 1 ? exact[0] : (texts.length === 1 ? 0 : -1);
        if (index < 0) throw new Error(`Assertion failed: select2 option "${wanted}" matched ${texts.length} results (${exact.length} exact; expected exactly 1)`);
        await containing.nth(index).click();
        const value = await select.inputValue();
        return { action: 'select2', target: step.target, search, option: wanted, value };
      }

      case 'download': {
        // Click a link or button that downloads a file; the text, line count and parsed CSV rows are captured.
        const locator = this.locators.resolve(page, step.target);
        const [download] = await Promise.all([
          page.waitForEvent('download', { timeout: step.timeoutMs || 30000 }),
          locator.click()
        ]);
        const stream = await download.createReadStream();
        const chunks = [];
        let size = 0;
        for await (const chunk of stream) {
          size += chunk.length;
          if (size > 5_000_000) throw new Error('Assertion failed: downloaded file exceeds 5 MB');
          chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
        }
        const text = Buffer.concat(chunks).toString('utf8').replace(/^﻿/, '');
        const captured = { filename: download.suggestedFilename(), text, lines: text.split(/\r?\n/).filter(l => l.trim()).length };
        if (/\.csv$/i.test(captured.filename) || step.csv) {
          const parsed = Papa.parse(text, { header: true, skipEmptyLines: 'greedy' });
          captured.header = parsed.meta.fields || [];
          captured.rows = parsed.data;
          captured.rowCount = parsed.data.length;
        }
        if (step.as) varContext.setCaptured(step.as, captured);
        return { action: 'download', target: step.target, filename: captured.filename, lines: captured.lines };
      }

      case 'upload': {
        const locator = this.locators.resolve(page, step.target);
        if (step.inlineCsv) {
          const filename = String(varContext.interpolate(step.filename));
          if (!/^[a-z0-9._-]+\.csv$/i.test(filename)) throw new Error('Unsupported: inline CSV needs a plain CSV filename');
          const rows = varContext.interpolateDeep(step.inlineCsv);
          await locator.setInputFiles({ name: filename, mimeType: 'text/csv', buffer: Buffer.from('\uFEFF' + Papa.unparse(rows), 'utf8') });
          return { action: 'upload', target: step.target, file: filename, rows: rows.length };
        }
        const filePath = varContext.interpolate(step.file);
        await locator.setInputFiles(filePath);
        return { action: 'upload', target: step.target, file: filePath };
      }

      case 'assert': {
        return await this.assertions.assert(page, step, this.locators, varContext);
      }

      case 'compare': {
        return this.assertions.compare(step, varContext);
      }

      case 'capture': {
        return await this.captureEngine.capture(page, step, this.locators, varContext);
      }

      default:
        throw new Error(`Unsupported browser action: '${action}'`);
    }
  }

  resolveRoute(routeName, rawParams, varContext, baseUrl) {
    const params = varContext.interpolateDeep(rawParams);

    // Map common routes to known URL paths
    const routeMap = {
      'login': '/login',
      'logout': '/logout',
      'dashboard': '/',
      'inventory.consumable.show': `/consumables/${params.id}`,
      'inventory.consumables.index': '/consumables',
      'requests.create': '/requests/create',
      'gov.requests.catalog': '/gov-requests/catalog',
      'gov.requests.basket': '/gov-requests/basket',
      'gov.requests.user.index': '/gov-requests/my-requests',
      'gov.requests.admin.index': '/gov-requests/admin',
      'gov.requests.fulfillment.index': '/gov-requests/fulfillment',
      'requests.show': `/requests/${params.id}`,
      'requests.index': '/requests',
      'gov.context.switch': '/gov-store/switch-context',
      'committee.dashboard': '/gov-store/committees',
      'committee.registry': '/gov-store/committees/registry',
      'committee.mine': '/gov-store/committees/mine',
      'committee.types': '/gov-store/committees/admin/types',
      'committee.show': `/gov-store/committees/${params.id}`,
      // Store operations (GRN) and asset tagging
      'storeops.hub': '/gov-store/operations/hub',
      'storeops.workspace': `/gov-store/operations/documents/${params.type || 'receipt'}/${params.id}`,
      'storeops.register': '/gov-store/operations/register',
      'asset-tags.settings': '/gov-store/asset-tags/settings',
      'asset-tags.codes': '/gov-store/asset-tags/codes',
      'asset-tags.lookup': '/gov-store/asset-tags/lookup',
      'asset-tags.enablement': '/gov-store/asset-tags/enablement',
      'catalog.finance': '/admin/catalog/finance',
      'catalog.governance.show': `/admin/catalog/governance/${params.id}`,
      'native.settings.asset-tags': '/admin/asset_tags',
      // Rules studio
      'rules.studio': '/gov-store/operations/settings/product-rules',
      'rules.simulator': '/gov-store/operations/settings/product-rules/simulator',
      'rules.create': `/gov-store/operations/settings/product-rules/policies/create/${params.template || 'blank'}`,
      'rules.edit': `/gov-store/operations/settings/product-rules/policies/${params.id}/edit`
    };

    const path = routeMap[routeName];
    if (!path) {
      throw new Error(`Route '${routeName}' is not registered in route map`);
    }

    return new URL(path, baseUrl).toString();
  }
}
