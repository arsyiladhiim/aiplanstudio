import { test, expect } from "@playwright/test";
import { ensureAuthed } from "./helpers";

test.describe("Wizard (Buat Plan)", () => {
  test("renders form and disables submit until filled", async ({ page }) => {
    await ensureAuthed(page);
    await page.goto("/new");
    await expect(page.locator('[data-testid="title-input"]')).toBeVisible();
    await expect(page.locator('[data-testid="idea-input"]')).toBeVisible();
    const submit = page.locator('[data-testid="start-plan"]');
    await expect(submit).toBeDisabled();
    await page.fill('[data-testid="title-input"]', "E2E Test Plan");
    await page.fill(
      '[data-testid="idea-input"]',
      "Aplikasi untuk mengelola jadwal kegiatan harian.",
    );
    await expect(submit).toBeEnabled();
  });

  test("target selection updates selected state", async ({ page }) => {
    await ensureAuthed(page);
    await page.goto("/new");
    const both = page.locator('[data-testid="target-both"]');
    await both.click();
    // Selected target shows the brand text color (active styling)
    await expect(both).toHaveClass(/var\(--color-brand\)/);
  });

  test("submit creates project and shows pipeline screen", async ({ page }) => {
    await ensureAuthed(page);
    await page.goto("/new");
    const title = `E2E ${Date.now()}`;
    await page.fill('[data-testid="title-input"]', title);
    await page.fill(
      '[data-testid="idea-input"]',
      "Aplikasi catatan sederhana dengan tag dan pencarian.",
    );
    await page.click('[data-testid="start-plan"]');
    // Pipeline screen renders (stage tracker). Do NOT wait for full AI run (flaky quota).
    await expect(page.locator('[data-testid^="stage-"]').first()).toBeVisible({
      timeout: 20000,
    });
  });
});
