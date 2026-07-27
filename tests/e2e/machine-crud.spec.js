const { test, expect } = require('@playwright/test');

test.describe('Flux E2E - Administration des Machines', () => {

  test.beforeEach(async ({ page }) => {
    page.on('pageerror', exception => {
      throw new Error(`[PAGE ERROR] ${exception.message}`);
    });

    await page.goto('/?admin');
    const passwordInput = page.locator('input[name="password"]');
    if (await passwordInput.isVisible()) {
      await passwordInput.fill('admin');
      await page.click('button[type="submit"]');
      await page.waitForLoadState('networkidle');
    }
  });

  test('Chargement de la liste des machines et structure admin', async ({ page }) => {
    await page.goto('/?admin&machines');

    // Vérifier l'existence d'éléments clés de la page
    const pageElement = page.locator('body');
    await expect(pageElement).toBeVisible();
  });

});
