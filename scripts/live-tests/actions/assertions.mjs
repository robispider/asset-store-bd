/**
 * GovStore Live Testing - Assertion Handlers
 * Evaluates explicit assertions with detailed expected vs actual messages.
 */

export class AssertionEngine {
  /**
   * Run an assertion against a Playwright Page and locator or direct values.
   */
  async assert(page, step, locatorResolver, varContext) {
    const testType = step.test;
    const targetAlias = step.target;

    let locator = null;
    if (targetAlias) {
      locator = locatorResolver.resolve(page, targetAlias);
    }

    switch (testType) {
      case 'valid':
      case 'invalid': {
        // HTML constraint validity of a form control (client-side validation evidence).
        const isValid = await locator.evaluate(el => el.checkValidity());
        const wantValid = testType === 'valid';
        if (isValid !== wantValid) {
          const msg = await locator.evaluate(el => el.validationMessage);
          throw new Error(`Assertion failed: Target '${targetAlias}' expected to be ${testType} but validity=${isValid} (${msg})`);
        }
        return { passed: true, test: testType, target: targetAlias };
      }

      case 'count': {
        const actualCount = await locator.count();
        const expectedCount = Number(varContext.interpolate(step.equals));
        if (actualCount !== expectedCount) {
          throw new Error(`Assertion failed: Target '${targetAlias}' count mismatch. Expected: ${expectedCount}, Actual: ${actualCount}`);
        }
        return { passed: true, test: 'count', target: targetAlias, expected: expectedCount, actual: actualCount };
      }

      case 'visible': {
        const isVis = await locator.isVisible();
        if (!isVis) {
          throw new Error(`Assertion failed: Target '${targetAlias}' expected to be visible, but was not.`);
        }
        return { passed: true, test: 'visible', target: targetAlias };
      }

      case 'hidden': {
        const isHidden = await locator.isHidden();
        if (!isHidden) {
          throw new Error(`Assertion failed: Target '${targetAlias}' expected to be hidden, but was visible.`);
        }
        return { passed: true, test: 'hidden', target: targetAlias };
      }

      case 'text': {
        const actualText = (await locator.innerText()).trim();
        const expectedText = String(varContext.interpolate(step.equals ?? step.contains ?? '')).trim();

        if (step.equals !== undefined) {
          if (actualText !== expectedText) {
            throw new Error(`Assertion failed: Target '${targetAlias}' text mismatch. Expected: "${expectedText}", Actual: "${actualText}"`);
          }
        } else if (step.contains !== undefined) {
          if (!actualText.includes(expectedText)) {
            throw new Error(`Assertion failed: Target '${targetAlias}' does not contain expected substring. Expected substring: "${expectedText}", Actual: "${actualText}"`);
          }
        }
        return { passed: true, test: 'text', target: targetAlias, expected: expectedText, actual: actualText };
      }

      case 'number': {
        const rawText = (await locator.innerText()).trim();
        // Remove currency symbols, commas, spaces
        const cleaned = rawText.replace(/[^0-9.-]/g, '');
        const actualNum = parseFloat(cleaned);
        const expectedNum = Number(varContext.interpolate(step.equals));

        if (isNaN(actualNum)) {
          throw new Error(`Assertion failed: Target '${targetAlias}' text "${rawText}" could not be parsed as a number.`);
        }

        if (step.equals !== undefined) {
          if (actualNum !== expectedNum) {
            throw new Error(`Assertion failed: Target '${targetAlias}' number mismatch. Expected: ${expectedNum}, Actual: ${actualNum}`);
          }
        }
        return { passed: true, test: 'number', target: targetAlias, expected: expectedNum, actual: actualNum };
      }

      case 'value': {
        const actualVal = await locator.inputValue();
        const expectedVal = String(varContext.interpolate(step.equals ?? step.contains ?? ''));

        if (step.equals !== undefined) {
          if (actualVal !== expectedVal) {
            throw new Error(`Assertion failed: Target '${targetAlias}' input value mismatch. Expected: "${expectedVal}", Actual: "${actualVal}"`);
          }
        }
        return { passed: true, test: 'value', target: targetAlias, expected: expectedVal, actual: actualVal };
      }

      case 'checked': {
        const isChecked = await locator.isChecked();
        const expectedChecked = step.equals !== false;
        if (isChecked !== expectedChecked) {
          throw new Error(`Assertion failed: Target '${targetAlias}' checked state mismatch. Expected: ${expectedChecked}, Actual: ${isChecked}`);
        }
        return { passed: true, test: 'checked', target: targetAlias, expected: expectedChecked, actual: isChecked };
      }

      case 'enabled': {
        const isEnabled = await locator.isEnabled();
        if (!isEnabled) {
          throw new Error(`Assertion failed: Target '${targetAlias}' expected to be enabled, but was disabled.`);
        }
        return { passed: true, test: 'enabled', target: targetAlias };
      }

      case 'disabled': {
        const isDisabled = await locator.isDisabled();
        if (!isDisabled) {
          throw new Error(`Assertion failed: Target '${targetAlias}' expected to be disabled, but was enabled.`);
        }
        return { passed: true, test: 'disabled', target: targetAlias };
      }

      case 'url': {
        const currentUrl = page.url();
        const expectedUrlPart = String(varContext.interpolate(step.equals ?? step.contains ?? ''));
        if (step.equals !== undefined) {
          if (currentUrl !== expectedUrlPart) {
            throw new Error(`Assertion failed: Page URL mismatch. Expected: "${expectedUrlPart}", Actual: "${currentUrl}"`);
          }
        } else if (step.contains !== undefined) {
          if (!currentUrl.includes(expectedUrlPart)) {
            throw new Error(`Assertion failed: Page URL does not contain expected part. Expected: "${expectedUrlPart}", Actual: "${currentUrl}"`);
          }
        }
        return { passed: true, test: 'url', expected: expectedUrlPart, actual: currentUrl };
      }

      default:
        throw new Error(`Unknown assertion test: '${testType}'`);
    }
  }

  /**
   * Run a direct value comparison.
   */
  compare(step, varContext) {
    const actual = varContext.interpolate(step.actual);
    const expected = step.equals !== undefined ? varContext.interpolate(step.equals) : undefined;
    const contains = step.contains !== undefined ? varContext.interpolate(step.contains) : undefined;

    if (expected !== undefined) {
      if (typeof actual === 'object' && typeof expected === 'object') {
        const actualJson = JSON.stringify(actual);
        const expectedJson = JSON.stringify(expected);
        if (actualJson !== expectedJson) {
          throw new Error(`Comparison failed. Expected: ${expectedJson}, Actual: ${actualJson}`);
        }
      } else if (String(actual) !== String(expected)) {
        throw new Error(`Comparison failed. Expected: "${expected}", Actual: "${actual}"`);
      }
    } else if (contains !== undefined) {
      if (!String(actual).includes(String(contains))) {
        throw new Error(`Comparison failed. Actual "${actual}" does not contain "${contains}"`);
      }
    }

    return { passed: true, actual, expected: expected ?? contains };
  }
}
