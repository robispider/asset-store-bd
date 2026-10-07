/**
 * GovStore Live Testing - Schema & Semantic Validator
 * Verifies journeys, flows, suites, and contracts before any browser or execution is triggered.
 */

const ALLOWED_ACTIONS = new Set([
  'goto',
  'click',
  'fill',
  'clear',
  'select',
  'check',
  'uncheck',
  'keyboard',
  'upload',
  'download',
  'reload',
  'observe',
  'assert',
  'compare',
  'capture',
  'wait',
  'actor',
  'ledger',
  'waitObserve',
  'probe',
  'submitForm',
  'use' // flow invocation
]);

const ALLOWED_NAMESPACES = new Set([
  'run',
  'actors',
  'fixtures',
  'data',
  'captured',
  'observed',
  'baseline'
]);

export class TestValidator {
  constructor(loader) {
    this.loader = loader;
  }

  validateJourney(journey, callingChain = []) {
    const errors = [];

    if (!journey || typeof journey !== 'object') {
      return ['Journey must be an object'];
    }

    if (!journey.schemaVersion || typeof journey.schemaVersion !== 'number') {
      errors.push('Missing or invalid schemaVersion (must be integer)');
    }

    if (!journey.id || typeof journey.id !== 'string') {
      errors.push('Missing or invalid journey id');
    }

    if (!journey.steps || !Array.isArray(journey.steps)) {
      errors.push('Missing steps array in journey');
      return errors;
    }

    const cap = journey.execution?.capability;
    if (cap && !['observe', 'write-fixtures'].includes(cap)) {
      errors.push(`Unknown execution.capability '${cap}'`);
    }
    if (cap === 'write-fixtures' && !journey.cleanup) {
      errors.push("write-fixtures journeys must declare a 'cleanup' policy (steps and/or retained records)");
    }
    if (cap === 'observe') {
      const mutating = journey.steps.filter(s => s.action === 'ledger');
      if (mutating.length) errors.push("observe journeys must not record ownership-ledger entries");
    }

    // Step-level semantic validation
    for (let i = 0; i < journey.steps.length; i++) {
      const step = journey.steps[i];
      const stepPath = `${journey.id}.steps[${i}]`;

      if (!step || typeof step !== 'object') {
        errors.push(`${stepPath}: Step must be an object`);
        continue;
      }

      if (step.use) {
        // Flow call validation
        if (callingChain.includes(step.use)) {
          errors.push(`${stepPath}: Cyclic flow reference detected: ${[...callingChain, step.use].join(' -> ')}`);
          continue;
        }

        try {
          const flow = this.loader.loadFlow(step.use);
          const flowErrors = this.validateFlow(flow, [...callingChain, step.use], step.with || {});
          errors.push(...flowErrors.map(e => `${stepPath} -> ${e}`));
        } catch (err) {
          errors.push(`${stepPath}: Flow '${step.use}' could not be loaded: ${err.message}`);
        }
        continue;
      }

      if (!step.action) {
        errors.push(`${stepPath}: Step must specify an 'action' or 'use'`);
        continue;
      }

      if (!ALLOWED_ACTIONS.has(step.action)) {
        errors.push(`${stepPath}: Unknown action '${step.action}'`);
        continue;
      }

      // Action-specific validations
      if (step.action === 'assert') {
        if (!step.test) {
          errors.push(`${stepPath}: Assertion step missing 'test' predicate`);
        }
        if (!step.target && step.actual === undefined && step.test !== 'url') {
          errors.push(`${stepPath}: Assertion step must specify either 'target' (locator alias) or 'actual' value`);
        }
      }

      if (step.action === 'observe') {
        if (!step.check) {
          errors.push(`${stepPath}: Observation missing 'check' name`);
        } else {
          try {
            const obsContracts = this.loader.loadObservationContracts();
            if (!obsContracts.contracts || !obsContracts.contracts[step.check]) {
              errors.push(`${stepPath}: Observation check '${step.check}' is not registered in contracts.json`);
            }
          } catch (e) {
            errors.push(`${stepPath}: Unable to verify observation contracts: ${e.message}`);
          }
        }
        if (!step.as) {
          errors.push(`${stepPath}: Observation missing 'as' alias for storing results`);
        }
      }

      if (step.modes && (!Array.isArray(step.modes) || step.modes.some(m => !['shadow', 'enforce'].includes(m)))) {
        errors.push(`${stepPath}: 'modes' must be an array of shadow/enforce`);
      }
      if (step.action === 'actor' && !step.name) {
        errors.push(`${stepPath}: actor step requires 'name'`);
      }
      if (step.action === 'ledger' && (!step.kind || !step.id)) {
        errors.push(`${stepPath}: ledger step requires 'kind' and 'id'`);
      }
      if (step.action === 'probe' && (!step.path || (step.expectStatus === undefined && !step.expectStatusByMode))) {
        errors.push(`${stepPath}: probe step requires 'path' and 'expectStatus'`);
      }
      if (step.action === 'waitObserve' && (!step.check || !step.path || step.equals === undefined)) {
        errors.push(`${stepPath}: waitObserve requires 'check', 'path' and 'equals'`);
      }

      if (step.action === 'compare') {
        if (step.actual === undefined || (step.equals === undefined && step.contains === undefined)) {
          errors.push(`${stepPath}: Compare step requires 'actual' and 'equals'/'contains'`);
        }
      }

      // Check variable syntax across step strings
      this.checkVariableReferences(step, stepPath, errors);
    }

    if (journey.cleanup?.steps?.length) {
      const sub = this.validateJourney({ schemaVersion: 1, id: `${journey.id}.cleanup`, execution: { capability: 'write-fixtures' }, cleanup: {}, steps: journey.cleanup.steps }, callingChain);
      errors.push(...sub);
    }

    return errors;
  }

