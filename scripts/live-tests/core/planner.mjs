/**
 * GovStore Live Testing - Execution Planner
 * Expands flows, matrix variations, and resolves UI contracts into a concrete execution plan.
 */

export class ExecutionPlanner {
  constructor(loader) {
    this.loader = loader;
  }

  planJourney(journey, options = {}) {
    const cases = [];

    if (journey.matrix && Array.isArray(journey.matrix) && journey.matrix.length > 0) {
      for (let i = 0; i < journey.matrix.length; i++) {
        const matrixCase = journey.matrix[i];
        const caseId = `${journey.id}[case_${i}]`;
        const expandedSteps = this.expandJourneySteps(journey, { ...(journey.data || {}), ...matrixCase });
        cases.push({
          caseId,
          journeyId: journey.id,
          title: `${journey.title} (Matrix case ${i + 1})`,
          capability: journey.execution?.capability || 'observe',
          execution: journey.execution || {},
          cleanup: this.expandCleanup(journey, matrixCase),
          fixtures: journey.fixtures || {},
          data: { ...(journey.data || {}), ...matrixCase },
          steps: expandedSteps
        });
      }
    } else {
      const expandedSteps = this.expandJourneySteps(journey, journey.data || {});
      cases.push({
        caseId: journey.id,
        journeyId: journey.id,
        title: journey.title,
        capability: journey.execution?.capability || 'observe',
        execution: journey.execution || {},
        cleanup: this.expandCleanup(journey, journey.data || {}),
        fixtures: journey.fixtures || {},
        data: journey.data || {},
        steps: expandedSteps
      });
    }

    return {
      journeyId: journey.id,
      title: journey.title,
      features: journey.features || [],
      tags: journey.tags || [],
      totalCases: cases.length,
      cases
    };
  }

  expandCleanup(journey, params) {
    const cleanup = journey.cleanup || {};
    return { ...cleanup, steps: this.expandSteps(cleanup.steps || [], params) };
  }

  expandJourneySteps(journey, params) {
    return ['preconditions', 'steps', 'postconditions'].flatMap(section =>
      this.expandSteps(journey[section] || [], params).map(step => ({ ...step, _phase: section })));
  }

  expandSteps(steps, contextParams = {}, depth = 0) {
    if (depth > 15) {
      throw new Error('Maximum flow expansion depth exceeded (possible cycle)');
    }

    const expanded = [];
    for (const step of steps) {
      if (step.use) {
        // Load the flow
        const flow = this.loader.loadFlow(step.use);
        const flowWith = { ...(step.with || {}) };

        // Substitute params in flowWith from contextParams
        for (const [k, v] of Object.entries(flowWith)) {
          if (typeof v === 'string' && v.startsWith('${params.') && v.endsWith('}')) {
            const pKey = v.slice(9, -1);
            flowWith[k] = contextParams[pKey] !== undefined ? contextParams[pKey] : v;
          }
        }

        // Recursively expand flow steps with combined params
        const defaults = Object.fromEntries(Object.entries(flow.params || {})
          .filter(([, def]) => def.default !== undefined).map(([name, def]) => [name, def.default]));
        const flowParams = { ...defaults, ...flowWith };
        const innerSteps = this.expandSteps(flow.steps, flowParams, depth + 1);
        for (const inner of innerSteps) {
          expanded.push({
            ...inner,
            ...(step.when && !inner.when ? { when: step.when } : {}),
            ...(step.modes && !inner.modes ? { modes: step.modes } : {}),
            _flow: inner._flow || step.use,
            _sensitive: Boolean(inner._sensitive || step.use === 'auth.login')
          });
        }
      } else {
        // Clone and substitute immediate ${params.xyz}
        const cloned = JSON.parse(JSON.stringify(step));
        this.substituteParams(cloned, contextParams);
        expanded.push(cloned);
      }
    }

    return expanded;
  }

  substituteParams(obj, params) {
    if (!obj || typeof obj !== 'object' || !params) return;

    for (const [key, val] of Object.entries(obj)) {
      if (typeof val === 'string') {
        obj[key] = val.replace(/\$\{params\.([a-zA-Z0-9_.-]+)\}/g, (match, paramKey) => {
          return params[paramKey] !== undefined ? String(params[paramKey]) : match;
        });
      } else if (typeof val === 'object') {
        this.substituteParams(val, params);
      }
    }
  }

  planSuite(suite) {
    const plannedJourneys = [];
    for (const journeyRef of suite.journeys) {
      const journey = this.loader.loadJourneyByIdOrPath(journeyRef);
      plannedJourneys.push(this.planJourney(journey));
    }
    return {
      suiteId: suite.id,
      title: suite.title,
      totalJourneys: plannedJourneys.length,
      journeys: plannedJourneys
    };
  }
}
