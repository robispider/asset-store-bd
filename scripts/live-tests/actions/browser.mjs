/**
 * GovStore Live Testing - Browser Action Engine
 */

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
    }

    switch (action) {
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
        await page.goto(targetUrl, { waitUntil: 'load' });
        return { action: 'goto', url: targetUrl };
      }

      case 'click': {
        const locator = this.locators.resolve(page, step.target);
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
        const locator = this.locators.resolve(page, step.target);
        await Promise.all([page.waitForLoadState('load'), locator.click()]);
        return { action: 'submitForm', target: step.target, url: page.url() };
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
          await locator.waitFor({ state: step.state || 'visible' });
        } else {
          await page.waitForLoadState('networkidle');
        }
        return { action: 'wait' };
      }

      case 'upload': {
        const locator = this.locators.resolve(page, step.target);
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
      'gov.context.switch': '/gov-store/switch-context'
    };

    const path = routeMap[routeName];
    if (!path) {
      throw new Error(`Route '${routeName}' is not registered in route map`);
    }

    return new URL(path, baseUrl).toString();
  }
}
