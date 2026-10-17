const { test, expect } = require('@playwright/test');

test.describe('Non-régression Console & Chargement de toutes les pages', () => {

  const pagesToTest = [
    { name: 'Dashboard Admin', url: '/?admin' },
    { name: 'Gestion Machines', url: '/?admin&machines' },
    { name: 'Gestion Imprimantes', url: '/?admin&imprimantes' },
    { name: 'Bibliothèque IA (Docling)', url: '/?admin&bibliotheque_ia' },
    { name: 'Gestion des Prix', url: '/?admin&prix' },
    { name: 'Historique des Changements', url: '/?admin&changes' },
    { name: 'Gestion des Tirages', url: '/?admin&tirages' },
    { name: 'Statistiques', url: '/?admin&stats' },
    { name: 'Bases de données', url: '/?admin&bdd' },
    { name: 'Gestion des News', url: '/?admin&news' },
    { name: 'Aide Machines Admin', url: '/?admin&aide' },
    { name: 'Gestion des Traductions', url: '/?admin_translations' },
    { name: 'Calculateur Multimachines', url: '/?tirage_multimachines' },
    { name: 'Studio PDF', url: '/?studio' },
    { name: 'Bibliothèque & Chat RAG', url: '/?bibliotheque' },
    { name: 'Changement Consommables', url: '/?changement' },
    { name: 'Auto-tirage', url: '/?auto_tirage' }
  ];

  test.beforeEach(async ({ page }) => {
    // 1. Intercepter TOUTES les erreurs JS non capturées
    page.on('pageerror', exception => {
      throw new Error(`[PAGE ERROR] Erreur JS capturée dans la console : ${exception.message}\nStack: ${exception.stack}`);
    });

    // 2. Connexion Admin
    await page.goto('/?admin');
    const passwordInput = page.locator('input[name="password"]');
    if (await passwordInput.isVisible()) {
      await passwordInput.fill('admin');
      await page.click('button[type="submit"]');
      await page.waitForLoadState('networkidle');
    }
  });

  for (const pageConfig of pagesToTest) {
    test(`La page "${pageConfig.name}" (${pageConfig.url}) doit charger sans aucune erreur console JS`, async ({ page }) => {
      const response = await page.goto(pageConfig.url);
      
      // Vérifier le statut HTTP
      expect(response.status()).toBeLessThan(400);

      // Laisser le JS exécuter les scripts initiaux (DOMContentLoaded / window.onload)
      await page.waitForTimeout(1000);

      // Vérifier la présence du body
      const bodyCount = await page.locator('body').count();
      expect(bodyCount).toBeGreaterThan(0);
    });
  }

});
