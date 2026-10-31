/** Read only rendered application screens using the current actor's browser session.
 * These are interface evidence, never independent database assertions.
 */
import { interfaceNumber } from '../core/interface-values.mjs';
export const INTERFACE_CHECKS = ['ui.document', 'ui.stockCard', 'ui.basket', 'ui.request', 'ui.tracking', 'ui.asset'];

export async function inspectInterface(current, check, args, baseUrl) {
  if (!INTERFACE_CHECKS.includes(check)) throw new Error(`Unsupported interface inspection '${check}'`);
  const page = await current.context().newPage();
  const visit = async pathname => {
    const url = new URL(pathname, baseUrl);
    if (url.origin !== new URL(baseUrl).origin) throw new Error('Interface inspection must stay on the selected target');
    const response = await page.goto(url.href, { waitUntil: 'load' });
    if (!response?.ok() || new URL(page.url()).pathname === '/login') throw new Error(`Assertion failed: interface screen unavailable (${response?.status()})`);
  };
  try {
    let result;
    if (check === 'ui.asset') {
      await visit(`/hardware/${args.id}/edit`);
      await page.locator('input[name="serials[1]"]').waitFor();
      result = { statusId: await page.locator('select[name="status_id"]').inputValue(),
        serial: await page.locator('input[name="serials[1]"]').inputValue(), requestable: await page.locator('#requestable').isChecked() };
    } else if (check === 'ui.document') {
      await visit(`/gov-store/operations/documents/${args.type || 'receipt'}/${args.id}`);
      await page.locator('#sumQty').waitFor();
      let assets = await page.locator('.at-issued__tag').evaluateAll(nodes => nodes.map(n => ({
        id: Number(n.getAttribute('href')?.match(/\/hardware\/(\d+)/)?.[1]), tag: n.textContent.trim()
      })));
      result = { quantity: Number(await page.locator('#sumQty').innerText()),
        number: (await page.locator('h4.storeops-inline-186').innerText()).trim(),
        posted: await page.locator('.box-success #sumQty').count() === 1,
        assets, assetCount: assets.length, attribution: await page.locator('.col-md-4 > p').allTextContents() };
      if (args.modelId) {
        await visit(`/gov-store/operations/kardex/assetmodel/${args.modelId}`);
        const rows = page.locator('#units tr').filter({ has: page.locator(`a[href$="/documents/receipt/${args.id}"]`) });
        assets = await rows.evaluateAll(rows => rows.map(row => {
          const link = row.querySelector('a[href*="/hardware/"]');
          return { id: Number(link?.getAttribute('href')?.match(/\/hardware\/(\d+)/)?.[1]), tag: link?.textContent.trim() };
        }));
        result.assets = assets;
        result.assetCount = assets.length;
      }
    } else if (check === 'ui.stockCard') {
      await visit(`/gov-store/operations/kardex/${args.type}/${args.id}`);
      const values = await page.locator('.gs-kpis .gs-kpi__value').allTextContents();
      if (values.length !== 4) throw new Error('Assertion failed: stock card did not expose four balance totals');
      result = { broughtForward: interfaceNumber(values[0]), received: interfaceNumber(values[1]), issued: Math.abs(interfaceNumber(values[2])), closing: interfaceNumber(values[3]),
        documents: await page.locator('.storeops-ledger a.storeops-mono').allTextContents() };
    } else if (check === 'ui.basket') {
      await visit('/gov-requests/basket');
      await page.locator('[data-cr-basket], [data-cr-draft], .cr').first().waitFor();
      const lines = page.locator('[data-cr-line]').filter({ hasText: args.name });
      if (await lines.count() > 1) throw new Error('Assertion failed: basket item name is ambiguous');
      result = { count: await lines.count(), quantity: await lines.count() ? Number(await lines.locator('[data-cr-line-qty]').inputValue()) : 0 };
    } else if (check === 'ui.tracking') {
      await visit(`/gov-store/admin/tracking/initiatives/${args.initiativeId}/manage?q=${encodeURIComponent(args.code)}&tab=all`);
      const row = page.locator('tr[id^="code-"]').filter({ has: page.locator('a.gs-tracking-code', { hasText: args.code }) });
      if (await row.count() !== 1) throw new Error('Assertion failed: tracking code was not uniquely found in Manage');
      const id = Number((await row.getAttribute('id')).replace('code-', ''));
      const detail = page.locator(`#manage-detail-${id}`);
      const planned = await detail.locator('tfoot .gs-num').first().innerText();
      const key = await row.locator('.gs-tracking-manage__status [data-status]').getAttribute('data-status');
      const status = { draft: 'DRAFT', success: 'ACTIVE', archived: 'ARCHIVED' }[key];
      if (!status) throw new Error('Assertion failed: unknown tracking lifecycle badge');
      result = { id, status, planned: interfaceNumber(planned), hasOrder: await detail.locator('a[href*="/download"]').count() > 0 };
      await visit(`/gov-store/admin/tracking/initiatives/${args.initiativeId}/tracking-codes/${id}`);
      const values = await page.locator('.gs-tracking-kpis .gs-kpi__value').allTextContents();
      if (values.length < 2) throw new Error('Assertion failed: tracking monitor totals are missing');
      result.received = interfaceNumber(values[0]);
      result.value = interfaceNumber(values[1]);
      result.offices = await page.locator('a[href*="/offices/"]').allTextContents();
    } else if (check === 'ui.request') {
      if (!['my-requests', 'admin', 'fulfillment-register'].includes(args.view)) throw new Error('Unsupported request inspection view');
      await visit(`/gov-requests/${args.view}/${args.id}`);
      const root = page.locator('[data-request-number][data-status-key]').first();
      await root.waitFor();
      const lines = await page.locator('tr[data-line-id]').evaluateAll(rows => rows.map(row => ({
        name: row.cells[0]?.textContent.trim(), requested: row.cells[1]?.textContent.trim(), approved: row.cells[2]?.textContent.trim(), issued: row.cells[3]?.textContent.trim()
      })));
      result = { number: await root.getAttribute('data-request-number'), status: await root.getAttribute('data-status-key'), lines,
        history: await page.locator('#history').count() ? await page.locator('#history').innerText() : '',
        acknowledgeAvailable: await page.locator('form[action$="/receive"] button[type="submit"]').count() === 1 };
    }
    return { ...result, evidenceSource: 'rendered-interface', screen: new URL(page.url()).pathname };
  } finally { await page.close(); }
}
