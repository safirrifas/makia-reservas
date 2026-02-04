import type { Context, Next } from 'hono';
import { HTTPException } from 'hono/http-exception';
import * as jose from 'jose';
import { prisma } from '../database/client';
import type { JWTPayload, Variables } from '../types';

const JWT_SECRET = new TextEncoder().encode(
  process.env.JWT_SECRET || 'makia-super-secret-key-change-in-production'
);

export async function authMiddleware(c: Context<{ Variables: Variables }>, next: Next) {
  const authHeader = c.req.header('Authorization');

  if (!authHeader) {
    throw new HTTPException(401, { message: 'Authorization header required' });
  }

  // API Key authentication (mk_live_xxx or mk_test_xxx)
  if (authHeader.startsWith('Bearer mk_')) {
    return handleApiKeyAuth(c, authHeader.substring(7), next);
  }

  // JWT authentication
  if (authHeader.startsWith('Bearer ')) {
    return handleJwtAuth(c, authHeader.substring(7), next);
  }

  throw new HTTPException(401, { message: 'Invalid authorization format' });
}

async function handleJwtAuth(c: Context<{ Variables: Variables }>, token: string, next: Next) {
  try {
    const { payload } = await jose.jwtVerify(token, JWT_SECRET) as { payload: JWTPayload };

    const user = await prisma.user.findUnique({
      where: { id: payload.sub },
      include: { organization: true },
    });

    if (!user || !user.isActive) {
      throw new HTTPException(401, { message: 'User not found or inactive' });
    }

    c.set('user', user);
    if (user.organization) {
      c.set('organization', user.organization);
    }

    await next();
  } catch (error) {
    if (error instanceof jose.errors.JWTExpired) {
      throw new HTTPException(401, { message: 'Token expired' });
    }
    if (error instanceof jose.errors.JWTInvalid) {
      throw new HTTPException(401, { message: 'Invalid token' });
    }
    throw error;
  }
}

async function handleApiKeyAuth(c: Context<{ Variables: Variables }>, apiKey: string, next: Next) {
  const keyHash = await hashApiKey(apiKey);

  const key = await prisma.apiKey.findUnique({
    where: { keyHash },
    include: { organization: true },
  });

  if (!key) {
    throw new HTTPException(401, { message: 'Invalid API key' });
  }

  if (key.expiresAt && key.expiresAt < new Date()) {
    throw new HTTPException(401, { message: 'API key expired' });
  }

  // Actualizar último uso
  await prisma.apiKey.update({
    where: { id: key.id },
    data: { lastUsedAt: new Date() },
  });

  c.set('apiKey', {
    id: key.id,
    organizationId: key.organizationId,
    scopes: key.scopes,
  });
  c.set('organization', key.organization);

  await next();
}

async function hashApiKey(key: string): Promise<string> {
  const encoder = new TextEncoder();
  const data = encoder.encode(key);
  const hashBuffer = await crypto.subtle.digest('SHA-256', data);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
}

// Generar JWT token
export async function generateToken(user: { id: string; email: string; role: string; organizationId?: string | null }): Promise<string> {
  const payload: JWTPayload = {
    sub: user.id,
    email: user.email,
    role: user.role,
    orgId: user.organizationId ?? undefined,
    iat: Math.floor(Date.now() / 1000),
    exp: Math.floor(Date.now() / 1000) + (24 * 60 * 60), // 24 horas
  };

  return await new jose.SignJWT(payload as unknown as jose.JWTPayload)
    .setProtectedHeader({ alg: 'HS256' })
    .setIssuedAt()
    .setExpirationTime('24h')
    .sign(JWT_SECRET);
}

// Middleware opcional (no lanza error si no hay auth)
export async function optionalAuth(c: Context<{ Variables: Variables }>, next: Next) {
  const authHeader = c.req.header('Authorization');

  if (authHeader) {
    try {
      await authMiddleware(c, async () => {});
    } catch {
      // Ignorar errores de auth
    }
  }

  await next();
}

// Verificar scopes para API keys
export function requireScope(scope: string) {
  return async (c: Context<{ Variables: Variables }>, next: Next) => {
    const apiKey = c.get('apiKey');

    if (apiKey && !apiKey.scopes.includes(scope) && !apiKey.scopes.includes('*')) {
      throw new HTTPException(403, { message: `Missing required scope: ${scope}` });
    }

    await next();
  };
}
