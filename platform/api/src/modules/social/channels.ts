/**
 * Rutas de Configuración de Canales Sociales
 *
 * Endpoints para configurar y gestionar los canales de redes sociales
 * (WhatsApp, Telegram, Messenger, Instagram).
 */

import { Hono } from 'hono';
import { z } from 'zod';
import { zValidator } from '@hono/zod-validator';
import { prisma } from '../../shared/database/client';
import { authMiddleware } from '../../shared/middleware/auth';
import { setTelegramWebhook, deleteTelegramWebhook, getTelegramBotInfo, setTelegramCommands } from './telegram';
import type { Variables } from '../../shared/types';

export const channelsRoutes = new Hono<{ Variables: Variables }>();

// Todas las rutas requieren autenticación
channelsRoutes.use('*', authMiddleware);

// ============================================
// LISTAR CANALES
// ============================================

channelsRoutes.get('/', async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const channels = await prisma.socialChannel.findMany({
    where: { organizationId: organization.id },
    orderBy: { type: 'asc' },
  });

  // Ocultar tokens sensibles
  const safeChannels = channels.map(channel => ({
    ...channel,
    config: sanitizeConfig(channel.type, channel.config as Record<string, unknown>),
  }));

  return c.json({ success: true, data: safeChannels });
});

// ============================================
// OBTENER CANAL
// ============================================

channelsRoutes.get('/:type', async (c) => {
  const organization = c.get('organization');
  const type = c.req.param('type').toUpperCase();

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const channel = await prisma.socialChannel.findFirst({
    where: {
      organizationId: organization.id,
      type: type as any,
    },
  });

  if (!channel) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Channel not found' },
    }, 404);
  }

  return c.json({
    success: true,
    data: {
      ...channel,
      config: sanitizeConfig(channel.type, channel.config as Record<string, unknown>),
    },
  });
});

// ============================================
// CONFIGURAR WHATSAPP
// ============================================

const whatsappConfigSchema = z.object({
  phoneNumberId: z.string().min(1),
  accessToken: z.string().min(1),
  verifyToken: z.string().min(8),
  businessAccountId: z.string().optional(),
  welcomeMessage: z.string().optional(),
});

channelsRoutes.post('/whatsapp', zValidator('json', whatsappConfigSchema), async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const { welcomeMessage, ...config } = c.req.valid('json');

  // Crear o actualizar canal
  const channel = await prisma.socialChannel.upsert({
    where: {
      organizationId_type: {
        organizationId: organization.id,
        type: 'WHATSAPP',
      },
    },
    create: {
      organizationId: organization.id,
      type: 'WHATSAPP',
      isActive: true,
      config,
      welcomeMessage,
    },
    update: {
      config,
      welcomeMessage,
      isActive: true,
      updatedAt: new Date(),
    },
  });

  // Generar URL del webhook
  const webhookUrl = `${process.env.API_URL || 'https://api.contacpro.app'}/social/whatsapp/webhook/${organization.id}`;

  return c.json({
    success: true,
    data: {
      channel: {
        ...channel,
        config: sanitizeConfig('WHATSAPP', config),
      },
      webhookUrl,
      instructions: [
        '1. Ve a Meta Business Suite > WhatsApp > Configuración',
        '2. En "Webhooks", configura la URL del webhook',
        `3. URL: ${webhookUrl}`,
        `4. Token de verificación: ${config.verifyToken}`,
        '5. Suscríbete a los eventos: messages',
      ],
    },
  });
});

// ============================================
// CONFIGURAR TELEGRAM
// ============================================

const telegramConfigSchema = z.object({
  botToken: z.string().min(1),
  welcomeMessage: z.string().optional(),
});

channelsRoutes.post('/telegram', zValidator('json', telegramConfigSchema), async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const { botToken, welcomeMessage } = c.req.valid('json');

  // Verificar que el token es válido
  const botInfo = await getTelegramBotInfo(botToken);

  if (!botInfo) {
    return c.json({
      success: false,
      error: { code: 'INVALID_TOKEN', message: 'Invalid Telegram bot token' },
    }, 400);
  }

  const config = {
    botToken,
    botUsername: botInfo.username,
  };

  // Crear o actualizar canal
  const channel = await prisma.socialChannel.upsert({
    where: {
      organizationId_type: {
        organizationId: organization.id,
        type: 'TELEGRAM',
      },
    },
    create: {
      organizationId: organization.id,
      type: 'TELEGRAM',
      isActive: true,
      config,
      welcomeMessage,
    },
    update: {
      config,
      welcomeMessage,
      isActive: true,
      updatedAt: new Date(),
    },
  });

  // Configurar webhook de Telegram
  const webhookUrl = `${process.env.API_URL || 'https://api.contacpro.app'}/social/telegram/webhook/${organization.id}`;
  const webhookSet = await setTelegramWebhook({ botToken, botUsername: botInfo.username }, webhookUrl);

  // Configurar comandos del bot
  await setTelegramCommands({ botToken, botUsername: botInfo.username });

  return c.json({
    success: true,
    data: {
      channel: {
        ...channel,
        config: sanitizeConfig('TELEGRAM', config),
      },
      botUsername: botInfo.username,
      botName: botInfo.name,
      botLink: `https://t.me/${botInfo.username}`,
      webhookConfigured: webhookSet,
    },
  });
});

// ============================================
// ACTIVAR/DESACTIVAR CANAL
// ============================================

