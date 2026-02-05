/**
 * MakIA Restaurante - Webhook Management Routes
 *
 * Endpoints para gestionar webhooks de organizaciones
 */

import { Hono } from 'hono';
import { HTTPException } from 'hono/http-exception';
import { webhookService, type WebhookEvent } from './service';

const webhooks = new Hono();

/**
 * GET /webhooks
 * Listar webhooks de la organización
 */
webhooks.get('/', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');

  if (!orgId) {
    throw new HTTPException(400, { message: 'Organization ID required' });
  }

  // TODO: Obtener de base de datos
  const endpoints = [
    {
      id: 'wh_example',
      url: 'https://example.com/wp-json/makia/v1/webhook',
      events: ['license.updated', 'notification.new'],
      active: true,
      created_at: new Date().toISOString(),
    },
  ];

  return c.json({
    success: true,
    data: endpoints,
  });
});

/**
 * POST /webhooks
 * Registrar nuevo webhook endpoint
 */
webhooks.post('/', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');
  const body = await c.req.json();
  const { url, events } = body;

  if (!orgId) {
    throw new HTTPException(400, { message: 'Organization ID required' });
  }

  if (!url) {
    throw new HTTPException(400, { message: 'Webhook URL required' });
  }

  // Validar URL
  try {
    new URL(url);
  } catch {
    throw new HTTPException(400, { message: 'Invalid URL format' });
  }

  // Validar eventos
  const validEvents: WebhookEvent[] = [
    'license.updated',
    'license.expired',
    'license.renewed',
    'plan.upgraded',
    'plan.downgraded',
    'payment.succeeded',
    'payment.failed',
    'notification.new',
    'plugin.update_available',
    'system.maintenance',
  ];

  const selectedEvents = events?.length ? events.filter((e: string) => validEvents.includes(e as WebhookEvent)) : validEvents;

  const endpoint = await webhookService.registerEndpoint(orgId, url, selectedEvents);

  return c.json({
    success: true,
    data: {
      id: endpoint.id,
      url: endpoint.url,
      secret: endpoint.secret,
      events: endpoint.events,
      message: 'Webhook registrado. Guarda el secret de forma segura, no se mostrará de nuevo.',
    },
  });
});

/**
 * GET /webhooks/:id
 * Obtener detalle de un webhook
 */
webhooks.get('/:id', async (c) => {
  const webhookId = c.req.param('id');
  const orgId = c.req.query('org') || c.get('organizationId');

  // TODO: Obtener de base de datos y verificar pertenencia a organización

  return c.json({
    success: true,
    data: {
      id: webhookId,
      url: 'https://example.com/wp-json/makia/v1/webhook',
      events: ['license.updated', 'notification.new'],
      active: true,
      created_at: new Date().toISOString(),
      deliveries: {
        total: 42,
        successful: 40,
        failed: 2,
      },
    },
  });
});

/**
 * PATCH /webhooks/:id
 * Actualizar webhook
 */
webhooks.patch('/:id', async (c) => {
  const webhookId = c.req.param('id');
  const body = await c.req.json();
  const { url, events, active } = body;

  // TODO: Actualizar en base de datos

  return c.json({
    success: true,
    data: {
      id: webhookId,
      url: url || 'https://example.com/wp-json/makia/v1/webhook',
      events: events || ['license.updated', 'notification.new'],
      active: active !== undefined ? active : true,
      updated_at: new Date().toISOString(),
    },
  });
});

/**
 * DELETE /webhooks/:id
 * Eliminar webhook
 */
webhooks.delete('/:id', async (c) => {
  const webhookId = c.req.param('id');

  // TODO: Eliminar de base de datos

  return c.json({
    success: true,
    data: {
      deleted: true,
      id: webhookId,
    },
  });
});

/**
 * POST /webhooks/:id/test
 * Enviar webhook de prueba
 */
webhooks.post('/:id/test', async (c) => {
  const webhookId = c.req.param('id');

  // TODO: Obtener endpoint de base de datos y enviar test

  return c.json({
    success: true,
    data: {
      sent: true,
      webhook_id: webhookId,
      event: 'license.updated',
      test: true,
    },
  });
});

/**
 * POST /webhooks/:id/rotate-secret
 * Rotar secret del webhook
 */
webhooks.post('/:id/rotate-secret', async (c) => {
  const webhookId = c.req.param('id');

  // TODO: Generar nuevo secret y actualizar en base de datos
  const newSecret = Array.from({ length: 32 }, () =>
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'.charAt(
      Math.floor(Math.random() * 62)
    )
  ).join('');

  return c.json({
    success: true,
    data: {
      id: webhookId,
      secret: newSecret,
      message: 'Secret rotado. Actualiza la configuración en tu sitio WordPress.',
    },
  });
});

/**
 * GET /webhooks/:id/deliveries
 * Historial de entregas de un webhook
 */
webhooks.get('/:id/deliveries', async (c) => {
  const webhookId = c.req.param('id');
  const page = parseInt(c.req.query('page') || '1');
  const limit = parseInt(c.req.query('limit') || '20');

  // TODO: Obtener de base de datos

  return c.json({
    success: true,
    data: {
      deliveries: [
        {
          id: 'whd_001',
          event: 'license.updated',
          response_status: 200,
          delivered_at: new Date().toISOString(),
          attempts: 1,
        },
        {
          id: 'whd_002',
          event: 'notification.new',
          response_status: 200,
          delivered_at: new Date(Date.now() - 3600000).toISOString(),
          attempts: 1,
        },
      ],
      pagination: {
        page,
        limit,
        total: 42,
        pages: 3,
      },
    },
  });
});

/**
 * GET /webhooks/events
 * Listar eventos disponibles
 */
webhooks.get('/events/list', async (c) => {
  const events = [
    {
      name: 'license.updated',
      description: 'Se actualiza cuando cambia algún dato de la licencia',
    },
    {
      name: 'license.expired',
      description: 'Se envía cuando la licencia expira',
    },
    {
      name: 'license.renewed',
      description: 'Se envía cuando se renueva la licencia',
    },
    {
      name: 'plan.upgraded',
      description: 'Se envía cuando se mejora el plan',
    },
    {
      name: 'plan.downgraded',
      description: 'Se envía cuando se reduce el plan',
    },
    {
      name: 'payment.succeeded',
      description: 'Se envía cuando un pago se procesa correctamente',
    },
    {
      name: 'payment.failed',
      description: 'Se envía cuando falla un pago',
    },
    {
      name: 'notification.new',
      description: 'Se envía cuando hay una nueva notificación del sistema',
    },
    {
      name: 'plugin.update_available',
      description: 'Se envía cuando hay una nueva versión del plugin disponible',
    },
    {
      name: 'system.maintenance',
      description: 'Se envía para notificar mantenimiento programado',
    },
  ];

  return c.json({
    success: true,
    data: events,
  });
});

export default webhooks;
