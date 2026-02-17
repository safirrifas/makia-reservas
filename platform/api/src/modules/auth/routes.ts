import { Hono } from 'hono';
import { z } from 'zod';
import { zValidator } from '@hono/zod-validator';
import { prisma } from '../../shared/database/client';
import { generateToken, authMiddleware } from '../../shared/middleware/auth';
import type { Variables } from '../../shared/types';
import { nanoid } from 'nanoid';

export const authRoutes = new Hono<{ Variables: Variables }>();

// ============================================
// SCHEMAS
// ============================================
const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(6),
});

const registerSchema = z.object({
  email: z.string().email(),
  password: z.string().min(8),
  name: z.string().min(2),
  organizationName: z.string().min(2).optional(),
});

const apiKeySchema = z.object({
  name: z.string().min(1),
  scopes: z.array(z.string()).default(['bookings:read', 'bookings:write']),
  expiresInDays: z.number().optional(),
});

// ============================================
// ROUTES
// ============================================

// Login
authRoutes.post('/login', zValidator('json', loginSchema), async (c) => {
  const { email, password } = c.req.valid('json');

  const user = await prisma.user.findUnique({
    where: { email },
    include: { organization: true },
  });

  if (!user || !user.passwordHash) {
    return c.json({
      success: false,
      error: { code: 'INVALID_CREDENTIALS', message: 'Invalid email or password' },
    }, 401);
  }

  // Verificar password (usando Web Crypto API)
  const isValid = await verifyPassword(password, user.passwordHash);

  if (!isValid) {
    return c.json({
      success: false,
      error: { code: 'INVALID_CREDENTIALS', message: 'Invalid email or password' },
    }, 401);
  }

  // Actualizar último login
  await prisma.user.update({
    where: { id: user.id },
    data: { lastLoginAt: new Date() },
  });

  const token = await generateToken(user);

  return c.json({
    success: true,
    data: {
      token,
      user: {
        id: user.id,
        email: user.email,
        name: user.name,
        role: user.role,
        organization: user.organization ? {
          id: user.organization.id,
          name: user.organization.name,
          slug: user.organization.slug,
          plan: user.organization.plan,
        } : null,
      },
    },
  });
});

// Registro
authRoutes.post('/register', zValidator('json', registerSchema), async (c) => {
  const { email, password, name, organizationName } = c.req.valid('json');

  // Verificar si el email ya existe
  const existing = await prisma.user.findUnique({ where: { email } });
  if (existing) {
    return c.json({
      success: false,
      error: { code: 'EMAIL_EXISTS', message: 'Email already registered' },
    }, 409);
  }

  const passwordHash = await hashPassword(password);

  // Crear usuario y organización en una transacción
  const result = await prisma.$transaction(async (tx) => {
    let organization = null;

    if (organizationName) {
      const slug = generateSlug(organizationName);
      organization = await tx.organization.create({
        data: {
          name: organizationName,
          slug,
          plan: 'FREE',
        },
      });
    }

    const user = await tx.user.create({
      data: {
        email,
        passwordHash,
        name,
        role: organization ? 'OWNER' : 'OPERATOR',
        organizationId: organization?.id,
      },
    });

    return { user, organization };
  });

  const token = await generateToken(result.user);

  return c.json({
    success: true,
    data: {
      token,
      user: {
        id: result.user.id,
        email: result.user.email,
        name: result.user.name,
        role: result.user.role,
        organization: result.organization ? {
          id: result.organization.id,
          name: result.organization.name,
          slug: result.organization.slug,
        } : null,
      },
    },
  }, 201);
});

// Obtener usuario actual
authRoutes.get('/me', authMiddleware, async (c) => {
  const user = c.get('user')!;
  const organization = c.get('organization');

  return c.json({
    success: true,
    data: {
      id: user.id,
      email: user.email,
      name: user.name,
      role: user.role,
      organization: organization ? {
        id: organization.id,
        name: organization.name,
        slug: organization.slug,
        plan: organization.plan,
      } : null,
    },
  });
});