channelsRoutes.patch('/:type/toggle', async (c) => {
  const organization = c.get('organization');
  const type = c.req.param('type').toUpperCase();

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const channel = await prisma.socialChannel.findFirst({
    where: {
      organizationId: organization.id,
      type: type as any,
    },
  });

  if (!channel) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Channel not configured' },
    }, 404);
  }

  const newStatus = !channel.isActive;

  // Para Telegram, gestionar webhook
  if (type === 'TELEGRAM') {
    const config = channel.config as { botToken: string; botUsername?: string };
    if (newStatus) {
      const webhookUrl = `${process.env.API_URL || 'https://api.contacpro.app'}/social/telegram/webhook/${organization.id}`;
      await setTelegramWebhook(config, webhookUrl);
    } else {
      await deleteTelegramWebhook(config);
    }
  }

  const updated = await prisma.socialChannel.update({
    where: { id: channel.id },
    data: { isActive: newStatus },
  });

  return c.json({
    success: true,
    data: {
      ...updated,
      config: sanitizeConfig(updated.type, updated.config as Record<string, unknown>),
    },
  });
});

// ============================================
// ELIMINAR CANAL
// ============================================

channelsRoutes.delete('/:type', async (c) => {
  const organization = c.get('organization');
  const type = c.req.param('type').toUpperCase();

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const channel = await prisma.socialChannel.findFirst({
    where: {
      organizationId: organization.id,
      type: type as any,
    },
  });

  if (!channel) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Channel not found' },
    }, 404);
  }

  // Para Telegram, eliminar webhook
  if (type === 'TELEGRAM') {
    const config = channel.config as { botToken: string; botUsername?: string };
    await deleteTelegramWebhook(config);
  }

  await prisma.socialChannel.delete({
    where: { id: channel.id },
  });

  return c.json({ success: true, message: 'Channel deleted' });
});

// ============================================
// ESTADÍSTICAS DE CONVERSACIONES
// ============================================

channelsRoutes.get('/stats/conversations', async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const thirtyDaysAgo = new Date();
  thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

  const [
    totalConversations,
    completedConversations,
    byPlatform,
    byStatus,
    recentBookings,
  ] = await Promise.all([
    // Total conversaciones
    prisma.conversation.count({
      where: {
        organizationId: organization.id,
        createdAt: { gte: thirtyDaysAgo },
      },
    }),
    // Conversaciones completadas (reserva creada)
    prisma.conversation.count({
      where: {
        organizationId: organization.id,
        status: 'COMPLETED',
        createdAt: { gte: thirtyDaysAgo },
      },
    }),
    // Por plataforma
    prisma.conversation.groupBy({
      by: ['platform'],
      where: {
        organizationId: organization.id,
        createdAt: { gte: thirtyDaysAgo },
      },
      _count: true,
    }),
    // Por estado
    prisma.conversation.groupBy({
      by: ['status'],
      where: {
        organizationId: organization.id,
        createdAt: { gte: thirtyDaysAgo },
      },
      _count: true,
    }),
    // Reservas desde redes sociales
    prisma.booking.count({
      where: {
        organizationId: organization.id,
        source: { startsWith: 'social:' },
        createdAt: { gte: thirtyDaysAgo },
      },
    }),
  ]);

  const conversionRate = totalConversations > 0
    ? Math.round((completedConversations / totalConversations) * 100)
    : 0;

  return c.json({
    success: true,
    data: {
      period: {
        from: thirtyDaysAgo.toISOString(),
        to: new Date().toISOString(),
      },
      summary: {
        totalConversations,
        completedConversations,
        conversionRate,
        bookingsFromSocial: recentBookings,
      },
      byPlatform: byPlatform.reduce((acc, item) => {
        acc[item.platform] = item._count;
        return acc;
      }, {} as Record<string, number>),
      byStatus: byStatus.reduce((acc, item) => {
        acc[item.status] = item._count;
        return acc;
      }, {} as Record<string, number>),
    },
  });
});

// ============================================
// CONVERSACIONES RECIENTES
// ============================================

channelsRoutes.get('/conversations', async (c) => {
  const organization = c.get('organization');
  const platform = c.req.query('platform')?.toUpperCase();
  const status = c.req.query('status')?.toUpperCase();
  const page = parseInt(c.req.query('page') || '1');
  const limit = parseInt(c.req.query('limit') || '20');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const where: any = { organizationId: organization.id };
  if (platform) where.platform = platform;
  if (status) where.status = status;

  const [conversations, total] = await Promise.all([
    prisma.conversation.findMany({
      where,
      orderBy: { lastActivityAt: 'desc' },
      skip: (page - 1) * limit,
      take: limit,
      include: {
        _count: { select: { messages: true } },
      },
    }),
    prisma.conversation.count({ where }),
  ]);

  return c.json({
    success: true,
    data: conversations,
    pagination: {
      page,
      limit,
      total,
      totalPages: Math.ceil(total / limit),
    },
  });
});

// ============================================
// DETALLE DE CONVERSACIÓN
// ============================================

channelsRoutes.get('/conversations/:id', async (c) => {
  const organization = c.get('organization');
  const conversationId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const conversation = await prisma.conversation.findFirst({
    where: {
      id: conversationId,
      organizationId: organization.id,
    },
    include: {
      messages: {
        orderBy: { createdAt: 'asc' },
      },
    },
  });

  if (!conversation) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Conversation not found' },
    }, 404);
  }

  return c.json({ success: true, data: conversation });
});

// ============================================
// HELPERS
// ============================================

function sanitizeConfig(
  type: string,
  config: Record<string, unknown>
): Record<string, unknown> {
  const safe = { ...config };

  // Ocultar tokens sensibles, solo mostrar últimos 4 caracteres
  const sensitiveFields = ['accessToken', 'botToken', 'verifyToken', 'secret'];

  for (const field of sensitiveFields) {
    if (safe[field] && typeof safe[field] === 'string') {
      const value = safe[field] as string;
      safe[field] = value.length > 8
        ? `****${value.slice(-4)}`
        : '****';
    }
  }

  return safe;
}
