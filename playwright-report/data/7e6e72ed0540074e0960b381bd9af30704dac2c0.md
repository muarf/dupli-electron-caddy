# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke.test.js >> L'application devrait se lancer et afficher la fenêtre principale
- Location: tests/e2e/smoke.test.js:5:1

# Error details

```
TimeoutError: electronApplication.waitForEvent: Timeout 60000ms exceeded while waiting for event "window"
```

# Test source

```ts
  1  | const { _electron: electron } = require('@playwright/test');
  2  | const { test, expect } = require('@playwright/test');
  3  | const path = require('path');
  4  | 
  5  | test('L\'application devrait se lancer et afficher la fenêtre principale', async () => {
  6  |   const electronApp = await electron.launch({ 
  7  |     executablePath: path.join(__dirname, '../../node_modules/.bin/electron'),
  8  |     args: [
  9  |         '.',
  10 |         '--no-sandbox',
  11 |         '--disable-gpu',
  12 |         '--disable-software-rasterizer',
  13 |         '--disable-dev-shm-usage',
  14 |         '--remote-debugging-port=9333'
  15 |     ],
  16 |     timeout: 60000,
  17 |     env: {
  18 |         ...process.env,
  19 |         NODE_ENV: 'test',
  20 |         ELECTRON_RUNNING_IN_TEST: 'true'
  21 |     }
  22 |   });
  23 | 
  24 |   electronApp.process().stdout.on('data', data => console.log(`[App Stdout] ${data}`));
  25 |   electronApp.process().stderr.on('data', data => console.log(`[App Stderr] ${data}`));
  26 | 
  27 |   console.log('Application lancée via Playwright (PID:', electronApp.process().pid, '), attente de la fenêtre...');
  28 |   
  29 |   // Utiliser waitForEvent qui est plus robuste que la boucle windows()
> 30 |   const window = await electronApp.waitForEvent('window', { timeout: 60000 }).catch(e => {
     |                                    ^ TimeoutError: electronApplication.waitForEvent: Timeout 60000ms exceeded while waiting for event "window"
  31 |     console.log('Timeout waitForEvent(\'window\'), tentative via windows()...');
  32 |     const windows = electronApp.windows();
  33 |     if (windows.length > 0) return windows[0];
  34 |     throw e;
  35 |   });
  36 | 
  37 |   console.log('Fenêtre détectée !');
  38 |   
  39 |   // Attendre un peu pour que le contenu se charge
  40 |   await window.waitForLoadState('domcontentloaded', { timeout: 60000 });
  41 |   console.log('DOM chargé !');
  42 | 
  43 |   // Vérifier le titre de l'application
  44 |   const title = await window.title();
  45 |   console.log('Titre de la fenêtre:', title);
  46 |   
  47 |   // On s'attend à ce que le titre contienne "Duplicator"
  48 |   expect(title).toMatch(/Duplicator/);
  49 | 
  50 |   // Vérifier qu'un élément de l'interface est présent (par exemple le header ou un logo)
  51 |   // On attend un peu que le contenu PHP soit servi
  52 |   await window.waitForTimeout(5000);
  53 | 
  54 |   // Faire une capture d'écran pour le debug
  55 |   await window.screenshot({ path: 'tests/e2e/screenshots/smoke-test.png' });
  56 | 
  57 |   await electronApp.close();
  58 | });
  59 | 
```