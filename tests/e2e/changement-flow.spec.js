const { test, expect } = require('@playwright/test');

test.describe('Flux E2E - Changement de Consommables', () => {

  test.beforeEach(async ({ page }) => {
    page.on('pageerror', exception => {
      throw new Error(`[PAGE ERROR] ${exception.message}`);
    });
  });

  test('Affichage de la page de changement et sélection d une machine', async ({ page }) => {
    await page.goto('/?changement');

    // Vérifier la présence du formulaire
    const selectMachine = page.locator('#machine, select[name="machine"]');
    await expect(selectMachine).toBeVisible();
  });

});
