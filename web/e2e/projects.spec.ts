import { test, expect } from "@playwright/test";
import { ensureAuthed } from "./helpers";

const title = `E2E Projects ${Date.now()}`;

test.beforeAll(async ({ browser }) => {
  // Create a fresh project via the authenticated API so list assertions exist.
  // Reuse the storageState from global-setup (no extra UI login → no throttle).
  const context = await browser.newContext({
    storageState: "./e2e/.auth/state.json",
  });
  const baseURL = process.env.E2E_BASE_URL || "http://localhost:4197";
  const res = await context.request.post(`${baseURL}/api/projects`, {
    data: { title, idea: "Project untuk E2E test projects.", target: "web" },
  });
  await context.close();
  if (!res.ok()) {
    throw new Error(
      `Failed to create project in beforeAll (${res.status()}): ${await res.text()}`,
    );
  }
});

test.describe("Projects", () => {
  test("projects list shows the created project card", async ({ page }) => {
    await ensureAuthed(page);
    await page.goto("/projects");
    await expect(page.getByText(title)).toBeVisible({ timeout: 15000 });
  });

  test("search input filters project list", async ({ page }) => {
    await ensureAuthed(page);
    await page.goto("/projects");
    await expect(page.getByText(title)).toBeVisible({ timeout: 15000 });
    await page
      .getByPlaceholder("Cari project...")
      .fill("zzz-tidak-ada-project-ini");
    await expect(page.locator("text=Belum ada project")).toBeVisible({
      timeout: 10000,
    });
  });

  test("open project detail from list", async ({ page }) => {
    await ensureAuthed(page);
    await page.goto("/projects");
    await page.getByText(title).first().click();
    await page.waitForURL(/\/projects\/\d+/, { timeout: 10000 });
    // version selector + stage tabs render on detail (may be empty until data loads)
    await expect(page.locator('[data-testid^="version-"]').first()).toBeVisible(
      { timeout: 15000 },
    );
    await expect(page.getByRole("button", { name: "PRD" })).toBeVisible();
  });
});
