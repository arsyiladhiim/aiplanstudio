// Simple in-memory rate limiter for BFF route handlers.
// Resets every windowMs. Uses process-wide Map (per-instance, not distributed).
// For production with multiple BFF instances, use Redis. ponytail: acceptable for single-instance Docker.

const rateLimitMap = new Map<string, { count: number; resetAt: number }>();

export function rateLimit(
  key: string,
  maxRequests: number,
  windowMs: number,
): { allowed: boolean; remaining: number; retryAfterMs: number } {
  const now = Date.now();
  const entry = rateLimitMap.get(key);

  if (!entry || now > entry.resetAt) {
    rateLimitMap.set(key, { count: 1, resetAt: now + windowMs });
    return { allowed: true, remaining: maxRequests - 1, retryAfterMs: windowMs };
  }

  if (entry.count >= maxRequests) {
    return { allowed: false, remaining: 0, retryAfterMs: entry.resetAt - now };
  }

  entry.count++;
  return { allowed: true, remaining: maxRequests - entry.count, retryAfterMs: entry.resetAt - now };
}

export function getClientIp(request: Request): string {
  const forwarded = request.headers.get('x-forwarded-for');
  if (forwarded) return forwarded.split(',')[0].trim();
  return 'unknown';
}

/** Return 429 Response if rate limited */
export function checkRateLimit(
  request: Request,
  endpoint: string,
  maxRequests = 5,
  windowMs = 60_000,
): Response | null {
  const ip = getClientIp(request);
  const key = `${endpoint}:${ip}`;
  const result = rateLimit(key, maxRequests, windowMs);
  if (!result.allowed) {
    const retryAfterSec = Math.ceil(result.retryAfterMs / 1000);
    return new Response(
      JSON.stringify({ message: `Terlalu banyak permintaan. Coba lagi dalam ${retryAfterSec} detik.` }),
      {
        status: 429,
        headers: {
          'Content-Type': 'application/json',
          'Retry-After': String(retryAfterSec),
        },
      },
    );
  }
  return null;
}
