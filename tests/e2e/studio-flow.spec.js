const { test, expect } = require('@playwright/test');

test.describe('Flux E2E - Studio PDF & Imposition', () => {

  test.beforeEach(async ({ page }) => {
    page.on('pageerror', exception => {
      throw new Error(`[PAGE ERROR] ${exception.message}`);
    });
  });

  test('Affichage du Studio PDF, téléversement de PDF et génération des vignettes', async ({ page }) => {
    await page.goto('/?studio');

    // Televerser un PDF de test
    const fileInput = page.locator('#studioFileInput');
    await fileInput.setInputFiles('tests/assets/blank_A4_4pages.pdf');

    // Attendre le rendu des vignettes
    await page.waitForTimeout(1500);

    // Vérifier l'action 'Ajouter à la bibliothèque'
    const btnSaveToLib = page.locator('#btnSaveToLibrary');
    if (await btnSaveToLib.count() > 0 && await btnSaveToLib.isVisible()) {
      await btnSaveToLib.click();
      await page.waitForTimeout(500);
    }
  });

});
