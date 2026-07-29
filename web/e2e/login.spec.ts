import { test, expect } from '@playwright/test';

test.describe('Login page', () => {
  test('should render login form', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
    await expect(page.getByRole('button', { name: /masuk|login|sign/i })).toBeVisible();
  });
});
