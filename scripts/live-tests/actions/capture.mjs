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
    const extract = value => {
      if (!step.pattern || value == null) return value;
      const match = String(value).match(new RegExp(step.pattern));
      if (!match) throw new Error(`Assertion failed: captured value "${value}" does not match pattern ${step.pattern}`);
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
      const interpolated = varContext.interpolate(step.value);
      varContext.setCaptured(asKey, interpolated);
      return { as: asKey, value: interpolated };
    }

    throw new Error(`Capture action must specify 'target', 'url', or 'value'`);
  }
}
