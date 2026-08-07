import { mkdirSync } from "node:fs";
import { request as pwRequest } from "@playwright/test";

// Logs in once via the real BFF stack and saves session cookies so all
// project/wizard tests share one authenticated state (avoids login throttle).
const baseURL = process.env.E2E_BASE_URL || "http://localhost:4197";
const adminEmail = process.env.E2E_ADMIN_EMAIL || "admin@aistack.dev";
const adminPassword = process.env.E2E_ADMIN_PASSWORD || "password123";
const statePath = "./e2e/.auth/state.json";

export default async function globalSetup() {
  mkdirSync("./e2e/.auth", { recursive: true });

  const context = await pwRequest.newContext({
    baseURL,
    // ignore TLS / hostname checks for this internal e2e
  });

  // 1. CSRF cookie
  await context.get("/api/sanctum/csrf-cookie");
  // 2. Login — reads cookies captured above
  const res = await context.post("/api/login", {
    data: { email: adminEmail, password: adminPassword },
  });
  if (!res.ok()) {
    throw new Error(
      `Global setup login failed (${res.status()}): ${await res.text()}`,
    );
  }

  // Save storage state (cookies incl. session)
  await context.storageState({ path: statePath });
  await context.dispose();
  console.log(`[global-setup] authenticated as ${adminEmail} → ${statePath}`);
}
