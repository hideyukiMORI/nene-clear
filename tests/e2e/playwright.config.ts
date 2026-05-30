import { defineConfig, devices } from '@playwright/test'

/**
 * NeNe Clear — Admin UI E2E suite.
 *
 * The SPA is built and served by `vite preview`. All API calls (/admin/*,
 * application/json) are intercepted via page.route() — no real backend.
 * Page navigation (document requests under /admin/*) falls through to the
 * preview server's SPA fallback.
 */
export default defineConfig({
  testDir: './specs',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
  use: {
    baseURL: 'http://localhost:4174',
    storageState: { cookies: [], origins: [] },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    actionTimeout: 10_000,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: 'npm run build && npm run preview -- --port 4174 --strictPort',
    cwd: '../../frontend',
    url: 'http://localhost:4174',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
})
