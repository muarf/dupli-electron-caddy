// @ts-check
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  // Playwright ne ramasse QUE les .spec.js et smoke.test.js
  // Les autres .test.js dans e2e/ appartiennent à Jest
  testMatch: ['**/*.spec.js', '**/smoke.test.js'],
  timeout: 60000,
  use: {
    headless: true,
  },
  retries: 0,
});
