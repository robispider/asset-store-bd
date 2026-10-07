// Visual regression for gov-store/theming (§16.4).
//
//   GS_THEME_BASE_URL=http://snipeit.local \
//   GS_THEME_STORAGE_STATE=scripts/live-tests/theme-visual/.auth/superuser.json \
//   npm run test:themes                      # compare
//   npm run test:themes -- --update-snapshots  # (re)record baselines
//
// The storage state is a signed-in super admin session saved by you (see README.md);
// no credentials are ever stored in or typed by this suite.
import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.GS_THEME_BASE_URL || 'http://snipeit.local';
const host = new URL(baseURL).hostname;
if (!/^(localhost|127\.0\.0\.1|\[::1\]|[a-z0-9-]+\.(local|localhost|test))$/i.test(host)) {
    throw new Error(`theme-visual only runs against local / non-production hosts (got ${host}).`);
}

export default defineConfig({
    testDir: '.',
    testMatch: /.*\.spec\.mjs$/,
    snapshotPathTemplate: '{testDir}/__screenshots__/{projectName}/{arg}{ext}',
    timeout: 60_000,
    fullyParallel: true,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
    expect: {
        toHaveScreenshot: { maxDiffPixelRatio: 0.002, animations: 'disabled', caret: 'hide' },
    },
    use: {
        baseURL,
        storageState: process.env.GS_THEME_STORAGE_STATE || undefined,
        viewport: { width: 1440, height: 900 },
        locale: 'en-US',
        timezoneId: 'Asia/Dhaka',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
        // Smoke subset on the other engines (tests tagged @smoke).
        { name: 'firefox', grep: /@smoke/, use: { ...devices['Desktop Firefox'], viewport: { width: 1440, height: 900 } } },
        { name: 'webkit', grep: /@smoke/, use: { ...devices['Desktop Safari'], viewport: { width: 1440, height: 900 } } },
    ],
});
