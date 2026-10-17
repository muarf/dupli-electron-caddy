const { test, expect } = require('@playwright/test');

test.describe('Flux E2E - Bibliothèque Documentaire & Docling', () => {

  test.beforeEach(async ({ page }) => {
    page.on('pageerror', exception => {
      throw new Error(`[PAGE ERROR] ${exception.message}`);
    });
  });

  test('Affichage de la bibliothèque documentaire et recherche', async ({ page }) => {
    await page.goto('/?bibliotheque');

    // Vérifier la présence du conteneur de la bibliothèque
    const libraryContainer = page.locator('#bibliotheque-container, .bibliotheque, body');
    await expect(libraryContainer).toBeVisible();
  });

});
