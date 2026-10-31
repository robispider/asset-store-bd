/** Reconcile effective GovStore handlers with the required-function register without inventing live passes. */
export function reconcileInventory(routes, existing = {}) {
  const namespaces = { Classification: 'classification', Organization: 'organization', CustomRequests: 'custom-requests',
    TenantScope: 'tenant-scope', Experimentation: 'experimentation', OfficeMembership: 'office-membership',
    Metadata: 'metadata', UserOnboarding: 'user-onboarding', Tracking: 'tracking', GeoView: 'geo-view',
    GeoAreas: 'geo-areas', AssetTagging: 'asset-tagging', Committee: 'committee', StoreOperations: 'store-operations', Theming: 'theming' };
  const storyGroups = { classification: [13, 23, 24, 28], organization: [1, 2, 3, 27, 28, 31],
    'custom-requests': [7, 8, 9, 17], 'tenant-scope': [5, 16, 17, 25, 27], 'office-membership': [3, 5, 12, 15, 16, 17],
    metadata: [6, 14, 26], 'user-onboarding': [4], tracking: [21, 22], 'geo-view': [17, 18, 21, 22],
    'geo-areas': [1, 2, 17, 22], committee: [29], 'store-operations': [6, 9, 10, 11, 14, 18], theming: [30] };
  const functions = new Map((existing.functions || []).map(f => [f.id, f]));
  for (const route of routes.filter(r => r.action?.startsWith('GovStore\\'))) {
    const pkg = namespaces[route.action.split('\\')[1]];
    if (!pkg) throw new Error(`Unmapped GovStore package: ${route.action}`);
    const id = `route.${route.name || `${route.method}.${route.uri}`}`.toLowerCase().replace(/[^a-z0-9_.-]/g, '-');
    const ability = route.middleware?.find(m => m.includes('RequireGovAbility:'))?.split(':')[1] || null;
    const source = { method: route.method, uri: route.uri, name: route.name, handler: route.action, ability };
    if (functions.has(id)) {
      const f = functions.get(id);
      if (!f.sources.some(s => s.method === source.method && s.uri === source.uri)) f.sources.push(source);
      continue;
    }
    const mutation = /POST|PUT|PATCH|DELETE/.test(route.method);
    functions.set(id, { id, title: `${route.method} /${route.uri}`, package: pkg,
      stories: (storyGroups[pkg] || []).map(n => `US-${String(n).padStart(2, '0')}`), sources: [source],
      applicationStatus: 'implemented', actors: [ability || 'Current handler authorization and actual role'],
      preconditions: ['Authorized fictional actor and matching working scope', 'Current handler-specific state and fixtures'],
      outcomes: [mutation ? 'Complete the normal UI action and independently verify its committed effects' : 'Interact with the consumer UI and verify meaningful scoped results'],
      effects: mutation ? 'Identify created/consumed records; use supported cleanup or report retention' : 'No business writes expected',
      branches: [
        { id: 'success', title: 'Authorized supported action and persisted/consumer outcome', requiredEvidence: 'function', journeys: [] },
        ...(mutation ? [{ id: 'validation', title: 'Invalid input/state refused without unintended business writes', requiredEvidence: 'function', journeys: [] },
          { id: 'boundary', title: 'Wrong role/ownership/office/company refused under current mode contract', requiredEvidence: 'function', journeys: [] }] : [])
      ] });
  }
  return { schemaVersion: 1,
    description: 'Required effective-route and connected-function inventory. Empty journey mappings are missing coverage, never passes. Route-group story associations are conservative package coverage; refine handler-specific branches against current code.',
    packages: Object.values(namespaces).sort(), stories: Array.from({ length: 31 }, (_, i) => `US-${String(i + 1).padStart(2, '0')}`),
    functions: [...functions.values()].sort((a, b) => a.id.localeCompare(b.id)) };
}

export function checkInventory(loader, registry) {
  const errors = [];
  const ids = new Set();
  const known = new Map(loader.listJourneys().map(j => [j.id, loader.loadJourneyByIdOrPath(j.id)]));
  for (const f of registry.functions) {
    if (ids.has(f.id)) errors.push(`Duplicate function ${f.id}`);
    ids.add(f.id);
    if (!registry.packages.includes(f.package)) errors.push(`${f.id}: unknown package`);
    for (const s of f.stories) if (!registry.stories.includes(s)) errors.push(`${f.id}: unknown story ${s}`);
    const branches = new Set();
    for (const b of f.branches) {
      if (branches.has(b.id)) errors.push(`${f.id}: duplicate branch ${b.id}`);
      branches.add(b.id);
      for (const ref of b.journeys) {
        const j = known.get(ref.id);
        if (!j) errors.push(`${f.id}/${b.id}: unknown journey ${ref.id}`);
        else if (ref.caseId && ref.caseId !== j.id && !j.matrix?.some((_, i) => ref.caseId === `${j.id}[case_${i}]`)) errors.push(`${f.id}/${b.id}: unknown matrix case ${ref.caseId}`);
      }
    }
  }
  for (const pkg of registry.packages) if (!registry.functions.some(f => f.package === pkg)) errors.push(`Package ${pkg} has no functions`);
  for (const s of registry.stories) if (!registry.functions.some(f => f.stories.includes(s))) errors.push(`Story ${s} has no functions`);
  return errors;
}
