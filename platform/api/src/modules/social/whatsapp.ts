/**
 * Integración con WhatsApp Business API
 *
 * Soporta:
 * - WhatsApp Business API (Cloud API de Meta)
 * - Webhooks para recibir mensajes
 * - Envío de mensajes de texto y botones
 */

import { Hono } from 'hono';
import { prisma } from '../../shared/database/client';
import {
  processMessage,
  saveOutboundMessage,
  type ConversationContext,
} from './conversation-engine';

export const whatsappRoutes = new Hono();

// ============================================
// TIPOS
// ============================================

interface WhatsAppWebhookPayload {
  object: string;
  entry: Array<{
    id: string;
    changes: Array<{
      value: {
        messaging_product: string;
        metadata: {
          display_phone_number: string;
          phone_number_id: string;
        };
        contacts?: Array<{
          profile: { name: string };
          wa_id: string;
        }>;
        messages?: Array<{
          from: string;
          id: string;
          timestamp: string;
          type: string;
          text?: { body: string };
          button?: { payload: string; text: string };
          interactive?: {
            type: string;
            button_reply?: { id: string; title: string };
            list_reply?: { id: string; title: string };
          };
        }>;
        statuses?: Array<{
          id: string;
          status: string;
          timestamp: string;
          recipient_id: string;
        }>;
      };
      field: string;
    }>;
  }>;
}

interface WhatsAppConfig {
  phoneNumberId: string;
  accessToken: string;
  verifyToken: string;
  businessAccountId?: string;
}

// ============================================
// WEBHOOK VERIFICATION
// ============================================

whatsappRoutes.get('/webhook/:organizationId', async (c) => {
  const organizationId = c.req.param('organizationId');
  const mode = c.req.query('hub.mode');
  const token = c.req.query('hub.verify_token');
  const challenge = c.req.query('hub.challenge');

  if (mode !== 'subscribe') {
    return c.text('Invalid mode', 403);
  }

  // Buscar configuración del canal
  const channel = await prisma.socialChannel.findFirst({
    where: {
      organizationId,
      type: 'WHATSAPP',
      isActive: true,
    },
  });

  if (!channel) {
    console.error(`[WhatsApp] No active channel for org ${organizationId}`);
    return c.text('Channel not found', 403);
  }

  const config = channel.config as unknown as WhatsAppConfig;

  if (token !== config.verifyToken) {
    console.error(`[WhatsApp] Invalid verify token`);
    return c.text('Invalid token', 403);
  }

  console.log(`[WhatsApp] Webhook verified for org ${organizationId}`);
  return c.text(challenge || '');
});

// ============================================
// WEBHOOK HANDLER
// ============================================

whatsappRoutes.post('/webhook/:organizationId', async (c) => {
  const organizationId = c.req.param('organizationId');

  try {
    const payload = await c.req.json<WhatsAppWebhookPayload>();

    // Verificar que es un mensaje de WhatsApp
    if (payload.object !== 'whatsapp_business_account') {
      return c.json({ status: 'ignored' });
    }

    // Buscar canal y organización
    const [channel, organization] = await Promise.all([
      prisma.socialChannel.findFirst({
        where: {
          organizationId,
          type: 'WHATSAPP',
          isActive: true,
        },
      }),
      prisma.organization.findUnique({
        where: { id: organizationId },
      }),
    ]);

    if (!channel || !organization) {
      console.error(`[WhatsApp] Channel or org not found: ${organizationId}`);
      return c.json({ status: 'error', message: 'Not found' }, 404);
    }

    const config = channel.config as unknown as WhatsAppConfig;

    // Procesar cada entrada
    for (const entry of payload.entry) {
      for (const change of entry.changes) {
        if (change.field !== 'messages') continue;

        const { messages, contacts } = change.value;
        if (!messages || messages.length === 0) continue;

        for (const message of messages) {
          // Extraer texto del mensaje
          let messageText = '';
          if (message.type === 'text' && message.text) {
            messageText = message.text.body;
          } else if (message.type === 'button' && message.button) {
            messageText = message.button.text;
          } else if (message.type === 'interactive' && message.interactive) {
            if (message.interactive.button_reply) {
              messageText = message.interactive.button_reply.title;
            } else if (message.interactive.list_reply) {
              messageText = message.interactive.list_reply.title;
            }
          } else {
            // Ignorar otros tipos de mensajes (imagen, audio, etc.)
            continue;
          }

          if (!messageText) continue;

          // Obtener información del contacto
          const contact = contacts?.find(c => c.wa_id === message.from);
          const userName = contact?.profile?.name;

          // Crear contexto para el motor de conversación
          const ctx: ConversationContext = {
            organizationId,
            organizationName: organization.name,
            organizationAddress: organization.address || undefined,
            organizationPhone: organization.phone || undefined,
            platform: 'WHATSAPP',
            externalId: message.from, // Número de WhatsApp del usuario
            socialChannelId: channel.id,
            userMessage: messageText,
            userName,
            userPhone: `+${message.from}`, // Formato E.164
          };

          // Procesar mensaje
          const response = await processMessage(ctx);

          // Enviar respuestas
          for (const text of response.messages) {
            await sendWhatsAppMessage(config, message.from, text, response.quickReplies);

            // Guardar mensaje saliente
            const conversation = await prisma.conversation.findFirst({
              where: {
                organizationId,
                platform: 'WHATSAPP',
                externalId: message.from,
                status: 'ACTIVE',
              },
            });

            if (conversation) {
              await saveOutboundMessage(conversation.id, text);
            }
          }
        }
      }
    }

    return c.json({ status: 'ok' });
  } catch (error) {
    console.error('[WhatsApp] Webhook error:', error);
    return c.json({ status: 'error' }, 500);
  }
});

