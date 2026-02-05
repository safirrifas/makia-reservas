import { Hono } from 'hono';
import { z } from 'zod';
import { zValidator } from '@hono/zod-validator';
import { prisma } from '../../shared/database/client';
import { authMiddleware } from '../../shared/middleware/auth';
import type { Variables } from '../../shared/types';
import crypto from 'crypto';

export const webhooksRoutes = new Hono<{ Variables: Variables }>();

// Todas las rutas requieren autenticación
webhooksRoutes.use('*', authMiddleware);

// ============================================
// SCHEMAS
// ============================================
const createWebhookSchema = z.object({
  url: z.string().url(),
  events: z.array(z.enum([
    'booking.created',
    'booking.confirmed',
    'booking.cancelled',
    'booking.updated',
    'booking.completed',
    'booking.no_show',
  ])),
});

const updateWebhookSchema = z.object({
  url: z.string().url().optional(),
  events: z.array(z.string()).optional(),
  isActive: z.boolean().optional(),
});

// ============================================
// EVENTOS DISPONIBLES
// ============================================
export const WEBHOOK_EVENTS = {
  'booking.created': 'Se crea una nueva reserva',
  'booking.confirmed': 'Se confirma una reserva',
  'booking.cancelled': 'Se cancela una reserva',
  'booking.updated': 'Se actualiza una reserva',
  'booking.completed': 'Se marca como completada',
  'booking.no_show': 'Se marca como no-show',
} as const;

export type WebhookEvent = keyof typeof WEBHOOK_EVENTS;

// ============================================
// ROUTES
// ============================================

// Listar webhooks
webhooksRoutes.get('/', async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const webhooks = await prisma.webhook.findMany({
    where: { organizationId: organization.id },
    orderBy: { createdAt: 'desc' },
    select: {
      id: true,
      url: true,
      events: true,
      isActive: true,
      createdAt: true,
      // No incluir secret
    },
  });

  return c.json({
    success: true,
    data: webhooks,
    meta: {
      availableEvents: WEBHOOK_EVENTS,
    },
  });
});

// Crear webhook
webhooksRoutes.post('/', zValidator('json', createWebhookSchema), async (c) => {
  const organization = c.get('organization');
  const user = c.get('user')!;

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  if (user.role !== 'OWNER' && user.role !== 'ADMIN') {
    return c.json({
      success: false,
      error: { code: 'FORBIDDEN', message: 'Only owners and admins can create webhooks' },
    }, 403);
  }

  const data = c.req.valid('json');

  // Generar secret para firmar payloads
  const secret = `whsec_${crypto.randomBytes(24).toString('hex')}`;

  const webhook = await prisma.webhook.create({
    data: {
      organizationId: organization.id,
      url: data.url,
      events: data.events,
      secret,
      isActive: true,
    },
  });

  return c.json({
    success: true,
    data: {
      id: webhook.id,
      url: webhook.url,
      events: webhook.events,
      secret, // Solo se muestra al crear
      isActive: webhook.isActive,
      createdAt: webhook.createdAt,
    },
    message: 'Webhook creado. Guarda el secret, no se mostrará de nuevo.',
  }, 201);
});

// Actualizar webhook
webhooksRoutes.patch('/:id', zValidator('json', updateWebhookSchema), async (c) => {
  const organization = c.get('organization');
  const webhookId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const data = c.req.valid('json');

  const webhook = await prisma.webhook.updateMany({
    where: {
      id: webhookId,
      organizationId: organization.id,
    },
    data,
  });

  if (webhook.count === 0) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Webhook not found' },
    }, 404);
  }

  return c.json({ success: true });
});

// Eliminar webhook
webhooksRoutes.delete('/:id', async (c) => {
  const organization = c.get('organization');
  const webhookId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  await prisma.webhook.deleteMany({
    where: {
      id: webhookId,
      organizationId: organization.id,
    },
  });

  return c.json({ success: true });
});

// Regenerar secret
webhooksRoutes.post('/:id/regenerate-secret', async (c) => {
  const organization = c.get('organization');
  const webhookId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const newSecret = `whsec_${crypto.randomBytes(24).toString('hex')}`;

  const webhook = await prisma.webhook.updateMany({
    where: {
      id: webhookId,
      organizationId: organization.id,
    },
    data: { secret: newSecret },
  });

  if (webhook.count === 0) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Webhook not found' },
    }, 404);
  }

  return c.json({
    success: true,
    data: { secret: newSecret },
    message: 'Secret regenerado. Actualiza tu endpoint con el nuevo secret.',
  });
});

// Test webhook
webhooksRoutes.post('/:id/test', async (c) => {
  const organization = c.get('organization');
  const webhookId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const webhook = await prisma.webhook.findFirst({
    where: {
      id: webhookId,
      organizationId: organization.id,
    },
  });

  if (!webhook) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Webhook not found' },
    }, 404);
  }

  // Enviar evento de prueba
  const testPayload = {
    event: 'webhook.test',
    timestamp: new Date().toISOString(),
    data: {
      message: 'This is a test webhook from MakIA',
      organization: organization.slug,
    },
  };

  try {
    const result = await sendWebhook(webhook.url, webhook.secret, testPayload);
    return c.json({
      success: true,
      data: {
        delivered: result.success,
        statusCode: result.statusCode,
        responseTime: result.responseTime,
      },
    });
  } catch (error) {
    return c.json({
      success: false,
      error: {
        code: 'DELIVERY_FAILED',
        message: error instanceof Error ? error.message : 'Unknown error',
      },
    }, 500);
  }
});

// ============================================
// WEBHOOK DISPATCHER
// ============================================

interface WebhookPayload {
  event: string;
  timestamp: string;
  data: unknown;
}

interface WebhookResult {
  success: boolean;
  statusCode?: number;
  responseTime?: number;
  error?: string;
}

/**
 * Enviar webhook a un endpoint
 */
export async function sendWebhook(
  url: string,
  secret: string,
  payload: WebhookPayload
): Promise<WebhookResult> {
  const body = JSON.stringify(payload);
  const timestamp = Math.floor(Date.now() / 1000).toString();

  // Generar firma HMAC-SHA256
  const signature = crypto
    .createHmac('sha256', secret)
    .update(`${timestamp}.${body}`)
    .digest('hex');

  const startTime = Date.now();

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-MakIA-Signature': `t=${timestamp},v1=${signature}`,
        'X-MakIA-Event': payload.event,
        'User-Agent': 'MakIA-Webhooks/1.0',
      },
      body,
      signal: AbortSignal.timeout(10000), // 10s timeout
    });

    return {
      success: response.ok,
      statusCode: response.status,
      responseTime: Date.now() - startTime,
    };
  } catch (error) {
    return {
      success: false,
      error: error instanceof Error ? error.message : 'Unknown error',
      responseTime: Date.now() - startTime,
    };
  }
}

/**
 * Disparar evento de webhook para una organización
 */
export async function triggerWebhook(
  organizationId: string,
  event: WebhookEvent,
  data: unknown
): Promise<void> {
  const webhooks = await prisma.webhook.findMany({
    where: {
      organizationId,
      isActive: true,
      events: { has: event },
    },
  });

  const payload: WebhookPayload = {
    event,
    timestamp: new Date().toISOString(),
    data,
  };

  // Enviar a todos los webhooks en paralelo (fire-and-forget)
  await Promise.allSettled(
    webhooks.map((webhook) => sendWebhook(webhook.url, webhook.secret, payload))
  );
}
