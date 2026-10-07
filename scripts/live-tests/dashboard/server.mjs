import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { TestLoader } from '../core/loader.mjs';
import { redactObject } from '../reporters/redaction.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const LOCAL_HOST = /^(localhost|127\.0\.0\.1|\[::1\]|[a-z0-9-]+\.(local|localhost|test))$/i;

/**
 * Local-only operator dashboard. Binds to 127.0.0.1, requires a random per-start token on every request,
 * checks the Host header, and only ever launches this repository's CLI with validated arguments.
 */
export async function startDashboard({ baseDir, port = 4780 }) {
  const token = crypto.randomBytes(18).toString('hex');
  const runsDir = path.join(baseDir, 'storage', 'app', 'private', 'live-tests');
  const loader = new TestLoader(baseDir);
  let active = null; // { runId, child, startedAt, args }

  const json = (res, code, body) => {
    res.writeHead(code, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
    res.end(JSON.stringify(body));
  };
  const readJson = (file, fallback) => { try { return JSON.parse(fs.readFileSync(file, 'utf8')); } catch { return fallback; } };
  const safeRunId = id => /^run_[A-Za-z0-9_-]+$/.test(String(id || '')) ? id : null;

  const listRuns = () => {
    if (!fs.existsSync(runsDir)) return [];
    return fs.readdirSync(runsDir).filter(n => n.startsWith('run_')).sort().reverse().slice(0, 25).map(id => {
      const r = readJson(path.join(runsDir, id, 'results.json'), null);
      const p = readJson(path.join(runsDir, id, 'provenance.json'), {});
      return { id, summary: r?.summary || null, baseUrl: p.baseUrl, suiteId: p.suiteId, testId: p.testId, tests: p.tests, startedAt: p.startedAt, running: active?.runId === id };
    });
  };

  const readBody = req => new Promise((resolve, reject) => {
    let data = '';
    req.on('data', c => { data += c; if (data.length > 20000) reject(new Error('body too large')); });
    req.on('end', () => { try { resolve(data ? JSON.parse(data) : {}); } catch (e) { reject(e); } });
  });

  function launch(opts) {
    if (active) throw Object.assign(new Error('A run is already in progress'), { status: 409 });
    const args = [path.join(baseDir, 'scripts', 'live-tests', 'cli.mjs'), 'run'];
    const known = [...loader.listSuites().map(s => s.id)];
    const journeys = loader.listJourneys().map(j => j.id);
    if (Array.isArray(opts.tests)) {
      if (!opts.tests.length) throw Object.assign(new Error('Select at least one journey'), { status: 400 });
      const bad = opts.tests.filter(t => !journeys.includes(t));
      if (bad.length) throw Object.assign(new Error('Unknown journey: ' + bad.join(', ')), { status: 400 });
      args.push('--tests', opts.tests.join(','));
    } else if (opts.test) {
      if (!journeys.includes(opts.test)) throw Object.assign(new Error('Unknown journey'), { status: 400 });
      args.push('--test', opts.test);
    } else {
      if (!known.includes(opts.suite)) throw Object.assign(new Error('Unknown suite'), { status: 400 });
      args.push('--suite', opts.suite);
    }
    if (opts.baseUrl) {
      let u;
      try { u = new URL(opts.baseUrl); } catch { throw Object.assign(new Error('Invalid server URL'), { status: 400 }); }
      if (!/^https?:$/.test(u.protocol)) throw Object.assign(new Error('URL must be http(s)'), { status: 400 });
      const extra = (process.env.LIVE_TEST_ALLOWED_HOSTS || '').split(',').map(h => h.trim().toLowerCase()).filter(Boolean);
      if (!LOCAL_HOST.test(u.hostname) && !extra.includes(u.hostname.toLowerCase())) {
        throw Object.assign(new Error(`Host '${u.hostname}' is not allowed. Local hosts are allowed; for another server set LIVE_TEST_ALLOWED_HOSTS before starting the dashboard (the server must use the same database the PHP bridge reads).`), { status: 400 });
      }
      args.push('--baseUrl', u.origin);
    }
    if (opts.headed) {
      args.push('--headed');
      const slow = Math.max(0, Math.min(parseInt(opts.slowMo, 10) || 0, 2000));
      if (slow) args.push('--slow-mo', String(slow));
    }
    if (opts.seed) args.push('--seed', String(parseInt(opts.seed, 10) || 20261006));
    if (opts.retryReadonly) args.push('--retry-readonly', String(Math.min(parseInt(opts.retryReadonly, 10) || 0, 3)));
    if (opts.allowWrites) args.push('--allow-writes');
    if (opts.allowExternalMail) args.push('--allow-external-mail');

    const runId = `run_${Date.now()}_DASH`;
    args.push('--run-id', runId);
    const runDir = path.join(runsDir, runId);
    fs.mkdirSync(runDir, { recursive: true });
    const log = fs.createWriteStream(path.join(runDir, 'console.log'));
    const child = spawn(process.execPath, args, { cwd: baseDir, env: process.env, stdio: ['ignore', 'pipe', 'pipe'] });
    const sink = chunk => log.write(redactObject(String(chunk)));
    child.stdout.on('data', sink);
    child.stderr.on('data', sink);
    active = { runId, child, startedAt: new Date().toISOString(), exitCode: null };
    child.on('exit', code => { active.exitCode = code; log.end(); setTimeout(() => { if (active?.runId === runId) active = null; }, 1500); });
    return runId;
  }

  const server = http.createServer(async (req, res) => {
    try {
      const url = new URL(req.url, 'http://127.0.0.1');
      const host = (req.headers.host || '').split(':')[0];
      if (!['127.0.0.1', 'localhost'].includes(host)) return json(res, 403, { error: 'bad host' });
      if (url.pathname === '/') {
        if (url.searchParams.get('t') !== token) { res.writeHead(403); return res.end('Open the URL printed by the dashboard command (it contains the access token).'); }
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' });
        return res.end(fs.readFileSync(path.join(HERE, 'index.html'), 'utf8'));
      }
      if ((req.headers['x-dashboard-token'] || url.searchParams.get('t')) !== token) return json(res, 403, { error: 'bad token' });

      if (url.pathname === '/api/state') {
        return json(res, 200, {
          suites: loader.listSuites().map(s => ({ id: s.id, title: s.title, journeys: s.journeys })),
          journeys: loader.listJourneys().map(j => ({ id: j.id, title: j.title, capability: j.capability })),
          runs: listRuns(),
          active: active ? { runId: active.runId, startedAt: active.startedAt } : null,
          defaultBaseUrl: 'http://snipeit.local'
        });
      }
      if (url.pathname === '/api/run' && req.method === 'POST') {
        const runId = launch(await readBody(req));
        return json(res, 200, { runId });
      }
      if (url.pathname === '/api/stop' && req.method === 'POST') {
        if (active) active.child.kill();
        return json(res, 200, { stopped: Boolean(active) });
      }
      if (url.pathname === '/api/events') {
        const runId = safeRunId(url.searchParams.get('runId'));
        if (!runId) return json(res, 400, { error: 'bad run id' });
        const dir = path.join(runsDir, runId);
        const events = [];
        const file = path.join(dir, 'events.jsonl');
        if (fs.existsSync(file)) {
          for (const line of fs.readFileSync(file, 'utf8').split('\n')) { if (line) try { events.push(JSON.parse(line)); } catch { /* partial line */ } }
        }
        let consoleTail = '';
        try { consoleTail = fs.readFileSync(path.join(dir, 'console.log'), 'utf8').slice(-4000); } catch { /* not yet */ }
        return json(res, 200, {
          events: events.slice(-400),
          console: consoleTail,
          results: readJson(path.join(dir, 'results.json'), null),
          cleanup: readJson(path.join(dir, 'cleanup.json'), null),
          provenance: readJson(path.join(dir, 'provenance.json'), null),
          running: active?.runId === runId,
          exitCode: active?.runId === runId ? active.exitCode : undefined
        });
      }
      if (url.pathname === '/api/report') {
        const runId = safeRunId(url.searchParams.get('runId'));
        const file = runId && path.join(runsDir, runId, 'report.html');
        if (!file || !fs.existsSync(file)) return json(res, 404, { error: 'no report yet' });
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        return res.end(fs.readFileSync(file));
      }
      if (url.pathname === '/api/screenshot') {
        const runId = safeRunId(url.searchParams.get('runId'));
        const name = path.basename(url.searchParams.get('name') || '');
        const file = runId && name.endsWith('.png') && path.join(runsDir, runId, 'artifacts', name);
        if (!file || !fs.existsSync(file)) return json(res, 404, { error: 'not found' });
        res.writeHead(200, { 'Content-Type': 'image/png', 'Cache-Control': 'no-store' });
        return res.end(fs.readFileSync(file));
      }
      return json(res, 404, { error: 'not found' });
    } catch (err) {
      return json(res, err.status || 500, { error: err.message });
    }
  });

  await new Promise(resolve => server.listen(port, '127.0.0.1', resolve));
  console.log(`\nLive test dashboard (local only): http://127.0.0.1:${port}/?t=${token}\nPress Ctrl+C to stop.\n`);
}
