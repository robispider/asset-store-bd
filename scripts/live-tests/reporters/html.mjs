import { redactObject } from './redaction.mjs';

const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

/** Self-contained private HTML report built from results/provenance/cleanup files. No external assets. */
export function renderHtmlReport({ results, provenance = {}, cleanup = {}, ownership = [] }) {
  const rows = (results.results || []).map(r => redactObject(r)).map(r => `
    <tr class="${esc(r.status)}">
      <td>${esc(r.journeyId)}</td><td>${esc(r.status)}</td><td>${esc(r.failureClass || r.code || '')}</td>
      <td>${esc(r.durationMs ?? '')}</td><td>${esc(r.cleanup?.status ?? '')}</td>
      <td>${esc(r.error || '')}</td></tr>`).join('');
  const s = results.summary || {};
  const retained = (cleanup.retained || []).map(e => `<li><code>${esc(e.kind)}</code> ${esc(e.id)} &mdash; ${esc(e.effect)}</li>`).join('') || '<li>none</li>';
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Live test report ${esc(provenance.runId)}</title>
<style>body{font:14px system-ui;margin:24px;color:#222}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:6px;text-align:left;vertical-align:top}
.passed{background:#e8f5e9}.failed{background:#ffebee}.blocked{background:#fff8e1}.interrupted,.inconclusive{background:#eceff1}code{background:#f4f4f4;padding:1px 4px}</style></head><body>
<h1>Live journey report</h1>
<p>Run <code>${esc(provenance.runId)}</code> against <code>${esc(provenance.baseUrl)}</code> at revision <code>${esc(provenance.application?.revision)}</code> (${esc(provenance.application?.dirtyFiles)} dirty files). Access mode <code>${esc(provenance.preflight?.accessMode)}</code>, seed <code>${esc(provenance.seed)}</code>, unique suffix <code>${esc(provenance.uniqueSuffix)}</code>.</p>
<p>Total ${esc(s.total)} &middot; passed ${esc(s.passed)} &middot; failed ${esc(s.failed)} &middot; blocked ${esc(s.blocked)} &middot; interrupted ${esc(s.interrupted ?? 0)}. A pass means its explicit assertions passed on this revision, environment and fixture state only.</p>
<table><thead><tr><th>Journey</th><th>Status</th><th>Class/code</th><th>ms</th><th>Cleanup</th><th>Error</th></tr></thead><tbody>${rows}</tbody></table>
<h2>Cleanup and retained records</h2><p>Status <code>${esc(cleanup.status)}</code>; ledger entries ${ownership.length}.</p><ul>${retained}</ul>
</body></html>`;
}
