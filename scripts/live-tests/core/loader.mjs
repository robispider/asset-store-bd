import fs from 'node:fs';
import path from 'node:path';

/**
 * GovStore Live Testing - Safe JSON Loader
 * Enforces boundary containment and loads suites, journeys, flows, and UI contracts.
 */
export class TestLoader {
  constructor(baseDir = process.cwd()) {
    this.baseDir = path.resolve(baseDir);
    this.liveRoot = path.join(this.baseDir, 'tests', 'live');
    this.schemasDir = path.join(this.liveRoot, 'schemas');
    this.suitesDir = path.join(this.liveRoot, 'suites');
    this.subtestsDir = path.join(this.liveRoot, 'subtests');
    this.flowsDir = path.join(this.liveRoot, 'flows');
    this.uiContractsDir = path.join(this.liveRoot, 'ui-contracts');
    this.observationsDir = path.join(this.liveRoot, 'observations');
  }

  assertSafePath(targetPath, rootDir = this.liveRoot) {
    const resolved = path.resolve(targetPath);
    if (!resolved.startsWith(rootDir)) {
      throw new Error(`Path traversal violation: '${targetPath}' escapes root '${rootDir}'`);
    }
    return resolved;
  }

  loadJson(filePath) {
    const safePath = this.assertSafePath(filePath);
    if (!fs.existsSync(safePath)) {
      throw new Error(`File not found: '${safePath}'`);
    }
    const raw = fs.readFileSync(safePath, 'utf8');
    try {
      return JSON.parse(raw);
    } catch (err) {
      throw new Error(`JSON parse error in '${filePath}': ${err.message}`);
    }
  }

  loadSchema(name) {
    const schemaFile = path.join(this.schemasDir, `${name}.schema.json`);
    return this.loadJson(schemaFile);
  }

  loadFlow(flowId) {
    const flowFile = path.join(this.flowsDir, `${flowId}.json`);
    return this.loadJson(flowFile);
  }

  loadUiContract(contractId) {
    const contractFile = path.join(this.uiContractsDir, `${contractId}.json`);
    return this.loadJson(contractFile);
  }

  loadObservationContracts() {
    const contractsFile = path.join(this.observationsDir, 'contracts.json');
    return this.loadJson(contractsFile);
  }

  loadJourneyByIdOrPath(idOrPath) {
    if (fs.existsSync(idOrPath)) {
      return this.loadJson(idOrPath);
    }

    // Try finding by relative path under subtests
    const candidatePath = path.join(this.subtestsDir, idOrPath.endsWith('.json') ? idOrPath : `${idOrPath}.json`);
    if (fs.existsSync(candidatePath)) {
      return this.loadJson(candidatePath);
    }

    // Search recursively in subtests for matching "id"
    const all = this.listJourneys();
    const found = all.find(j => j.id === idOrPath);
    if (found) {
      return this.loadJson(found.filePath);
    }

    throw new Error(`Journey not found: '${idOrPath}'`);
  }

  loadSuite(suiteIdOrPath) {
    if (fs.existsSync(suiteIdOrPath)) {
      return this.loadJson(suiteIdOrPath);
    }
    const suitePath = path.join(this.suitesDir, suiteIdOrPath.endsWith('.json') ? suiteIdOrPath : `${suiteIdOrPath}.json`);
    if (fs.existsSync(suitePath)) {
      return this.loadJson(suitePath);
    }
    throw new Error(`Suite not found: '${suiteIdOrPath}'`);
  }

  listJourneys() {
    const journeys = [];
    const scanDir = (dir) => {
      if (!fs.existsSync(dir)) return;
      const entries = fs.readdirSync(dir, { withFileTypes: true });
      for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
          scanDir(fullPath);
        } else if (entry.isFile() && entry.name.endsWith('.json')) {
          try {
            const data = JSON.parse(fs.readFileSync(fullPath, 'utf8'));
            if (data.id && data.steps) {
              journeys.push({
                id: data.id,
                title: data.title || data.id,
                features: data.features || [],
                tags: data.tags || [],
                capability: data.execution?.capability || 'observe',
                filePath: fullPath
              });
            }
          } catch {
            // Ignore invalid JSON in directory scan
          }
        }
      }
    };
    scanDir(this.subtestsDir);
    return journeys;
  }

  listSuites() {
    const suites = [];
    if (!fs.existsSync(this.suitesDir)) return suites;
    const entries = fs.readdirSync(this.suitesDir);
    for (const entry of entries) {
      if (entry.endsWith('.json')) {
        const fullPath = path.join(this.suitesDir, entry);
        try {
          const data = JSON.parse(fs.readFileSync(fullPath, 'utf8'));
          suites.push({
            id: data.id || path.basename(entry, '.json'),
            title: data.title || entry,
            journeys: data.journeys || [],
            filePath: fullPath
          });
        } catch {
          // ignore
        }
      }
    }
    return suites;
  }
}
