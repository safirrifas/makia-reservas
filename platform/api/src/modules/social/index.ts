/**
 * Módulo de Reservas desde Redes Sociales
 *
 * Este módulo permite a los clientes hacer reservas a través de:
 * - WhatsApp Business
 * - Telegram
 * - (Futuro) Facebook Messenger
 * - (Futuro) Instagram DM
 *
 * Arquitectura:
 * - Motor de conversación unificado (conversation-engine.ts)
 * - Adaptadores por plataforma (whatsapp.ts, telegram.ts)
 * - Endpoints de configuración (channels.ts)
 */

import { Hono } from 'hono';
import { whatsappRoutes } from './whatsapp';
import { telegramRoutes } from './telegram';
import { channelsRoutes } from './channels';

export const socialRoutes = new Hono();

// Rutas de webhooks (públicas, sin auth)
socialRoutes.route('/whatsapp', whatsappRoutes);
socialRoutes.route('/telegram', telegramRoutes);

// Rutas de configuración (requieren auth)
socialRoutes.route('/channels', channelsRoutes);

// Re-exportar funciones útiles
export { processMessage, cleanupExpiredConversations } from './conversation-engine';
export { sendWhatsAppNotification, sendWhatsAppTemplate } from './whatsapp';
export { sendTelegramNotification, getTelegramBotInfo } from './telegram';
