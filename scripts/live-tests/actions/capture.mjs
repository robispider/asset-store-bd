/**
 * GovStore Live Testing - Value Capture Engine
 */

export class CaptureEngine {
  async capture(page, step, locatorResolver, varContext) {
    const asKey = step.as;
    if (!asKey) {
      throw new Error(`Capture action missing required 'as' parameter`);
    }

    if (step.url) {
      const url = page.url();
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
