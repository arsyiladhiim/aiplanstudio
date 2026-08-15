import { mkdirSync } from "node:fs"
import { request as pwRequest } from "@playwright/test"

// Logs in once via Laravel API directly (Docker compose stack, port 8000) and saves session cookies
// so all project/wizard tests share one authenticated state (avoids login throttle).
// CP-13: direct routing — Next.js has no /api/login proxy. Hit Laravel nginx_api directly.
const apiBaseURL = process.env.E2E_API_BASE_URL || "http://localhost:8000"
const adminEmail = process.env.E2E_ADMIN_EMAIL || ""
const adminPassword = process.env.E2E_ADMIN_PASSWORD || ""
const statePath = "./e2e/.auth/state.json"

export default async function globalSetup() {
  mkdirSync("./e2e/.auth", { recursive: true })

  const context = await pwRequest.newContext({
    baseURL: apiBaseURL,
  })

  // 1. CSRF token (CP-13 custom endpoint — raw session token)
  const csrfRes = await context.get("/api/csrf-token")
  const { token } = (await csrfRes.json()) as { token: string }
  // 2. Login — sends X-CSRF-TOKEN header (raw token, accepted by Laravel CSRF middleware)
  const res = await context.post("/api/login", {
    headers: { "X-CSRF-TOKEN": token, Accept: "application/json" },
    data: { email: adminEmail, password: adminPassword },
  })
  if (!res.ok()) {
    throw new Error(
      `Global setup login failed (${res.status()}): ${await res.text()}`
    )
  }

  // Save storage state (cookies incl. session)
  await context.storageState({ path: statePath })
  await context.dispose()
  console.log(`[global-setup] authenticated as ${adminEmail} → ${statePath}`)
}
