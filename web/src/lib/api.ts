// lib/api.ts — fetch wrapper with Sanctum SPA session auth.
// Auth via HttpOnly session cookie + CSRF. No tokens in JS.

const BASE = "";
const TIMEOUT_MS = 30_000;

let csrfPromise: Promise<void> | null = null;

/** Get CSRF token from cookie set by Laravel (XSRF-TOKEN is not HttpOnly) */
function getCsrfToken(): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : null;
}

/** Fetch CSRF cookie from Laravel (sets XSRF-TOKEN cookie via BFF) */
export async function fetchCsrfCookie(): Promise<void> {
  await fetch(`${BASE}/api/sanctum/csrf-cookie`, { credentials: "include" });
}

/** Ensure CSRF cookie is fetched (lazy, once per session) */
function ensureCsrf(): Promise<void> {
  if (!csrfPromise) {
    csrfPromise = fetchCsrfCookie().catch(() => {
      csrfPromise = null;
    });
  }
  return csrfPromise;
}

/** Build fetch headers with CSRF token for state-changing requests */
function csrfHeaders(method: string): HeadersInit {
  const h: Record<string, string> = { "Content-Type": "application/json" };
  if (method !== "GET") {
    const token = getCsrfToken();
    if (token) h["X-XSRF-TOKEN"] = token;
  }
  return h;
}

async function handleResponse<T>(res: Response): Promise<T> {
  if (res.status === 401) {
    if (typeof window !== "undefined") window.location.href = "/login";
    throw new Error("Sesi telah berakhir. Silakan login ulang.");
  }
  if (res.status === 419) {
    throw new RetryableCsrfError();
  }
  if (!res.ok) {
    let msg = "Terjadi kesalahan.";
    try {
      const body = await res.json();
      msg = body.message || body.error || msg;
    } catch {
      msg = `Error ${res.status}`;
    }
    throw new ApiError(msg, res.status);
  }
  if (res.status === 204) return undefined as T;
  return res.json();
}

class RetryableCsrfError extends Error {
  constructor() {
    super("CSRF token mismatch");
    this.name = "RetryableCsrfError";
  }
}

export class ApiError extends Error {
  status: number;
  constructor(message: string, status: number) {
    super(message);
    this.name = "ApiError";
    this.status = status;
  }
}

async function apiFetch<T>(
  method: string,
  path: string,
  body?: unknown,
): Promise<T> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);

  if (method !== "GET") {
    await ensureCsrf();
  }

  const doFetch = async (): Promise<T> => {
    const res = await fetch(`${BASE}/api${path}`, {
      method,
      headers: csrfHeaders(method),
      credentials: "include",
      signal: controller.signal,
      body: body ? JSON.stringify(body) : undefined,
    });
    return handleResponse<T>(res);
  };

  try {
    const result = await doFetch();
    clearTimeout(timer);
    return result;
  } catch (err) {
    clearTimeout(timer);
    if (err instanceof RetryableCsrfError) {
      csrfPromise = null;
      await ensureCsrf();
      return doFetch();
    }
    throw err;
  }
}

export async function apiGet<T>(path: string): Promise<T> {
  return apiFetch<T>("GET", path);
}

export async function apiPost<T>(path: string, body?: unknown): Promise<T> {
  return apiFetch<T>("POST", path, body);
}

export async function apiPatch<T>(path: string, body?: unknown): Promise<T> {
  return apiFetch<T>("PATCH", path, body);
}

export async function apiPut<T>(path: string, body?: unknown): Promise<T> {
  return apiFetch<T>("PUT", path, body);
}

export async function apiDelete(path: string): Promise<void> {
  await apiFetch<void>("DELETE", path);
}

export function createSSE(
  path: string,
  onEvent: (event: string, data: unknown) => void,
  onError?: (error: Event) => void,
): EventSource {
  const url = `${BASE}/api${path}`;
  const es = new EventSource(url);
  let receivedAnyEvent = false;
  let finished = false;

  es.onopen = () => {
    console.log("SSE connection opened:", url);
  };

  ["status", "token", "artifact"].forEach((eventType) => {
    es.addEventListener(eventType, (e: MessageEvent) => {
      if (finished) return;
      receivedAnyEvent = true;
      try {
        const data = JSON.parse(e.data);
        onEvent(eventType, data);
      } catch (err) {
        console.error("SSE parse error:", eventType, e.data, err);
      }
    });
  });

  es.addEventListener("done", (e: MessageEvent) => {
    if (finished) return;
    finished = true;
    receivedAnyEvent = true;
    try {
      const data = JSON.parse(e.data);
      onEvent("done", data);
    } catch (err) {
      console.error("SSE parse error: done", e.data, err);
    }
    es.close();
  });

  es.addEventListener("fail", (e: MessageEvent) => {
    if (finished) return;
    finished = true;
    receivedAnyEvent = true;
    try {
      const data = JSON.parse(e.data);
      onEvent("fail", data);
    } catch (err) {
      console.error("SSE parse error: fail", e.data, err);
    }
    es.close();
  });

  es.onerror = () => {
    if (finished) return;
    if (!receivedAnyEvent) {
      console.error("SSE connection error. readyState:", es.readyState);
      onError?.(new Event("error"));
    }
    // Do NOT close — let browser auto-reconnect for transient errors
  };

  return es;
}

