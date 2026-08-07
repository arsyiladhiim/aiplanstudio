import { test, expect } from "@playwright/test";
import { ADMIN_EMAIL, ADMIN_PASSWORD } from "./helpers";

test.describe("Auth flow", () => {
  test("render login form", async ({ page }) => {
    await page.goto("/login");
    await expect(page.locator('[data-testid="login-form"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });

  test("login with wrong password shows error", async ({ page }) => {
    await page.goto("/login");
    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', "wrong-password-123");
    await page.click('[data-testid="login-submit"]');
    await expect(page.locator("text=Kredensial tidak cocok")).toBeVisible({
      timeout: 15000,
    });
  });

  test("login success redirects to dashboard", async ({ page }) => {
    await page.goto("/login");
    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', ADMIN_PASSWORD);
    await page.click('[data-testid="login-submit"]');
    await page.waitForURL("**/dashboard", { timeout: 15000 });
    await expect(page.locator('[data-testid="nav-projects"]')).toBeVisible();
  });

  test("protected route redirects unauthenticated to login (proxy.ts)", async ({
    page,
  }) => {
    await page.goto("/projects");
    await page.waitForURL("**/login**", { timeout: 10000 });
    const url = new URL(page.url());
    expect(url.searchParams.get("redirect")).toBe("/projects");
  });
});
