const { _electron: electron } = require('@playwright/test');
const { test, expect } = require('@playwright/test');
const path = require('path');

test('L\'application devrait se lancer et afficher la fenêtre principale', async () => {
  const electronApp = await electron.launch({ 
    executablePath: path.join(__dirname, '../../node_modules/.bin/electron'),
    args: [
        '.',
        '--no-sandbox',
        '--disable-gpu',
        '--disable-software-rasterizer',
        '--disable-dev-shm-usage',
        '--remote-debugging-port=9333'
    ],
    timeout: 60000,
    env: {
        ...process.env,
        NODE_ENV: 'test',
        ELECTRON_RUNNING_IN_TEST: 'true'
    }
  });

  electronApp.process().stdout.on('data', data => console.log(`[App Stdout] ${data}`));
  electronApp.process().stderr.on('data', data => console.log(`[App Stderr] ${data}`));

  console.log('Application lancée via Playwright (PID:', electronApp.process().pid, '), attente de la fenêtre...');
  
  // Utiliser waitForEvent qui est plus robuste que la boucle windows()
  const window = await electronApp.waitForEvent('window', { timeout: 60000 }).catch(e => {
    console.log('Timeout waitForEvent(\'window\'), tentative via windows()...');
    const windows = electronApp.windows();
    if (windows.length > 0) return windows[0];
    throw e;
  });

  console.log('Fenêtre détectée !');
  
  // Attendre un peu pour que le contenu se charge
  await window.waitForLoadState('domcontentloaded', { timeout: 60000 });
  console.log('DOM chargé !');

  // Vérifier le titre de l'application
  const title = await window.title();
  console.log('Titre de la fenêtre:', title);
  
  // On s'attend à ce que le titre contienne "Duplicator"
  expect(title).toMatch(/Duplicator/);

  // Vérifier qu'un élément de l'interface est présent (par exemple le header ou un logo)
  // On attend un peu que le contenu PHP soit servi
  await window.waitForTimeout(5000);

  // Faire une capture d'écran pour le debug
  await window.screenshot({ path: 'tests/e2e/screenshots/smoke-test.png' });

  await electronApp.close();
});
