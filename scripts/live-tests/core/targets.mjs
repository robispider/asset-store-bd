import fs from 'node:fs';
import path from 'node:path';
import { registerSecret } from '../reporters/redaction.mjs';

export function loadTargets(baseDir, file = process.env.LIVE_TEST_TARGETS_FILE) {
  const location = file || path.join(baseDir, 'storage/app/private/live-tests/targets.json');
  if (!fs.existsSync(location)) {
    if (file) throw new Error('The configured live-test target file does not exist.');
    return [];
  }
  let config;
  try { config = JSON.parse(fs.readFileSync(location, 'utf8')); }
  catch { throw new Error('The private live-test target file is not valid JSON.'); }
  if (!Array.isArray(config.targets)) throw new Error('The target file must contain a targets array.');
  const used = new Set();
  for (const target of config.targets) {
    if (!/^[a-z0-9_-]+$/i.test(target.id || '') || used.has(target.id)) throw new Error('Every target needs a unique id containing letters, numbers, hyphens or underscores.');
    used.add(target.id);
    let url;
    try { url = new URL(target.baseUrl); } catch { throw new Error(`Target ${target.id} needs a valid server URL.`); }
    if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) throw new Error(`Target ${target.id} needs an HTTP(S) URL without embedded credentials.`);
    if (!['local', 'staging', 'testing'].includes(target.environment)) throw new Error(`Target ${target.id} must declare a nonproduction environment.`);
    if (target.accessMode && !['shadow', 'enforce'].includes(target.accessMode)) throw new Error(`Target ${target.id} accessMode must be shadow or enforce.`);
    if ((!target.bootstrap?.username && !(target.database && Number.isSafeInteger(target.bootstrap?.userId) && target.bootstrap.userId > 0))
      || typeof target.bootstrap?.password !== 'string' || !target.bootstrap.password) throw new Error(`Target ${target.id} needs its bootstrap login (or userId with database configuration) and password.`);
    registerSecret(target.bootstrap.password);
    for (const account of Object.values(target.accounts || {})) registerSecret(account.password);
    if (target.database) {
      if ((target.database.driver || 'mysql') !== 'mysql' || !target.database.host || !target.database.database || !target.database.username) {
        throw new Error(`Target ${target.id} needs a MySQL host, database name and username.`);
      }
      registerSecret(target.database.password);
    }
  }
  return config.targets;
}

/** Dashboard metadata deliberately excludes account and database connection details. */
export function publicTargets(targets) {
  return targets.map(({ id, label, baseUrl, environment, database }) => ({ id, label: label || id, baseUrl,
    environment, hasDatabase: Boolean(database) }));
}
