import { redactObject } from './redaction.mjs';

const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

/** Self-contained private HTML report built from results/provenance/cleanup files. No external assets. */
export function renderHtmlReport({ results, provenance = {}, cleanup = {}, ownership = [], coverage = null }) {
  const rows = (results.results || []).map(r => redactObject(r)).map(r => `
    <tr class="${esc(r.status)}">
      <td>${esc(r.caseId || r.journeyId)}</td><td>${esc(r.status)}</td><td>${esc(r.evidenceLevel || 'unknown')} · skipped ${esc(r.skippedSteps || 0)}</td><td>${esc(r.failureClass || r.code || '')}</td>
      <td>${esc(r.durationMs ?? '')}</td><td>${esc(r.cleanup?.status ?? '')}</td>
      <td>${esc(r.error || '')}</td></tr>`).join('');
  const s = results.summary || {};
  const retained = (cleanup.retained || []).map(e => `<li><code>${esc(e.kind)}</code> ${esc(e.id)} &mdash; ${esc(e.effect)}</li>`).join('') || '<li>none</li>';
  const functional = coverage ? `<h2>Full functional acceptance: ${coverage.summary.complete ? 'complete' : 'incomplete'}</h2>
<p>${esc(coverage.summary.completeFunctions)} / ${esc(coverage.summary.functions)} functions complete;
${esc(coverage.summary.passedBranches)} / ${esc(coverage.summary.branches)} required branches passed;
${esc(coverage.summary.missingBranches)} branches have no journey. Selected journey passes do not establish full acceptance.</p>
<table><thead><tr><th>Package / function</th><th>Required branch</th><th>Result</th></tr></thead><tbody>${coverage.functions.flatMap(f => f.branches.map(b => `<tr class="${esc(b.status)}"><td>${esc(f.package)} / ${esc(f.title)}</td><td>${esc(b.title)}</td><td>${esc(b.status)}</td></tr>`)).join('')}</tbody></table>` : '';
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Live test report ${esc(provenance.runId)}</title>
<style>body{font:14px system-ui;margin:24px;color:#222}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:6px;text-align:left;vertical-align:top}
.passed{background:#e8f5e9}.failed{background:#ffebee}.blocked{background:#fff8e1}.interrupted,.inconclusive{background:#eceff1}code{background:#f4f4f4;padding:1px 4px}</style></head><body>
<h1>Live journey report</h1>
<p>Run <code>${esc(provenance.runId)}</code> against <code>${esc(provenance.baseUrl)}</code>. Deployed revision <code>${esc(provenance.deployment?.revision || 'unknown')}</code> (${esc(provenance.deployment?.source || 'unknown source')}). Runner revision <code>${esc(provenance.runner?.revision || provenance.application?.revision)}</code>. Access mode <code>${esc(provenance.preflight?.accessMode)}</code>, seed <code>${esc(provenance.seed)}</code>, unique suffix <code>${esc(provenance.uniqueSuffix)}</code>.</p>
<p>Total ${esc(s.total)} &middot; passed ${esc(s.passed)} &middot; failed ${esc(s.failed)} &middot; blocked ${esc(s.blocked)} &middot; interrupted ${esc(s.interrupted ?? 0)}. A pass means its explicit assertions passed on this revision, environment and fixture state only.</p>
<p>Target <code>${esc(provenance.targetId || 'local')}</code>; fixtures from <code>${esc(provenance.fixtureSource || 'legacy')}</code>; verification channel <code>${esc(provenance.verificationChannel || 'legacy')}</code>. ${provenance.verificationChannel === 'browser-interface' ? 'Live database not accessed. Saved outcomes are checked through reopened screens, registers and history.' : 'This historical run is not certified as interface-only evidence.'} Configured-target environment and access mode are operator declarations.</p>
<table><thead><tr><th>Journey / branch</th><th>Status</th><th>Evidence</th><th>Class/code</th><th>ms</th><th>Cleanup</th><th>Error</th></tr></thead><tbody>${rows}</tbody></table>
${functional}
<h2>Cleanup and retained records</h2><p>Status <code>${esc(cleanup.status)}</code>; ledger entries ${ownership.length}.</p><ul>${retained}</ul>
</body></html>`;
}
