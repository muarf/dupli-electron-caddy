// @ts-check
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  // Playwright ne ramasse QUE les .spec.js et smoke.test.js
  // Les autres .test.js dans e2e/ appartiennent à Jest
  testMatch: ['**/*.spec.js', '**/smoke.test.js'],
  timeout: 120 * 1000,
  expect: {
    timeout: 10000,
  },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1, // Electron tests should run serially
  reporter: 'html',
  use: {
    headless: true,
    trace: 'on-first-retry',
  },
});
