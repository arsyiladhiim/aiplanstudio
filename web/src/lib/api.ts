// lib/api.ts — fetch wrapper with Sanctum SPA session auth.
// Auth via HttpOnly session cookie + CSRF. No tokens in JS.
// Direct call ke Laravel API (no BFF) — see docs/25-bypass-bff.md.
// CP-13: CSRF token fetched via /api/csrf-token (raw session token in X-CSRF-TOKEN header).
// Browser cannot read XSRF-TOKEN cookie from api subdomain (host-only cookie), so we fetch the
// raw token from Laravel and send it via X-CSRF-TOKEN header. Laravel's CSRF middleware accepts
// the raw token in this header (see PreventRequestForgery::getTokenFromRequest — order:
// _token → X-CSRF-TOKEN raw → X-XSRF-TOKEN cookie decrypt).

const BASE = process.env.NEXT_PUBLIC_API_URL ?? ""
const TIMEOUT_MS = 30_000

let csrfToken: string | null = null
let csrfExpiresAt = 0
let csrfPromise: Promise<void> | null = null

/** Fetch CSRF token from Laravel. Returns raw session token for X-CSRF-TOKEN header. */
async function fetchCsrfToken(): Promise<void> {
  const res = await fetch(`${BASE}/api/csrf-token`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  })
  if (!res.ok) throw new Error(`CSRF endpoint returned ${res.status}`)
  const data = (await res.json()) as {
    token: string
    issued_at: number
    expires_at: number
    lifetime: number
  }
  csrfToken = data.token
  csrfExpiresAt = data.expires_at
}

/** Ensure CSRF token is fetched (lazy, once per session; refetched on 419 retry or expiry) */
function ensureCsrf(): Promise<void> {
  if (!csrfPromise) {
    csrfPromise = fetchCsrfToken().catch((err) => {
      csrfPromise = null
      throw err
    })
  }
  return csrfPromise
}

/** Check if cached token expired; refetch if so. Call before mutating request. */
async function ensureFreshCsrf(): Promise<void> {
  if (!csrfToken || Date.now() / 1000 >= csrfExpiresAt - 30) {
    csrfPromise = null
    await ensureCsrf()
  }
}

/** Build fetch headers with CSRF token for state-changing requests */
function csrfHeaders(method: string): HeadersInit {
  const h: Record<string, string> = { "Content-Type": "application/json" }
  if (method !== "GET" && csrfToken) h["X-CSRF-TOKEN"] = csrfToken
  h["X-Request-ID"] = generateRequestId()
  return h
}

