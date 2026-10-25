import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';

/** One writing runner per origin: concurrent receipt/issue runs invalidate balance checks. */
export function lockWritingTarget(root, baseUrl, runId) {
  const key = createHash('sha256').update(new URL(baseUrl).origin).digest('hex').slice(0, 24);
  const file = path.join(root, `target-${key}.lock`);
  fs.mkdirSync(root, { recursive: true });
  const claim = () => {
    const fd = fs.openSync(file, 'wx');
    try { fs.writeFileSync(fd, JSON.stringify({ pid: process.pid, runId })); } finally { fs.closeSync(fd); }
  };
  try { claim(); } catch (error) {
    if (error.code !== 'EEXIST') throw error;
    const previous = JSON.parse(fs.readFileSync(file, 'utf8'));
    let alive = true;
    try { process.kill(previous.pid, 0); } catch (error) { if (error.code === 'ESRCH') alive = false; }
    if (alive) throw Object.assign(new Error(`Another writing live run owns this target (${previous.runId}). Finish that run before starting another.`), { code: 'preflight.target-busy' });
    fs.unlinkSync(file);
    claim();
  }
  return () => {
    if (!fs.existsSync(file)) return;
    const owner = JSON.parse(fs.readFileSync(file, 'utf8'));
    if (owner.pid === process.pid && owner.runId === runId) fs.unlinkSync(file);
  };
}