// ============================================
// ENVIAR MENSAJE
// ============================================

async function sendWhatsAppMessage(
  config: WhatsAppConfig,
  to: string,
  text: string,
  quickReplies?: string[]
): Promise<string | null> {
  const url = `https://graph.facebook.com/v18.0/${config.phoneNumberId}/messages`;

  try {
    let body: Record<string, unknown>;

    if (quickReplies && quickReplies.length > 0 && quickReplies.length <= 3) {
      // Usar botones interactivos (máximo 3)
      body = {
        messaging_product: 'whatsapp',
        recipient_type: 'individual',
        to,
        type: 'interactive',
        interactive: {
          type: 'button',
          body: { text },
          action: {
            buttons: quickReplies.slice(0, 3).map((reply, i) => ({
              type: 'reply',
              reply: {
                id: `btn_${i}`,
                title: reply.slice(0, 20), // Máximo 20 caracteres
              },
            })),
          },
        },
      };
    } else if (quickReplies && quickReplies.length > 3) {
      // Usar lista (para más de 3 opciones)
      body = {
        messaging_product: 'whatsapp',
        recipient_type: 'individual',
        to,
        type: 'interactive',
        interactive: {
          type: 'list',
          body: { text },
          action: {
            button: 'Ver opciones',
            sections: [{
              title: 'Opciones',
              rows: quickReplies.slice(0, 10).map((reply, i) => ({
                id: `opt_${i}`,
                title: reply.slice(0, 24), // Máximo 24 caracteres
              })),
            }],
          },
        },
      };
    } else {
      // Mensaje de texto simple
      body = {
        messaging_product: 'whatsapp',
        recipient_type: 'individual',
        to,
        type: 'text',
        text: { body: text },
      };
    }

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${config.accessToken}`,
      },
      body: JSON.stringify(body),
    });

    const data = await response.json();

    if (!response.ok) {
      console.error('[WhatsApp] Send error:', data);
      return null;
    }

    return data.messages?.[0]?.id || null;
  } catch (error) {
    console.error('[WhatsApp] Send error:', error);
    return null;
  }
}

// ============================================
// HELPER: ENVIAR MENSAJE DESDE CÓDIGO
// ============================================

export async function sendWhatsAppNotification(
  organizationId: string,
  to: string,
  message: string
): Promise<boolean> {
  const channel = await prisma.socialChannel.findFirst({
    where: {
      organizationId,
      type: 'WHATSAPP',
      isActive: true,
    },
  });

  if (!channel) {
    console.error(`[WhatsApp] No active channel for org ${organizationId}`);
    return false;
  }

  const config = channel.config as unknown as WhatsAppConfig;

  // Formatear número (quitar + si existe)
  const formattedTo = to.replace(/^\+/, '');

  const messageId = await sendWhatsAppMessage(config, formattedTo, message);
  return messageId !== null;
}

// ============================================
// TEMPLATES DE WHATSAPP BUSINESS
// ============================================

export async function sendWhatsAppTemplate(
  organizationId: string,
  to: string,
  templateName: string,
  parameters: string[]
): Promise<boolean> {
  const channel = await prisma.socialChannel.findFirst({
    where: {
      organizationId,
      type: 'WHATSAPP',
      isActive: true,
    },
  });

  if (!channel) {
    return false;
  }

  const config = channel.config as unknown as WhatsAppConfig;
  const url = `https://graph.facebook.com/v18.0/${config.phoneNumberId}/messages`;
  const formattedTo = to.replace(/^\+/, '');

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${config.accessToken}`,
      },
      body: JSON.stringify({
        messaging_product: 'whatsapp',
        to: formattedTo,
        type: 'template',
        template: {
          name: templateName,
          language: { code: 'es' },
          components: parameters.length > 0 ? [{
            type: 'body',
            parameters: parameters.map(p => ({ type: 'text', text: p })),
          }] : undefined,
        },
      }),
    });

    return response.ok;
  } catch (error) {
    console.error('[WhatsApp] Template error:', error);
    return false;
  }
}
