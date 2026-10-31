import { registerSecret } from '../reporters/redaction.mjs';

/**
 * GovStore Live Testing - Value Capture Engine
 */

export class CaptureEngine {
  async capture(page, step, locatorResolver, varContext) {
    const asKey = step.as;
    if (!asKey) {
      throw new Error(`Capture action missing required 'as' parameter`);
    }

    // Optional `pattern`: keep only regex group 1 (or the whole match) of the captured text.
    const protect = value => {
      const secret = String(value);
      registerSecret(secret);
      registerSecret(secret.replace(/\s+/g, ''));
      registerSecret(encodeURIComponent(secret));
      registerSecret(new URLSearchParams({ code: secret }).toString().slice(5));
    };
    const extract = value => {
      if (step.sensitive && value != null) {
        protect(value);
      }
      if (!step.pattern || value == null) return value;
      const match = String(value).match(new RegExp(step.pattern));
      if (!match) throw new Error(step.sensitive ? 'Assertion failed: sensitive capture did not match its declared pattern'
        : `Assertion failed: captured value "${value}" does not match pattern ${step.pattern}`);
      if (step.sensitive) protect(match[1] ?? match[0]);
      return match[1] ?? match[0];
    };

    if (step.url) {
      const url = extract(page.url());
      varContext.setCaptured(asKey, url);
      return { as: asKey, value: url };
    }

    if (step.target) {
      const locator = locatorResolver.resolve(page, step.target);
      let capturedValue = null;

      if (step.count) {
        capturedValue = await locator.count();
        varContext.setCaptured(asKey, capturedValue);
        return { as: asKey, value: capturedValue };
      }

      if (step.attribute) {
        capturedValue = await locator.getAttribute(step.attribute);
      } else if (step.value) {
        capturedValue = await locator.inputValue();
      } else {
        capturedValue = (await locator.innerText()).trim();
      }
      capturedValue = extract(capturedValue);

      varContext.setCaptured(asKey, capturedValue);
      return { as: asKey, value: capturedValue };
    }

    if (step.value !== undefined) {
      const interpolated = extract(varContext.interpolate(step.value));
      varContext.setCaptured(asKey, interpolated);
      return { as: asKey, value: interpolated };
    }

    throw new Error(`Capture action must specify 'target', 'url', or 'value'`);
  }
}
