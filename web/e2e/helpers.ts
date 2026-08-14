import { Page } from "@playwright/test";

export const ADMIN_EMAIL = process.env.E2E_ADMIN_EMAIL || "";
export const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || "";

/** Check if the current browser session is authenticated (session cookie → /api/user). */
async function isAuthed(page: Page): Promise<boolean> {
  return page.evaluate(async () => {
    const r = await fetch("/api/user", { credentials: "include" });
    return r.ok;
  });
}

/** Log in through the real UI (only used as a fallback to avoid login throttle). */
async function loginViaUi(page: Page) {
  await page.goto("/login");
  await page.fill('input[name="email"]', ADMIN_EMAIL);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await page.click('[data-testid="login-submit"]');
  await page.waitForURL("**/dashboard", { timeout: 15000 });
}

/**
 * Ensure the page is authenticated. Tests in the "app" project run with the
 * storageState saved by global-setup, so this only performs a UI login as a
 * fallback — never on every test (avoids the login throttle limit).
 */
export async function ensureAuthed(page: Page) {
  await page.goto("/");
  const authed = await isAuthed(page);
  if (authed) return;
  await loginViaUi(page);
}

/** Get the id of the project created by `title` via the authenticated API. */
export async function projectIdByTitle(
  page: Page,
  title: string,
): Promise<number | null> {
  const id = await page.evaluate(async (t) => {
    const r = await fetch("/api/projects", { credentials: "include" });
    if (!r.ok) return null;
    const j = await r.json();
    const p = j.data.find((x: { title: string }) => x.title === t);
    return p ? p.id : null;
  }, title);
  return id;
}
