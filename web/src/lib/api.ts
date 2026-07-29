// lib/api.ts — fetch wrapper with Sanctum SPA session auth.
// Auth via HttpOnly session cookie + CSRF. No tokens in JS.

const BASE = '';

/** Get CSRF token from cookie set by Laravel (XSRF-TOKEN is not HttpOnly) */
function getCsrfToken(): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : null;
}

/** Fetch CSRF cookie from Laravel (sets XSRF-TOKEN cookie via BFF) */
export async function fetchCsrfCookie(): Promise<void> {
  await fetch(`${BASE}/api/sanctum/csrf-cookie`, { credentials: 'include' });
}

/** Build fetch headers with CSRF token for state-changing requests */
function csrfHeaders(method: string): HeadersInit {
  const h: Record<string, string> = { 'Content-Type': 'application/json' };
  if (method !== 'GET') {
    const token = getCsrfToken();
    if (token) h['X-XSRF-TOKEN'] = token;
  }
  return h;
}

async function handleResponse<T>(res: Response): Promise<T> {
  if (res.status === 401) {
    if (typeof window !== 'undefined') window.location.href = '/login';
    throw new Error('Sesi telah berakhir. Silakan login ulang.');
  }
  if (!res.ok) {
    let msg = 'Terjadi kesalahan.';
    try {
      const body = await res.json();
      msg = body.message || body.error || msg;
    } catch {
      msg = `Error ${res.status}`;
    }
    throw new Error(msg);
  }
  if (res.status === 204) return undefined as T;
  return res.json();
}

async function apiFetch<T>(method: string, path: string, body?: unknown): Promise<T> {
  const res = await fetch(`${BASE}/api${path}`, {
    method,
    headers: csrfHeaders(method),
    credentials: 'include',
    body: body ? JSON.stringify(body) : undefined,
  });
  return handleResponse<T>(res);
}

export async function apiGet<T>(path: string): Promise<T> {
  return apiFetch<T>('GET', path);
}

export async function apiPost<T>(path: string, body?: unknown): Promise<T> {
  return apiFetch<T>('POST', path, body);
}

export async function apiPatch<T>(path: string, body?: unknown): Promise<T> {
  return apiFetch<T>('PATCH', path, body);
}

export async function apiPut<T>(path: string, body?: unknown): Promise<T> {
  return apiFetch<T>('PUT', path, body);
}

export async function apiDelete(path: string): Promise<void> {
  await apiFetch<void>('DELETE', path);
}

export function createSSE(
  path: string,
  onEvent: (event: string, data: any) => void,
  onError?: (error: Event) => void
): EventSource {
  const url = `${BASE}/api${path}`;
  const es = new EventSource(url);
  let receivedAnyEvent = false;
  let finished = false;

  es.onopen = () => {
    console.log('SSE connection opened:', url);
  };

  ['status', 'token', 'artifact'].forEach(eventType => {
    es.addEventListener(eventType, (e: MessageEvent) => {
      if (finished) return;
      receivedAnyEvent = true;
      try {
        const data = JSON.parse(e.data);
        onEvent(eventType, data);
      } catch (err) {
        console.error('SSE parse error:', eventType, e.data, err);
      }
    });
  });

  es.addEventListener('done', (e: MessageEvent) => {
    if (finished) return;
    finished = true;
    receivedAnyEvent = true;
    try {
      const data = JSON.parse(e.data);
      onEvent('done', data);
    } catch (err) {
      console.error('SSE parse error: done', e.data, err);
    }
    es.close();
  });

  es.addEventListener('fail', (e: MessageEvent) => {
    if (finished) return;
    finished = true;
    receivedAnyEvent = true;
    try {
      const data = JSON.parse(e.data);
      onEvent('fail', data);
    } catch (err) {
      console.error('SSE parse error: fail', e.data, err);
    }
    es.close();
  });

  es.onerror = () => {
    if (finished) return;
    if (!receivedAnyEvent) {
      console.error('SSE connection error. readyState:', es.readyState);
      onError?.(new Event('error'));
    }
    // Do NOT close — let browser auto-reconnect for transient errors
  };

  return es;
}

export type Target = 'web' | 'mobile' | 'both';
export type User = { id: number; name: string; email: string; role: 'admin' | 'member' };
export type Project = { id: number; title: string; idea: string; target: Target; stack?: string; versions_count?: number; created_at: string; updated_at: string };
export type Version = {
  id: number; version_no: number; stage_status: Record<string, string>;
  analysis?: string; prd?: string; architecture?: string; erd?: object;
  api_contract?: object; phases?: object[]; master_prompt?: string;
  project?: Project; phaseProgress?: Array<{ id?: number; phase_key: string; done: boolean }>;
};
export type AiProvider = { base_url: string; model: string; api_key_masked: string };
export type Template = { id: number; name: string; target: Target; description: string | null; seed?: object; created_at: string; updated_at: string };
