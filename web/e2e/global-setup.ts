import { mkdirSync } from "node:fs"
import { request as pwRequest } from "@playwright/test"

// Logs in once via Next.js frontend (Docker compose stack, port 3000) and saves session cookies
// so all project/wizard tests share one authenticated state (avoids login throttle).
const baseURL = process.env.E2E_BASE_URL || "http://localhost:3000"
const adminEmail = process.env.E2E_ADMIN_EMAIL || ""
const adminPassword = process.env.E2E_ADMIN_PASSWORD || ""
const statePath = "./e2e/.auth/state.json"

export default async function globalSetup() {
  mkdirSync("./e2e/.auth", { recursive: true })

  const context = await pwRequest.newContext({
    baseURL,
    // ignore TLS / hostname checks for this internal e2e
  })

  // 1. CSRF cookie
  await context.get("/api/sanctum/csrf-cookie")
  // 2. Login — reads cookies captured above
  const res = await context.post("/api/login", {
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