// Generar API Key
authRoutes.post('/api-keys', authMiddleware, zValidator('json', apiKeySchema), async (c) => {
  const user = c.get('user')!;
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  if (user.role !== 'OWNER' && user.role !== 'ADMIN') {
    return c.json({
      success: false,
      error: { code: 'FORBIDDEN', message: 'Only owners and admins can create API keys' },
    }, 403);
  }

  const { name, scopes, expiresInDays } = c.req.valid('json');

  // Generar API key
  const isProduction = process.env.NODE_ENV === 'production';
  const prefix = isProduction ? 'mk_live_' : 'mk_test_';
  const rawKey = prefix + nanoid(32);
  const keyHash = await hashApiKey(rawKey);

  const apiKey = await prisma.apiKey.create({
    data: {
      name,
      keyHash,
      keyPrefix: rawKey.substring(0, 12) + '...',
      scopes,
      expiresAt: expiresInDays ? new Date(Date.now() + expiresInDays * 24 * 60 * 60 * 1000) : null,
      organizationId: organization.id,
    },
  });

  // IMPORTANTE: Solo mostramos la key completa una vez
  return c.json({
    success: true,
    data: {
      id: apiKey.id,
      name: apiKey.name,
      key: rawKey, // Solo se muestra una vez!
      keyPrefix: apiKey.keyPrefix,
      scopes: apiKey.scopes,
      expiresAt: apiKey.expiresAt,
      createdAt: apiKey.createdAt,
    },
  }, 201);
});

// Listar API Keys
authRoutes.get('/api-keys', authMiddleware, async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  const apiKeys = await prisma.apiKey.findMany({
    where: { organizationId: organization.id },
    select: {
      id: true,
      name: true,
      keyPrefix: true,
      scopes: true,
      lastUsedAt: true,
      expiresAt: true,
      createdAt: true,
    },
    orderBy: { createdAt: 'desc' },
  });

  return c.json({
    success: true,
    data: apiKeys,
  });
});

// Revocar API Key
authRoutes.delete('/api-keys/:id', authMiddleware, async (c) => {
  const organization = c.get('organization');
  const keyId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  await prisma.apiKey.deleteMany({
    where: {
      id: keyId,
      organizationId: organization.id,
    },
  });

  return c.json({ success: true });
});

// ============================================
// HELPERS
// ============================================
async function hashPassword(password: string): Promise<string> {
  const encoder = new TextEncoder();
  const salt = crypto.getRandomValues(new Uint8Array(16));
  const keyMaterial = await crypto.subtle.importKey(
    'raw',
    encoder.encode(password),
    'PBKDF2',
    false,
    ['deriveBits']
  );

  const derivedBits = await crypto.subtle.deriveBits(
    {
      name: 'PBKDF2',
      salt,
      iterations: 100000,
      hash: 'SHA-256',
    },
    keyMaterial,
    256
  );

  const hashArray = Array.from(new Uint8Array(derivedBits));
  const saltArray = Array.from(salt);

  return saltArray.map((b) => b.toString(16).padStart(2, '0')).join('') +
    ':' +
    hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
}

async function verifyPassword(password: string, hash: string): Promise<boolean> {
  const [saltHex, hashHex] = hash.split(':');
  const salt = new Uint8Array(saltHex.match(/.{2}/g)!.map((byte) => parseInt(byte, 16)));

  const encoder = new TextEncoder();
  const keyMaterial = await crypto.subtle.importKey(
    'raw',
    encoder.encode(password),
    'PBKDF2',
    false,
    ['deriveBits']
  );

  const derivedBits = await crypto.subtle.deriveBits(
    {
      name: 'PBKDF2',
      salt,
      iterations: 100000,
      hash: 'SHA-256',
    },
    keyMaterial,
    256
  );

  const derivedHash = Array.from(new Uint8Array(derivedBits))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');

  return derivedHash === hashHex;
}

async function hashApiKey(key: string): Promise<string> {
  const encoder = new TextEncoder();
  const data = encoder.encode(key);
  const hashBuffer = await crypto.subtle.digest('SHA-256', data);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
}

function generateSlug(name: string): string {
  const base = name
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');

  return base + '-' + nanoid(6);
}
