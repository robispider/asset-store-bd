/**
 * GovStore Live Testing - Select2 Adapter
 * Handles interacting with Select2 dropdowns (regular and AJAX).
 */

export class Select2Adapter {
  /**
   * Select a value in a Select2 container.
   */
  static async selectOption(page, select2ContainerSelector, searchText) {
    // Click the Select2 display container to open dropdown
    const container = page.locator(select2ContainerSelector);
    await container.click();

    // Type into the Select2 search input
    const searchInput = page.locator('.select2-container--open input.select2-search__field');
    if (await searchInput.isVisible({ timeout: 1000 }).catch(() => false)) {
      await searchInput.fill(searchText);
      // Wait for AJAX results if any
      await page.waitForTimeout(300);
    }

    // Select the first matching result item
    const option = page.locator('.select2-results__option', { hasText: searchText }).first();
    await option.click();
  }
}
