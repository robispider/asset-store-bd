/**
 * GovStore Live Testing - Locator Resolver
 * Translates semantic aliases to Playwright locators using UI contracts.
 */

export class LocatorResolver {
  constructor(loader, activeLanguage = 'en-US') {
    this.loader = loader;
    this.activeLanguage = activeLanguage;
    this.contractCache = new Map();
  }

  setLanguage(lang) {
    this.activeLanguage = lang;
  }

  getContract(contractId) {
    if (!this.contractCache.has(contractId)) {
      try {
        const contract = this.loader.loadUiContract(contractId);
        this.contractCache.set(contractId, contract);
      } catch {
        this.contractCache.set(contractId, null);
      }
    }
    return this.contractCache.get(contractId);
  }

  resolve(page, targetAlias) {
    // If targetAlias starts with css= or # or . or //, treat as direct selector
    if (targetAlias.startsWith('css=') || targetAlias.startsWith('#') || targetAlias.startsWith('.') || targetAlias.startsWith('//')) {
      const selector = targetAlias.startsWith('css=') ? targetAlias.slice(4) : targetAlias;
      return page.locator(selector);
    }

    // Split alias: e.g. "consumable.name" -> contract="consumable", key="name"
    // or "auth.username" -> contract="auth", key="username"
    const dotIdx = targetAlias.indexOf('.');
    if (dotIdx !== -1) {
      const contractId = targetAlias.slice(0, dotIdx);
      const locatorKey = targetAlias.slice(dotIdx + 1);

      const contract = this.getContract(contractId);
      if (contract && contract.locators && contract.locators[locatorKey]) {
        const def = contract.locators[locatorKey];

        if (def.testId) {
          return page.getByTestId(def.testId);
        }
        if (def.role) {
          let name = def.name;
          // check if name has bilingual translation in contract.labels
          if (contract.labels && contract.labels[this.activeLanguage] && contract.labels[this.activeLanguage][locatorKey]) {
            name = contract.labels[this.activeLanguage][locatorKey];
          }
          return page.getByRole(def.role, name ? { name } : undefined);
        }
        if (def.label) {
          let labelText = def.label;
          if (contract.labels && contract.labels[this.activeLanguage] && contract.labels[this.activeLanguage][locatorKey]) {
            labelText = contract.labels[this.activeLanguage][locatorKey];
          }
          return page.getByLabel(labelText);
        }
        if (def.css) {
          const css = this.vars && def.css.includes('${') ? String(this.vars.interpolate(def.css)) : def.css;
          return page.locator(css);
        }
      }
    }

    // Default fallback: search by name attribute, id attribute, or data-testid
    return page.locator(`[data-testid="${targetAlias}"], [name="${targetAlias}"], #${targetAlias}`);
  }
}
