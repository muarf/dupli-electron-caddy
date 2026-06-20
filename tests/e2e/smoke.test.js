const { test, expect } = require('@playwright/test');

test('Le serveur PHP devrait répondre', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/', { timeout: 15000 });
  const title = await page.title();
  expect(title).toMatch(/Duplicator|Dupli|Connexion/);
});