/** POST-based SSE stream (alternative to EventSource, supports CSRF) */
export async function createSSEPost(
  path: string,
  body: unknown,
  onEvent: (event: string, data: unknown) => void,
  onError?: (error: Error) => void,
): Promise<AbortController> {
  const controller = new AbortController();
  let finished = false;

  await ensureCsrf();

  const res = await fetch(`${BASE}/api${path}`, {
    method: "POST",
    headers: csrfHeaders("POST"),
    credentials: "include",
    body: JSON.stringify(body),
    signal: controller.signal,
  });

  if (!res.ok) {
    let msg = "Stream gagal.";
    try {
      const b = await res.json();
      msg = b.message || msg;
    } catch {}
    const err = new Error(msg);
    onError?.(err);
    return controller;
  }

  const reader = res.body?.getReader();
  if (!reader) {
    onError?.(new Error("Stream tidak tersedia"));
    return controller;
  }

  const decoder = new TextDecoder();
  let buffer = "";

  const pump = async (): Promise<void> => {
    while (true) {
      const { done, value } = await reader.read();
      if (done || finished) break;
      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split("\n");
      buffer = lines.pop() || "";

      let eventName = "message";
      for (const line of lines) {
        if (line.startsWith("event: ")) {
          eventName = line.slice(7).trim();
          continue;
        }
        if (line.startsWith("data: ")) {
          const data = line.slice(6).trim();
          if (!data || data === "[DONE]") continue;
          try {
            const parsed = JSON.parse(data);
            const eventType = parsed.type || eventName;
            if (eventType === "done" || eventType === "fail") finished = true;
            onEvent(eventType, parsed);
          } catch {
            // skip unparseable lines
          }
          eventName = "message";
        }
      }
    }
    reader.releaseLock();
  };

  pump().catch((err) => {
    if (!finished) {
      finished = true;
      onError?.(err instanceof Error ? err : new Error(String(err)));
    }
  });

  return controller;
}

export type Target = "web" | "both";
export type User = {
  id: number;
  name: string;
  email: string;
  role: "admin" | "member";
  status?: "active" | "pending";
};
export type Project = {
  id: number;
  title: string;
  idea: string;
  target: Target;
  stack?: string;
  versions_count?: number;
  created_at: string;
  updated_at: string;
  progress?: number;
  stage_status?: Record<string, string>;
  latest_version_id?: number | null;
  is_favorite?: boolean;
};
export interface ErdData {
  nodes?: Array<{ id: string; label: string; fields?: string[] }>;
  edges?: Array<{ from: string; to: string; relation: string }>;
  api_contract?: Array<{
    method: string;
    path: string;
    description: string;
    auth: boolean;
  }>;
}

export interface PhaseData {
  key: string;
  title: string;
  tasks?: string[];
  prompt?: string;
  ac?: string[];
}

export type Version = {
  id: number;
  version_no: number;
  stage_status: Record<string, string>;
  pertanyaan?: string;
  answers?: Record<string, string>;
  pertanyaan_mobile?: string;
  mobile_answers?: Record<string, string>;
  analysis?: string;
  prd?: string;
  architecture?: string;
  erd?: ErdData;
  api_contract?: ErdData["api_contract"];
  phases?: PhaseData[];
  master_prompt?: string;
  standards?: string;
  agents?: string;
  tracking_token?: string;
  mobile_phases?: PhaseData[];
  mobile_master_prompt?: string;
  mobile_standards?: string;
  mobile_agents?: string;
  project?: Project;
  phaseProgress?: Array<{ id?: number; phase_key: string; done: boolean }>;
};

export type McqOption = {
  key: string;
  text: string;
  recommended: boolean;
  custom?: string;
};

export type McqQuestion = {
  id: string;
  question: string;
  options: McqOption[];
  recommendation_reason?: string;
};

export type McqData = {
  ambiguities: string[];
  questions: McqQuestion[];
};

export type McqAnswer = {
  selected: string;
  custom_text?: string;
};
export type Activity = {
  id: number;
  project_id: number;
  version_id?: number | null;
  action: string;
  description: string;
  metadata?: Record<string, unknown> | null;
  created_at: string;
  user: { id: number; name: string };
  project?: { id: number; title: string };
};
export type AiProvider = {
  base_url: string;
  model: string;
  api_key_masked: string;
};
export type Template = {
  id: number;
  name: string;
  target: Target;
  description: string | null;
  seed?: object;
  created_at: string;
  updated_at: string;
};
