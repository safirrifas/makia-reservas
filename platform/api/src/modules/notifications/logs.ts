/**
 * Rutas de Logs de Notificaciones
 *
 * Endpoints para consultar el historial de notificaciones enviadas.
 */

import { Hono } from 'hono';
import { z } from 'zod';
import { zValidator } from '@hono/zod-validator';
import { prisma } from '../../shared/database/client';
import { authMiddleware } from '../../shared/middleware/auth';
import { getQueueStats, cancelScheduledNotification } from './queue';
import type { Variables } from '../../shared/types';

export const logsRoutes = new Hono<{ Variables: Variables }>();

// Todas las rutas requieren autenticación
logsRoutes.use('*', authMiddleware);

// ============================================
// LISTAR LOGS
// ============================================

const listLogsSchema = z.object({
  channel: z.enum(['EMAIL', 'SMS', 'WHATSAPP', 'PUSH']).optional(),
  status: z.enum(['QUEUED', 'SENDING', 'SENT', 'DELIVERED', 'OPENED', 'CLICKED', 'FAILED', 'CANCELLED']).optional(),
  bookingId: z.string().optional(),
  dateFrom: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
  dateTo: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
  page: z.coerce.number().int().min(1).default(1),
  limit: z.coerce.number().int().min(1).max(100).default(50),
});

logsRoutes.get('/', zValidator('query', listLogsSchema), async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const { channel, status, bookingId, dateFrom, dateTo, page, limit } = c.req.valid('query');

  const where: any = {
    organizationId: organization.id,
  };

  if (channel) where.channel = channel;
  if (status) where.status = status;
  if (bookingId) where.bookingId = bookingId;

  if (dateFrom || dateTo) {
    where.queuedAt = {};
    if (dateFrom) where.queuedAt.gte = new Date(dateFrom);
    if (dateTo) where.queuedAt.lte = new Date(dateTo + 'T23:59:59Z');
  }

  const [logs, total] = await Promise.all([
    prisma.notificationLog.findMany({
      where,
      orderBy: { queuedAt: 'desc' },
      skip: (page - 1) * limit,
      take: limit,
    }),
    prisma.notificationLog.count({ where }),
  ]);

  return c.json({
    success: true,
    data: logs,
    pagination: {
      page,
      limit,
      total,
      totalPages: Math.ceil(total / limit),
    },
  });
});

// ============================================
// OBTENER LOG POR ID
// ============================================

logsRoutes.get('/:id', async (c) => {
  const organization = c.get('organization');
  const logId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const log = await prisma.notificationLog.findFirst({
    where: {
      id: logId,
      organizationId: organization.id,
    },
  });

  if (!log) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Log not found' },
    }, 404);
  }

  return c.json({ success: true, data: log });
});

// ============================================
// CANCELAR NOTIFICACIÓN PROGRAMADA
// ============================================

logsRoutes.post('/:id/cancel', async (c) => {
  const organization = c.get('organization');
  const logId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const log = await prisma.notificationLog.findFirst({
    where: {
      id: logId,
      organizationId: organization.id,
      status: { in: ['QUEUED'] },
    },
  });

  if (!log) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Queued notification not found' },
    }, 404);
  }

  await cancelScheduledNotification(logId);

  return c.json({ success: true, message: 'Notification cancelled' });
});

// ============================================
// ESTADÍSTICAS
// ============================================

logsRoutes.get('/stats/summary', async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  // Stats de los últimos 30 días
  const thirtyDaysAgo = new Date();
  thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

  const [
    totalSent,
    totalFailed,
    byChannel,
    byStatus,
    queueStats,
  ] = await Promise.all([
    // Total enviados
    prisma.notificationLog.count({
      where: {
        organizationId: organization.id,
        status: { in: ['SENT', 'DELIVERED', 'OPENED', 'CLICKED'] },
        queuedAt: { gte: thirtyDaysAgo },
      },
    }),
    // Total fallidos
    prisma.notificationLog.count({
      where: {
        organizationId: organization.id,
        status: 'FAILED',
        queuedAt: { gte: thirtyDaysAgo },
      },
    }),
    // Por canal
    prisma.notificationLog.groupBy({
      by: ['channel'],
      where: {
        organizationId: organization.id,
        queuedAt: { gte: thirtyDaysAgo },
      },
      _count: true,
    }),
    // Por estado
    prisma.notificationLog.groupBy({
      by: ['status'],
      where: {
        organizationId: organization.id,
        queuedAt: { gte: thirtyDaysAgo },
      },
      _count: true,
    }),
    // Stats de la cola
    getQueueStats(),
  ]);

  const successRate = totalSent + totalFailed > 0
    ? Math.round((totalSent / (totalSent + totalFailed)) * 100)
    : 100;

  return c.json({
    success: true,
    data: {
      period: {
        from: thirtyDaysAgo.toISOString(),
        to: new Date().toISOString(),
      },
      summary: {
        totalSent,
        totalFailed,
        successRate,
      },
      byChannel: byChannel.reduce((acc, item) => {
        acc[item.channel] = item._count;
        return acc;
      }, {} as Record<string, number>),
      byStatus: byStatus.reduce((acc, item) => {
        acc[item.status] = item._count;
        return acc;
      }, {} as Record<string, number>),
      queue: queueStats,
    },
  });
});

// ============================================
// LOGS POR RESERVA
// ============================================

logsRoutes.get('/booking/:bookingId', async (c) => {
  const organization = c.get('organization');
  const bookingId = c.req.param('bookingId');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  // Verificar que la reserva pertenece a la organización
  const booking = await prisma.booking.findFirst({
    where: {
      id: bookingId,
      organizationId: organization.id,
    },
  });

  if (!booking) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Booking not found' },
    }, 404);
  }

  const logs = await prisma.notificationLog.findMany({
    where: { bookingId },
    orderBy: { queuedAt: 'desc' },
  });

  return c.json({ success: true, data: logs });
});

// ============================================
// REENVIAR NOTIFICACIÓN FALLIDA
// ============================================

logsRoutes.post('/:id/retry', async (c) => {
  const organization = c.get('organization');
  const logId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const log = await prisma.notificationLog.findFirst({
    where: {
      id: logId,
      organizationId: organization.id,
      status: 'FAILED',
    },
  });

  if (!log) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Failed notification not found' },
    }, 404);
  }

  // Crear nueva notificación basada en la fallida
  const { queueNotification } = await import('./queue');

  const result = await queueNotification({
    organizationId: log.organizationId,
    bookingId: log.bookingId || undefined,
    channel: log.channel,
    event: log.event,
    recipient: log.recipient,
    subject: log.subject || undefined,
    body: log.body,
    metadata: {
      ...(log.metadata as object || {}),
      retryOf: log.id,
    },
  });

  return c.json({
    success: true,
    data: { newLogId: result.logId },
    message: 'Notification queued for retry',
  });
});
