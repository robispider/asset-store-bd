// Every Theme Lab focus-view section × 5 themes × 2 modes, plus real pages under each theme.
// Real pages are rendered through the never-saved `gs_preview` override, which requires a
// super admin session (GS_THEME_STORAGE_STATE). Optional page URLs come from env vars.
import { test, expect } from '@playwright/test';

const THEMES = (process.env.GS_THEME_KEYS || 'default,institutional-green,digital-blue,executive-neutral,lavender').split(',');
const MODES = ['light', 'dark'];
const SECTIONS = ['colour', 'type', 'shape', 'core', 'kit', 'general', 'patterns', 'charts', 'print', 'validation'];

const PAGES = [
    { name: 'dashboard', path: '/' },
    { name: 'assets-list', path: '/hardware' },
    { name: 'asset-detail', path: process.env.GS_THEME_ASSET_PATH || '/hardware/1' },
    { name: 'store-documents-hub', path: process.env.GS_THEME_HUB_PATH || '/gov-store/operations/hub' },
    { name: 'goods-receipt', path: process.env.GS_THEME_RECEIPT_PATH },
].filter((page) => page.path);

/** Hide content that changes between runs (clocks, counters, generated ids). */
async function stabilise(page) {
    await page.addStyleTag({ content: '*{transition:none!important;animation:none!important} .gs-timeline__meta,time,.main-footer{visibility:hidden!important}' });
    await page.evaluate(() => document.fonts && document.fonts.ready);
}

for (const theme of THEMES) {
    for (const mode of MODES) {
        test.describe(`${theme} · ${mode}`, () => {
            test(`lab sections ${theme} ${mode}${theme === 'institutional-green' ? ' @smoke' : ''}`, async ({ page }) => {
                await page.goto(`/gov/theme-lab/${theme}?gs_preview=${theme}&gs_mode=${mode}`);
                await expect(page.locator('html')).toHaveAttribute('data-skin', theme);
                await expect(page.locator('html')).toHaveAttribute('data-theme', mode);
                await stabilise(page);
                for (const section of SECTIONS) {
                    const el = page.locator(`#gs-panel-focus [data-lab-section="${section}"]`);
                    await el.scrollIntoViewIfNeeded();
                    await expect(el).toHaveScreenshot(`lab-${theme}-${mode}-${section}.png`);
                }
            });

            for (const target of PAGES) {
                test(`page ${target.name} ${theme} ${mode}`, async ({ page }) => {
                    const join = target.path.includes('?') ? '&' : '?';
                    await page.goto(`${target.path}${join}gs_preview=${theme}&gs_mode=${mode}`);
                    await expect(page.locator('html')).toHaveAttribute('data-skin', theme);
                    await stabilise(page);
                    await expect(page).toHaveScreenshot(`page-${target.name}-${theme}-${mode}.png`, { fullPage: true });
                });
            }
        });
    }
}

// Guests (login) get the organisation default; mode comes from localStorage / the OS.
for (const mode of MODES) {
    test(`login page ${mode} @smoke`, async ({ browser }) => {
        const context = await browser.newContext({ storageState: undefined, colorScheme: mode });
        await context.addInitScript((m) => { try { localStorage.setItem('theme', m); } catch (e) { /* ignore */ } }, mode);
        const page = await context.newPage();
        await page.goto('/login');
        await expect(page.locator('html')).toHaveAttribute('data-theme', mode);
        await stabilise(page);
        await expect(page).toHaveScreenshot(`page-login-${mode}.png`, { fullPage: true });
        await context.close();
    });
}

test('no flash: explicit dark mode is on <html> before first paint @smoke', async ({ page }) => {
    let firstTheme = null;
    page.on('response', async (response) => {
        if (firstTheme === null && response.request().resourceType() === 'document') {
            const body = await response.text();
            const tag = body.match(/<html\b[^>]*>/i);
            firstTheme = tag ? tag[0] : '';
        }
    });
    await page.goto('/gov/theme-lab/digital-blue?gs_preview=digital-blue&gs_mode=dark');
    expect(firstTheme).toContain('data-theme="dark"');
    expect(firstTheme).toContain('data-skin="digital-blue"');
});
