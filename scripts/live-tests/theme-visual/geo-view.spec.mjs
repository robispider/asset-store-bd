// Geo view kit on the Theme Lab (geo-view plan phase C exit): drill, Back/Forward, zoom buttons, keyboard,
// table toggle, help, compare, picker, 375 px, and a screenshot of the progress demo in every theme × mode.
// Runs with the theme-visual config and its signed-in super admin storage state (see README.md).
import { test, expect } from '@playwright/test';

const THEMES = (process.env.GS_THEME_KEYS || 'default,institutional-green,digital-blue,executive-neutral,lavender,civic-ledger').split(',');
const LAB = (theme, mode = 'light') => `/gov/theme-lab/${theme}?gs_preview=${theme}&gs_mode=${mode}`;
const map = page => page.locator('#geo-focus-progress');
const area = (page, id, root = '#geo-focus-progress') => page.locator(`${root} svg.gs-geo__svg .gs-geo__area[data-id="${id}"]`);

async function ready(page, id = 'geo-focus-progress') {
    await page.waitForFunction(i => window.GsGeoView && window.GsGeoView.get(i) && window.GsGeoView.get(i).state().payload, id);
}

test('lazy bundle loads only on pages with a map @smoke', async ({ page }) => {
    const scripts = [];
    page.on('request', r => { if (/lazy[-/]geo/.test(r.url())) { scripts.push(r.url()); } });
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    expect(scripts).toHaveLength(0);
    await page.goto(LAB('institutional-green'));
    await ready(page);
    expect(scripts.length).toBe(1);
});

test('drill, up, breadcrumb, Back and Forward, deep link @smoke', async ({ page }) => {
    await page.goto(LAB('institutional-green'));
    await ready(page);
    await area(page, 10).click();
    await expect(map(page).locator('.gs-geo__crumb[aria-current]')).toHaveText(/North|উত্তর/);
    await expect(page).toHaveURL(/gv-geo-focus-progress\[area\]=10/);
    await area(page, 100).click();
    await expect(map(page).locator('.gs-geo__crumb')).toHaveCount(3);
    await map(page).locator('[data-gs-geo-up]').click();
    await expect(page).toHaveURL(/\[area\]=10/);
    await page.goBack();
    await expect(map(page).locator('.gs-geo__crumb')).toHaveCount(3);
    await page.goBack();
    await page.goBack();
    await expect(map(page).locator('.gs-geo__crumb')).toHaveCount(1);
    await page.goForward();
    await expect(map(page).locator('.gs-geo__crumb')).toHaveCount(2);
    const deep = page.url();
    const fresh = await page.context().newPage();
    await fresh.goto(deep);
    await ready(fresh);
    await expect(fresh.locator('#geo-focus-progress .gs-geo__crumb')).toHaveCount(2);
});

test('zoom buttons, keyboard, table toggle and help', async ({ page }) => {
    await page.goto(LAB('institutional-green'));
    await ready(page);
    const viewBox = () => map(page).locator('svg.gs-geo__svg').getAttribute('viewBox');
    const before = await viewBox();
    await map(page).getByRole('button', { name: /Zoom in|বড় করুন/ }).click();
    await page.waitForTimeout(400);
    expect(await viewBox()).not.toBe(before);
    await map(page).getByRole('button', { name: /Reset view|শুরুর দৃশ্য/ }).click();

    const first = map(page).locator('svg .gs-geo__area[tabindex="0"]');
    await first.focus();
    await page.keyboard.press('ArrowRight');
    await expect(map(page).locator('svg .gs-geo__area:focus')).toHaveCount(1);
    await page.keyboard.press('Enter');
    await expect(map(page).locator('.gs-geo__crumb')).toHaveCount(2);
    await map(page).locator('.gs-geo__stage').focus();
    await page.keyboard.press('Escape');
    await expect(map(page).locator('.gs-geo__crumb')).toHaveCount(1);
    await page.keyboard.press('?');
    await expect(map(page).locator('.gs-geo__help')).toBeVisible();
    await page.keyboard.press('?');
    await expect(map(page).locator('.gs-geo__help')).toBeHidden();

    await map(page).getByRole('button', { name: /^(Table|সারণি)$/ }).click();
    await expect(map(page).locator('.gs-geo__table tbody tr')).toHaveCount(4);
    await expect(map(page).locator('.gs-geo__stage')).toBeHidden();
});

test('compare selects several areas; picker writes the chosen id', async ({ page }) => {
    await page.goto(LAB('institutional-green'));
    await ready(page, 'geo-focus-compare');
    await area(page, 10, '#geo-focus-compare').click();
    await area(page, 11, '#geo-focus-compare').click({ modifiers: ['Shift'] });
    await expect(page.locator('#geo-focus-compare .gs-geo__compare dt')).toHaveCount(2);

    await ready(page, 'geo-focus-pick');
    await area(page, 12, '#geo-focus-pick').click();
    await expect(page.locator('#geo-focus-pick input[name="geo-focus-pick-area"]')).toHaveValue('12');
    await area(page, 12, '#geo-focus-pick').click();   // second click opens it
    await expect(page.locator('#geo-focus-pick .gs-geo__crumb')).toHaveCount(2);
});

test('depth tools: find, flat mode, export and full screen', async ({ page }) => {
    await page.goto(LAB('institutional-green'));
    await ready(page);
    const find = map(page).locator('.gs-geo__search input');
    await find.fill(await page.evaluate(() => document.documentElement.lang.startsWith('bn') ? 'দক্ষিণ ২ক' : 'South 2a'));
    await map(page).locator('.gs-geo__results button').first().click();
    await expect(map(page).locator('svg .gs-geo__area.is-selected')).toHaveCount(1);
    await expect(page).toHaveURL(/\[select\]=1310/);

    await map(page).locator('.gs-geo__extra .gs-geo__btn').first().click();   // flat mode
    await expect(page).toHaveURL(/\[flat\]=2/);
    await expect(map(page).locator('svg .gs-geo__area')).toHaveCount(12);

    const csv = page.waitForEvent('download');
    await map(page).getByRole('button', { name: /CSV/ }).click();
    expect((await csv).suggestedFilename()).toMatch(/\.csv$/);
    const png = page.waitForEvent('download');
    await map(page).getByRole('button', { name: /image|ছবি/ }).click();
    expect((await png).suggestedFilename()).toMatch(/\.png$/);

    await map(page).getByRole('button', { name: /Full screen|পূর্ণ পর্দা/ }).click();
    await expect.poll(() => page.evaluate(() => document.fullscreenElement && document.fullscreenElement.id)).toBe('geo-focus-progress');
    await page.keyboard.press('Escape');
});

test('phone width keeps the panel below the map and 44 px targets', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto(LAB('institutional-green'));
    await ready(page);
    const stage = await map(page).locator('.gs-geo__stage').boundingBox();
    const panel = await map(page).locator('.gs-geo__panel').boundingBox();
    expect(panel.y).toBeGreaterThanOrEqual(stage.y + stage.height - 1);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});

for (const theme of THEMES) {
    for (const mode of ['light', 'dark']) {
        test(`progress demo ${theme} ${mode}`, async ({ page }) => {
            await page.goto(LAB(theme, mode));
            await ready(page);
            await page.addStyleTag({ content: '*{transition:none!important;animation:none!important}' });
            await map(page).scrollIntoViewIfNeeded();
            await expect(map(page)).toHaveScreenshot(`geo-${theme}-${mode}.png`);
        });
    }
}
