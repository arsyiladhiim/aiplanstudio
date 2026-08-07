import { defineConfig, devices } from "@playwright/test";

// E2E against the running Docker stack (nginx :4197 → BFF → Laravel).
// - globalSetup logs in once and saves session cookies → .auth/state.json
// - "login" project: tests the auth page with a FRESH (empty) storage state
// - "app" project: authenticated via storage state (avoids login throttle)
export default defineConfig({
  testDir: "./e2e",
  globalSetup: "./e2e/global-setup.ts",
  timeout: 60000,
  expect: { timeout: 15000 },
  fullyParallel: false,
  retries: 0,
  workers: 1,
  reporter: "line",
  use: {
    baseURL: process.env.E2E_BASE_URL || "http://localhost:4197",
    trace: "on-first-retry",
    screenshot: "only-on-failure",
  },
  projects: [
    {
      name: "login",
      testMatch: /auth\.spec\.ts/,
      use: { ...devices["Desktop Chrome"], storageState: undefined },
    },
    {
      name: "app",
      testMatch: /projects\.spec\.ts|wizard\.spec\.ts/,
      use: {
        ...devices["Desktop Chrome"],
        storageState: "./e2e/.auth/state.json",
      },
    },
  ],
});
