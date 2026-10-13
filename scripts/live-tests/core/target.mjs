/** The browser and read-only fixture bridge must target the same application origin. */
export function assertMatchingTarget(baseUrl, preflight) {
  const parse = value => {
    let url;
    try { url = new URL(value); } catch { throw new Error('The fixture bridge must report a valid application URL.'); }
    if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) {
      throw new Error('Live-test application URLs must use HTTP(S) without embedded credentials.');
    }
    return url.origin;
  };
  try {
    const browserOrigin = parse(baseUrl);
    const bridgeOrigin = parse(preflight?.appUrl);
    if (browserOrigin !== bridgeOrigin) {
      throw new Error(`Browser targets ${browserOrigin}, but fixture discovery reads the application configured at ${bridgeOrigin}. Use that application's URL, or run the fixture bridge on the target installation. Changing the server URL or host allowlist does not change the fixture database.`);
    }
    return { browserOrigin, bridgeOrigin };
  } catch (error) {
    error.code = 'preflight.target-mismatch';
    throw error;
  }
}
