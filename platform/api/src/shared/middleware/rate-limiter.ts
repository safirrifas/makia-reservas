import type { Context, Next } from 'hono';

interface RateLimitOptions {
  windowMs: number;
  max: number;
  keyGenerator?: (c: Context) => string;
}

// Simple in-memory rate limiter (para producción usar Redis)
const store = new Map<string, { count: number; resetTime: number }>();

export function rateLimiter(options: RateLimitOptions) {
  const { windowMs, max, keyGenerator } = options;

  // Limpiar entradas expiradas cada minuto
  setInterval(() => {
    const now = Date.now();
    for (const [key, value] of store.entries()) {
      if (value.resetTime < now) {
        store.delete(key);
      }
    }
  }, 60000);

  return async (c: Context, next: Next) => {
    const key = keyGenerator?.(c) ?? getClientIp(c);
    const now = Date.now();

    let record = store.get(key);

    if (!record || record.resetTime < now) {
      record = { count: 0, resetTime: now + windowMs };
      store.set(key, record);
    }

    record.count++;

    // Headers de rate limit
    c.header('X-RateLimit-Limit', max.toString());
    c.header('X-RateLimit-Remaining', Math.max(0, max - record.count).toString());
    c.header('X-RateLimit-Reset', Math.ceil(record.resetTime / 1000).toString());

    if (record.count > max) {
      return c.json({
        success: false,
        error: {
          code: 'RATE_LIMIT_EXCEEDED',
          message: 'Too many requests. Please try again later.',
          retryAfter: Math.ceil((record.resetTime - now) / 1000),
        },
      }, 429);
    }

    await next();
  };
}

function getClientIp(c: Context): string {
  // Considerar headers de proxy
  const forwarded = c.req.header('x-forwarded-for');
  if (forwarded) {
    return forwarded.split(',')[0].trim();
  }
  return c.req.header('x-real-ip') ?? 'unknown';
}
