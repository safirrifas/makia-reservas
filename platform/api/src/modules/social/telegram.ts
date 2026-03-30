/**
 * Integración con Telegram Bot API
 *
 * Soporta:
 * - Webhooks para recibir mensajes
 * - Envío de mensajes con teclados inline y reply
 * - Comandos del bot (/reservar, /ayuda, etc.)
 */

import { Hono } from 'hono';
import { prisma } from '../../shared/database/client';
import {
  processMessage,
  saveOutboundMessage,
  type ConversationContext,
} from './conversation-engine';

export const telegramRoutes = new Hono();

// ============================================
// TIPOS
// ============================================

interface TelegramUpdate {
  update_id: number;
  message?: TelegramMessage;
  callback_query?: {
    id: string;
    from: TelegramUser;
    message?: TelegramMessage;
    data?: string;
  };
}

interface TelegramMessage {
  message_id: number;
  from?: TelegramUser;
  chat: {
    id: number;
    type: string;
    first_name?: string;
    username?: string;
  };
  date: number;
  text?: string;
  contact?: {
    phone_number: string;
    first_name: string;
  };
}

interface TelegramUser {
  id: number;
  is_bot: boolean;
  first_name: string;
  last_name?: string;
  username?: string;
  language_code?: string;
}

interface TelegramConfig {
  botToken: string;
  botUsername?: string;
}

interface InlineKeyboardButton {
  text: string;
  callback_data?: string;
  url?: string;
}

interface ReplyKeyboardButton {
  text: string;
  request_contact?: boolean;
}

// ============================================
// WEBHOOK HANDLER
// ============================================

telegramRoutes.post('/webhook/:organizationId', async (c) => {
  const organizationId = c.req.param('organizationId');

  try {
    const update = await c.req.json<TelegramUpdate>();

    // Buscar canal y organización
    const [channel, organization] = await Promise.all([
      prisma.socialChannel.findFirst({
        where: {
          organizationId,
          type: 'TELEGRAM',
          isActive: true,
        },
      }),
      prisma.organization.findUnique({
        where: { id: organizationId },
      }),
    ]);

    if (!channel || !organization) {
      console.error(`[Telegram] Channel or org not found: ${organizationId}`);
      return c.json({ ok: true }); // Siempre responder 200 a Telegram
    }

    const config = channel.config as unknown as TelegramConfig;

    // Procesar mensaje o callback
    let chatId: number;
    let messageText: string;
    let userName: string | undefined;
    let userPhone: string | undefined;

    if (update.message) {
      chatId = update.message.chat.id;
      messageText = update.message.text || '';
      userName = update.message.from?.first_name;

      // Si envió su contacto
      if (update.message.contact) {
        userPhone = update.message.contact.phone_number;
        messageText = userPhone; // Tratar el teléfono como respuesta
      }

      // Comandos especiales
      if (messageText.startsWith('/')) {
        const command = messageText.split(' ')[0].toLowerCase();
        switch (command) {
          case '/start':
          case '/reservar':
            messageText = 'Quiero reservar';
            break;
          case '/ayuda':
          case '/help':
            messageText = 'ayuda';
            break;
          case '/cancelar':
            messageText = 'cancelar';
            break;
          default:
            // Comando desconocido, tratar como mensaje normal
            break;
        }
      }
    } else if (update.callback_query) {
      // Respuesta de botón inline
      chatId = update.callback_query.message?.chat.id || 0;
      messageText = update.callback_query.data || '';
      userName = update.callback_query.from.first_name;

      // Responder al callback para quitar el "loading"
      await answerCallbackQuery(config, update.callback_query.id);
    } else {
      return c.json({ ok: true });
    }

    if (!chatId || !messageText) {
      return c.json({ ok: true });
    }

    // Crear contexto para el motor de conversación
    const ctx: ConversationContext = {
      organizationId,
      organizationName: organization.name,
      organizationAddress: organization.address || undefined,
      organizationPhone: organization.phone || undefined,
      platform: 'TELEGRAM',
      externalId: chatId.toString(),
      socialChannelId: channel.id,
      userMessage: messageText,
      userName,
      userPhone,
    };

    // Procesar mensaje
    const response = await processMessage(ctx);

    // Enviar respuestas
    for (let i = 0; i < response.messages.length; i++) {
      const text = response.messages[i];
      const isLast = i === response.messages.length - 1;

      // Solo añadir botones en el último mensaje
      const keyboard = isLast && response.quickReplies
        ? createInlineKeyboard(response.quickReplies)
        : undefined;

      await sendTelegramMessage(config, chatId, text, keyboard);

      // Guardar mensaje saliente
      const conversation = await prisma.conversation.findFirst({
        where: {
          organizationId,
          platform: 'TELEGRAM',
          externalId: chatId.toString(),
          status: 'ACTIVE',
        },
      });

      if (conversation) {
        await saveOutboundMessage(conversation.id, text);
      }
    }

    return c.json({ ok: true });
  } catch (error) {
    console.error('[Telegram] Webhook error:', error);
    return c.json({ ok: true }); // Siempre 200 para Telegram
  }
});

