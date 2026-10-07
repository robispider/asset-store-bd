/**
 * GovStore Live Testing - Redaction & Privacy Guard
 * Strips credentials, tokens, session IDs, and database secrets from logs and artifacts.
 */

const SENSITIVE_PATTERNS = [
  /password['"]?\s*[:=]\s*['"]?[^'"\s\\]+/gi,
  /secret['"]?\s*[:=]\s*['"]?[^'"\s\\]+/gi,
  /_token['"]?\s*[:=]\s*['"]?[^'"\s\\]+/gi,
  /bearer\s+[a-zA-Z0-9_\-.]+/gi,
  /laravel_session=[a-zA-Z0-9%_-]+/gi,
  /XSRF-TOKEN=[a-zA-Z0-9%_-]+/gi,
  /\$2y\$[0-9]{2}\$[a-zA-Z0-9.\/]{53}/g // bcrypt hash pattern
];

const KNOWN_SECRETS = new Set();

/** Register a literal secret (e.g. a resolved fixture password) so it is masked wherever it appears. */
export function registerSecret(value) {
  if (typeof value === 'string' && value.length >= 4) KNOWN_SECRETS.add(value);
}

export function redactString(str) {
  if (typeof str !== 'string') return str;
  let redacted = str;
  for (const secret of KNOWN_SECRETS) {
    redacted = redacted.split(secret).join('[REDACTED]');
  }
  for (const pattern of SENSITIVE_PATTERNS) {
    redacted = redacted.replace(pattern, (match) => {
      const parts = match.split(/[:=]/);
      if (parts.length === 2) {
        return `${parts[0]}=[REDACTED]`;
      }
      return '[REDACTED_SECRET]';
    });
  }
  return redacted;
}

export function redactObject(obj) {
  if (obj === null || obj === undefined) return obj;
  if (typeof obj === 'string') return redactString(obj);
  if (typeof obj === 'number' || typeof obj === 'boolean') return obj;

  if (Array.isArray(obj)) {
    return obj.map(item => redactObject(item));
  }

  if (typeof obj === 'object') {
    const clean = {};
    for (const [key, val] of Object.entries(obj)) {
      const lower = key.toLowerCase();
      if (lower.includes('password') || lower.includes('secret') || lower.includes('token') || lower === 'hash') {
        clean[key] = '[REDACTED]';
      } else {
        clean[key] = redactObject(val);
      }
    }
    return clean;
  }

  return obj;
}