function generateRequestId(): string {
  if (
    typeof crypto !== "undefined" &&
    typeof crypto.randomUUID === "function"
  ) {
    return crypto.randomUUID()
  }
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`
}

async function handleResponse<T>(res: Response, path?: string): Promise<T> {
  if (res.status === 401) {
    // Simpan context penting agar /login?resume=1 bisa melanjutkan tanpa state hilang.
    try {
      const url = new URL(window.location.href)
      const m = url.pathname.match(/^\/projects\/(\d+)/)
      if (m) {
        sessionStorage.setItem("wizard:lostProject", m[1])
      }
      const verFromQuery = url.searchParams.get("version")
      if (verFromQuery) {
        sessionStorage.setItem("wizard:lostVersion", verFromQuery)
      }
    } catch {}
    // Forced logout from module-level fetcher: full reload to clear any cached client state.
    // eslint-disable-next-line @next/next/no-location-assign-relative-destination
    if (typeof window !== "undefined") window.location.href = "/login?resume=1"
    throw new Error("Sesi telah berakhir. Silakan login ulang.")
  }
  if (res.status === 419) {
    // CP-16.M4: 419 from auth endpoints means session rotated (login/logout changed token).
    // Retrying with new token will fail again — surface the error instead.
    const isAuthEndpoint = path
      ? /^\/(login|register|logout|forgot-password|reset-password)(\/|$|\?)/.test(
          path,
        )
      : false
    if (isAuthEndpoint) {
      throw new ApiError(
        "Sesi berakhir. Silakan muat ulang halaman dan coba lagi.",
        419,
      )
    }
    throw new RetryableCsrfError()
  }
  if (!res.ok) {
    let msg = "Terjadi kesalahan."
    try {
      const body = await res.json()
      msg = body.message || body.error || msg
    } catch {
      msg = `Error ${res.status}`
    }
    throw new ApiError(msg, res.status)
  }
  if (res.status === 204) return undefined as T
  return res.json()
}

class RetryableCsrfError extends Error {
  constructor() {
    super("CSRF token mismatch")
    this.name = "RetryableCsrfError"
  }
}

export class ApiError extends Error {
  status: number
  constructor(message: string, status: number) {
    super(message)
    this.name = "ApiError"
    this.status = status
  }
}

async function apiFetch<T>(
  method: string,
  path: string,
  body?: unknown
): Promise<T> {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS)

  if (method !== "GET") {
    await ensureFreshCsrf()
  }

  const doFetch = async (): Promise<T> => {
    const res = await fetch(`${BASE}/api${path}`, {
      method,
      headers: csrfHeaders(method),
      credentials: "include",
      signal: controller.signal,
      body: body ? JSON.stringify(body) : undefined,
    })
    return handleResponse<T>(res, path)
  }

  try {
    const result = await doFetch()
    clearTimeout(timer)
    return result
  } catch (err) {
    clearTimeout(timer)
    if (err instanceof RetryableCsrfError) {
      csrfToken = null
      csrfPromise = null
      await ensureCsrf()
      const timer2 = setTimeout(() => controller.abort(), TIMEOUT_MS)
      try {
        return await doFetch()
      } finally {
        clearTimeout(timer2)
      }
    }
    throw err
  }
}

export async function apiGet<T>(path: string): Promise<T> {
  return apiFetch<T>("GET", path)
}

export async function apiPost<T>(path: string, body?: unknown): Promise<T> {
  return apiFetch<T>("POST", path, body)
}

export async function apiPatch<T>(path: string, body?: unknown): Promise<T> {
  return apiFetch<T>("PATCH", path, body)
}

export async function apiDelete(path: string): Promise<void> {
  await apiFetch<void>("DELETE", path)
}

export interface AutoTrackingToken {
  id: number
  name: string
  token: string | null
  secret: string | null
  existing: boolean
  message: string
}

export async function apiSetupAutoTracking(
  projectId: number,
  versionId: number
): Promise<AutoTrackingToken> {
  return apiPost<AutoTrackingToken>(
    `/projects/${projectId}/versions/${versionId}/tokens/auto-tracking`
  )
}

export function createSSE(
  path: string,
  onEvent: (event: string, data: unknown) => void,
  onError?: (error: Event) => void
): EventSource {
  const url = `${BASE}/api${path}`
  const es = new EventSource(url)
  let receivedAnyEvent = false
  let finished = false
  let closed = false

  es.onopen = () => {
    console.log("SSE connection opened:", url)
  }

  ;["status", "token", "artifact"].forEach((eventType) => {
    es.addEventListener(eventType, (e: MessageEvent) => {
      if (finished) return
      receivedAnyEvent = true
      try {
        const data = JSON.parse(e.data)
        onEvent(eventType, data)
      } catch (err) {
        console.error("SSE parse error:", eventType, e.data, err)
      }
    })
  })

  es.addEventListener("done", (e: MessageEvent) => {
    if (finished) return
    finished = true
    receivedAnyEvent = true
    try {
      const data = JSON.parse(e.data)
      onEvent("done", data)
    } catch (err) {
      console.error("SSE parse error: done", e.data, err)
    }
    es.close()
  })

  es.addEventListener("fail", (e: MessageEvent) => {
    if (finished) return
    finished = true
    receivedAnyEvent = true
    try {
      const data = JSON.parse(e.data)
      onEvent("fail", data)
    } catch (err) {
      console.error("SSE parse error: fail", e.data, err)
    }
    es.close()
  })

  es.onerror = () => {
    if (finished || closed) return
    if (!receivedAnyEvent) {
      console.error("SSE connection error. readyState:", es.readyState)
      onError?.(new Event("error"))
    }
    if (es.readyState === EventSource.CLOSED) {
      console.error(
        "SSE connection closed permanently. Attempting manual reconnect..."
      )
      es.close()
      closed = true
      setTimeout(() => {
        if (finished) return
        const es2 = createSSE(path, onEvent, onError)
        es2.addEventListener("done", () => {
          es2.close()
        })
        es2.addEventListener("fail", () => {
          es2.close()
        })
      }, 3000)
    }
  }

  // Patch close: set closed=true agar onerror tidak reconnect setelah manual close.
  const origClose = es.close.bind(es)
  es.close = () => {
    closed = true
    origClose()
  }

  return es
}

/** POST-based SSE stream (alternative to EventSource, supports CSRF) */
export async function createSSEPost(
  path: string,
  body: unknown,
  onEvent: (event: string, data: unknown) => void,
  onError?: (error: Error) => void
): Promise<AbortController> {
  const controller = new AbortController()
  let finished = false

  await ensureFreshCsrf()

  // Retry-once untuk transient network error (TypeError fetch) sebelum menyerah.
  let res: Response
  try {
    res = await fetch(`${BASE}/api${path}`, {
      method: "POST",
      headers: csrfHeaders("POST"),
      credentials: "include",
      body: JSON.stringify(body),
      signal: controller.signal,
    })
  } catch (err) {
    if (err instanceof TypeError && !controller.signal.aborted) {
      try {
        res = await fetch(`${BASE}/api${path}`, {
          method: "POST",
          headers: csrfHeaders("POST"),
          credentials: "include",
          body: JSON.stringify(body),
          signal: controller.signal,
        })
      } catch (err2) {
        const e = err2 instanceof Error ? err2 : new Error(String(err2))
        onError?.(e)
        return controller
      }
    } else if (controller.signal.aborted) {
      return controller
    } else {
      const e = err instanceof Error ? err : new Error(String(err))
      onError?.(e)
      return controller
    }
  }

  if (!res.ok) {
    let msg = "Stream gagal."
    try {
      const b = await res.json()
      msg = b.message || msg
    } catch {}
    const err = new Error(msg)
    onError?.(err)
    return controller
  }

  const reader = res.body?.getReader()
  if (!reader) {
    onError?.(new Error("Stream tidak tersedia"))
    return controller
  }

  const decoder = new TextDecoder()
  let buffer = ""

  const pump = async (): Promise<void> => {
    while (true) {
      const { done, value } = await reader.read()
      if (done || finished) break
      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split("\n")
      buffer = lines.pop() || ""

      let eventName = "message"
      for (const line of lines) {
        if (line.startsWith("event: ")) {
          eventName = line.slice(7).trim()
          continue
        }
        if (line.startsWith("data: ")) {
          const data = line.slice(6).trim()
          if (!data || data === "[DONE]") continue
          try {
            const parsed = JSON.parse(data)
            const eventType = parsed.type || eventName
            if (eventType === "done" || eventType === "fail") finished = true
            onEvent(eventType, parsed)
          } catch {
            // skip unparseable lines
          }
          eventName = "message"
        }
      }
    }
    reader.releaseLock()
  }

  pump().catch((err) => {
    if (!finished) {
      finished = true
      onError?.(err instanceof Error ? err : new Error(String(err)))
    }
  })

  return controller
}

export type Target = "web" | "both"
export type User = {
  id: number
  name: string
  email: string
  role: "admin" | "member"
  status?: "active" | "pending"
  accent_color?: string | null
}
export type Project = {
  id: number
  title: string
  idea: string
  target: Target
  stack?: string
  versions_count?: number
  created_at: string
  updated_at: string
  progress?: number
  stage_status?: Record<string, string>
  latest_version_id?: number | null
  is_favorite?: boolean
  is_pinned?: boolean
  archived_at?: string | null
}
export interface ErdData {
  nodes?: Array<{ id: string; label: string; fields?: string[] }>
  edges?: Array<{ from: string; to: string; relation: string }>
  api_contract?: Array<{
    method: string
    path: string
    description: string
    auth: boolean
  }>
}

export interface PhaseData {
  key: string
  title: string
  tasks?: string[]
  prompt?: string
  ac?: string[]
  halaman?: SubItem[]
  menu?: SubItem[]
  fitur?: SubItem[]
  flow?: SubItem[]
  api?: SubItem[]
}

export interface SubItem {
  key: string
  title: string
  desc?: string
}

export interface TaskProgressData {
  id: number
  phase_progress_id: number
  task_key: string
  task_type: string
  title: string
  status: "pending" | "running" | "done" | "error"
  output?: string | null
  started_at?: string | null
  finished_at?: string | null
}

export async function toggleTask(
  versionId: number,
  taskKey: string,
  done: boolean,
  phaseKey?: string
): Promise<TaskProgressData> {
  return apiPatch<TaskProgressData>(`/versions/${versionId}/tasks/${taskKey}`, {
    done,
    phase_key: phaseKey,
  })
}

export type Version = {
  id: number
  version_no: number
  source_version_id?: number | null
  baseline_notes?: string | null
  stage_status: Record<string, string>
  stage_tokens?: Record<string, number>
  pertanyaan?: string
  answers?: Record<string, string>
  pertanyaan_mobile?: string
  mobile_answers?: Record<string, string>
  analysis?: string
  prd?: string
  architecture?: string
  erd?: ErdData
  api_contract?: ErdData["api_contract"]
  phases?: PhaseData[]
  master_prompt?: string
  standards?: string
  agents?: string
  tracking_token?: string
  mobile_phases?: PhaseData[]
  mobile_master_prompt?: string
  mobile_standards?: string
  mobile_agents?: string
  project?: Project
  phase_progress?: Array<{
    id?: number
    phase_key: string
    done: boolean
    status?: "pending" | "running" | "done" | "error"
    output?: string | null
    started_at?: string | null
    finished_at?: string | null
    tasks?: TaskProgressData[]
  }>
}

export type McqOption = {
  key: string
  text: string
  recommended: boolean
  custom?: string
}

export type McqQuestion = {
  id: string
  question: string
  options: McqOption[]
  recommendation_reason?: string
}

export type McqData = {
  ambiguities: string[]
  questions: McqQuestion[]
}

export type McqAnswer = {
  selected: string
  custom_text?: string
}
export type Activity = {
  id: number
  project_id: number
  version_id?: number | null
  action: string
  description: string
  metadata?: Record<string, unknown> | null
  created_at: string
  user: { id: number; name: string }
  project?: { id: number; title: string }
}
export type AiProvider = {
  base_url: string
  model: string
  api_key_masked: string
}
export type Template = {
  id: number
  name: string
  target: Target
  description: string | null
  seed?: object
  created_at: string
  updated_at: string
}

export type AppVersion = {
  version: string
  name: string
}

let _cachedVersion: AppVersion | null = null

export async function fetchAppVersion(): Promise<AppVersion | null> {
  if (_cachedVersion) return _cachedVersion
  try {
    const v = await apiGet<AppVersion>("/version")
    _cachedVersion = v
    return v
  } catch {
    return null
  }
}
