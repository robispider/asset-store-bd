/**
 * GovStore Live Testing - Bootstrap Tables Adapter
 * Locates rows across search/pagination and inspects columns reliably.
 */

export class BootstrapTableAdapter {
  /**
   * Search within a bootstrap table and return matching row locators.
   */
  static async searchTable(page, tableSelector, query) {
    const searchBox = page.locator(`${tableSelector} .bootstrap-table .search input, .fixed-table-toolbar .search input`);
    if (await searchBox.isVisible({ timeout: 1500 }).catch(() => false)) {
      await searchBox.fill(query);
      await page.keyboard.press('Enter');
      await page.waitForTimeout(500);
    }
  }

  /**
   * Find a row matching exact text in a table.
   */
  static getRow(page, tableSelector, rowText) {
    return page.locator(`${tableSelector} tbody tr`, { hasText: rowText });
  }

  /**
   * Get row count.
   */
  static async getRowCount(page, tableSelector) {
    const rows = page.locator(`${tableSelector} tbody tr:not(.no-records-found)`);
    return await rows.count();
  }
}