// ============================================
// ENVIAR MENSAJE
// ============================================

async function sendTelegramMessage(
  config: TelegramConfig,
  chatId: number,
  text: string,
  inlineKeyboard?: InlineKeyboardButton[][],
  replyKeyboard?: ReplyKeyboardButton[][]
): Promise<number | null> {
  const url = `https://api.telegram.org/bot${config.botToken}/sendMessage`;

  try {
    const body: Record<string, unknown> = {
      chat_id: chatId,
      text,
      parse_mode: 'Markdown',
    };

    if (inlineKeyboard) {
      body.reply_markup = {
        inline_keyboard: inlineKeyboard,
      };
    } else if (replyKeyboard) {
      body.reply_markup = {
        keyboard: replyKeyboard,
        resize_keyboard: true,
        one_time_keyboard: true,
      };
    }

    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });

    const data = await response.json();

    if (!data.ok) {
      console.error('[Telegram] Send error:', data);
      return null;
    }

    return data.result?.message_id || null;
  } catch (error) {
    console.error('[Telegram] Send error:', error);
    return null;
  }
}

async function answerCallbackQuery(
  config: TelegramConfig,
  callbackQueryId: string
): Promise<void> {
  const url = `https://api.telegram.org/bot${config.botToken}/answerCallbackQuery`;

  try {
    await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ callback_query_id: callbackQueryId }),
    });
  } catch (error) {
    console.error('[Telegram] Answer callback error:', error);
  }
}

function createInlineKeyboard(options: string[]): InlineKeyboardButton[][] {
  // Crear filas de 2 botones
  const keyboard: InlineKeyboardButton[][] = [];
  for (let i = 0; i < options.length; i += 2) {
    const row: InlineKeyboardButton[] = [];
    row.push({ text: options[i], callback_data: options[i] });
    if (options[i + 1]) {
      row.push({ text: options[i + 1], callback_data: options[i + 1] });
    }
    keyboard.push(row);
  }
  return keyboard;
}

// ============================================
// CONFIGURAR WEBHOOK
// ============================================

export async function setTelegramWebhook(
  config: TelegramConfig,
  webhookUrl: string
): Promise<boolean> {
  const url = `https://api.telegram.org/bot${config.botToken}/setWebhook`;

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        url: webhookUrl,
        allowed_updates: ['message', 'callback_query'],
      }),
    });

    const data = await response.json();
    return data.ok === true;
  } catch (error) {
    console.error('[Telegram] Set webhook error:', error);
    return false;
  }
}

export async function deleteTelegramWebhook(
  config: TelegramConfig
): Promise<boolean> {
  const url = `https://api.telegram.org/bot${config.botToken}/deleteWebhook`;

  try {
    const response = await fetch(url, { method: 'POST' });
    const data = await response.json();
    return data.ok === true;
  } catch (error) {
    console.error('[Telegram] Delete webhook error:', error);
    return false;
  }
}

// ============================================
// HELPER: ENVIAR MENSAJE DESDE CÓDIGO
// ============================================

export async function sendTelegramNotification(
  organizationId: string,
  chatId: string,
  message: string
): Promise<boolean> {
  const channel = await prisma.socialChannel.findFirst({
    where: {
      organizationId,
      type: 'TELEGRAM',
      isActive: true,
    },
  });

  if (!channel) {
    console.error(`[Telegram] No active channel for org ${organizationId}`);
    return false;
  }

  const config = channel.config as unknown as TelegramConfig;
  const messageId = await sendTelegramMessage(config, parseInt(chatId), message);
  return messageId !== null;
}

// ============================================
// OBTENER INFO DEL BOT
// ============================================

export async function getTelegramBotInfo(
  botToken: string
): Promise<{ username: string; name: string } | null> {
  const url = `https://api.telegram.org/bot${botToken}/getMe`;

  try {
    const response = await fetch(url);
    const data = await response.json();

    if (!data.ok) {
      return null;
    }

    return {
      username: data.result.username,
      name: data.result.first_name,
    };
  } catch (error) {
    console.error('[Telegram] Get bot info error:', error);
    return null;
  }
}

// ============================================
// COMANDOS DEL BOT
// ============================================

export async function setTelegramCommands(
  config: TelegramConfig
): Promise<boolean> {
  const url = `https://api.telegram.org/bot${config.botToken}/setMyCommands`;

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        commands: [
          { command: 'reservar', description: 'Hacer una nueva reserva' },
          { command: 'ayuda', description: 'Ver ayuda y opciones' },
          { command: 'cancelar', description: 'Cancelar proceso actual' },
        ],
        language_code: 'es',
      }),
    });

    const data = await response.json();
    return data.ok === true;
  } catch (error) {
    console.error('[Telegram] Set commands error:', error);
    return false;
  }
}