  validateFlow(flow, callingChain = [], params = {}) {
    const errors = [];
    if (!flow.schemaVersion) errors.push(`Flow missing schemaVersion`);
    if (!flow.id) errors.push(`Flow missing id`);
    if (!flow.steps || !Array.isArray(flow.steps)) {
      errors.push(`Flow '${flow.id}' missing steps array`);
      return errors;
    }

    // Verify required params
    if (flow.params) {
      for (const [paramName, paramDef] of Object.entries(flow.params)) {
        if (paramDef.required && params[paramName] === undefined && paramDef.default === undefined) {
          errors.push(`Flow '${flow.id}' missing required parameter: '${paramName}'`);
        }
      }
    }

    for (let i = 0; i < flow.steps.length; i++) {
      const step = flow.steps[i];
      const stepPath = `flow:${flow.id}.steps[${i}]`;

      if (step.use) {
        if (callingChain.includes(step.use)) {
          errors.push(`${stepPath}: Cyclic flow reference detected: ${[...callingChain, step.use].join(' -> ')}`);
          continue;
        }
        try {
          const subFlow = this.loader.loadFlow(step.use);
          errors.push(...this.validateFlow(subFlow, [...callingChain, step.use], step.with || {}));
        } catch (err) {
          errors.push(`${stepPath}: Sub-flow '${step.use}' load error: ${err.message}`);
        }
      } else if (!step.action || !ALLOWED_ACTIONS.has(step.action)) {
        errors.push(`${stepPath}: Unknown or missing action '${step.action}'`);
      }
    }

    return errors;
  }

  validateSuite(suite) {
    const errors = [];
    if (!suite.id) errors.push('Suite missing id');
    if (!suite.journeys || !Array.isArray(suite.journeys) || suite.journeys.length === 0) {
      errors.push('Suite must contain a non-empty journeys array');
    } else {
      for (const journeyRef of suite.journeys) {
        try {
          const journey = this.loader.loadJourneyByIdOrPath(journeyRef);
          const jErrors = this.validateJourney(journey);
          if (jErrors.length > 0) {
            errors.push(`Suite '${suite.id}' journey '${journeyRef}' invalid: ${jErrors.join('; ')}`);
          }
        } catch (err) {
          errors.push(`Suite '${suite.id}' references missing journey '${journeyRef}': ${err.message}`);
        }
      }
    }
    return errors;
  }

  checkVariableReferences(obj, currentPath, errors) {
    if (!obj || typeof obj !== 'object') return;

    for (const [key, val] of Object.entries(obj)) {
      const fieldPath = `${currentPath}.${key}`;
      if (typeof val === 'string') {
        const matches = val.matchAll(/\$\{([a-zA-Z0-9_.-]+)\}/g);
        for (const match of matches) {
          const varPath = match[1];
          const ns = varPath.split('.')[0];
          if (!ALLOWED_NAMESPACES.has(ns)) {
            errors.push(`${fieldPath}: Invalid variable namespace '${ns}' in '${match[0]}'`);
          }
        }
      } else if (typeof val === 'object') {
        this.checkVariableReferences(val, fieldPath, errors);
      }
    }
  }
}
