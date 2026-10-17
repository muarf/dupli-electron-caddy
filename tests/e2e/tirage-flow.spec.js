const { test, expect } = require('@playwright/test');

test.describe('Flux E2E - Calculateur de Tirages Multimachines', () => {

  test.beforeEach(async ({ page }) => {
    page.on('pageerror', exception => {
      throw new Error(`[PAGE ERROR] ${exception.message}`);
    });
  });

  test('Affichage du calculateur et validation de la saisie des compteurs', async ({ page }) => {
    await page.goto('/?tirage_multimachines');

    // Vérifier la présence du champ contact
    const contactInput = page.locator('#contact');
    await expect(contactInput).toBeVisible();

    // Saisir un nom de contact
    await contactInput.fill('Client Test E2E');
  });

});
