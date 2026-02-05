/**
 * Motor de Conversación para Reservas
 *
 * Maneja el flujo de conversación para crear reservas desde
 * redes sociales (WhatsApp, Telegram, Messenger, Instagram).
 *
 * El flujo guía al usuario paso a paso para recopilar:
 * - Fecha de la reserva
 * - Hora
 * - Número de comensales
 * - Nombre
 * - Email/Teléfono de contacto
 * - Ocasión especial (opcional)
 * - Notas especiales (opcional)
 */

import { prisma } from '../../shared/database/client';
import { format, parse, addDays, isValid, isBefore, startOfDay } from 'date-fns';
import { es } from 'date-fns/locale';
import type { ConversationStep, SocialChannelType, Conversation } from '@prisma/client';

// ============================================
// TIPOS
// ============================================

export interface ConversationContext {
  organizationId: string;
  organizationName: string;
  organizationAddress?: string;
  organizationPhone?: string;
  platform: SocialChannelType;
  externalId: string; // Chat ID en la plataforma
  socialChannelId: string;
  userMessage: string;
  userName?: string; // Nombre del usuario si está disponible
  userPhone?: string; // Teléfono si viene de WhatsApp
}

export interface ConversationResponse {
  messages: string[];
  quickReplies?: string[];
  buttons?: Array<{ text: string; payload: string }>;
  completed?: boolean;
  bookingId?: string;
}

// ============================================
// MENSAJES DEL BOT
// ============================================

const MESSAGES = {
  GREETING: (restaurantName: string) =>
    `¡Hola! 👋 Soy el asistente de reservas de *${restaurantName}*.\n\n¿Te gustaría hacer una reserva?`,

  ASK_DATE: () =>
    `¿Para qué día te gustaría reservar?\n\nPuedes decirme:\n• "Hoy" o "Mañana"\n• "Viernes" o "Sábado"\n• Una fecha como "15 de febrero"`,

  ASK_TIME: (date: string) =>
    `Perfecto, *${date}* ✓\n\n¿A qué hora te gustaría venir?\n\nEjemplos: "20:30", "9 de la noche", "a las 2"`,

  ASK_GUESTS: (time: string) =>
    `*${time}* ✓\n\n¿Para cuántas personas?`,

  ASK_NAME: (guests: number) =>
    `*${guests} ${guests === 1 ? 'persona' : 'personas'}* ✓\n\n¿A nombre de quién hago la reserva?`,

  ASK_CONTACT: (name: string, hasPhone: boolean) =>
    hasPhone
      ? `Gracias *${name}* ✓\n\n¿Me das tu email para enviarte la confirmación?`
      : `Gracias *${name}* ✓\n\n¿Me das tu email o teléfono para enviarte la confirmación?`,

  ASK_OCCASION: () =>
    `¿Es alguna ocasión especial? (cumpleaños, aniversario, etc.)\n\nSi no, escribe "no" o "ninguna"`,

  ASK_NOTES: () =>
    `¿Tienes alguna petición especial? (alergias, silla de bebé, etc.)\n\nSi no, escribe "no" o "ninguna"`,

  CONFIRM: (data: {
    name: string;
    date: string;
    time: string;
    guests: number;
    occasion?: string;
    notes?: string;
    restaurant: string;
    address?: string;
  }) => {
    let msg = `📋 *Resumen de tu reserva:*\n\n`;
    msg += `📅 *Fecha:* ${data.date}\n`;
    msg += `🕐 *Hora:* ${data.time}\n`;
    msg += `👥 *Comensales:* ${data.guests}\n`;
    msg += `👤 *Nombre:* ${data.name}\n`;
    if (data.occasion && data.occasion !== 'no' && data.occasion !== 'ninguna') {
      msg += `🎉 *Ocasión:* ${data.occasion}\n`;
    }
    if (data.notes && data.notes !== 'no' && data.notes !== 'ninguna') {
      msg += `📝 *Notas:* ${data.notes}\n`;
    }
    msg += `\n📍 *${data.restaurant}*`;
    if (data.address) {
      msg += `\n${data.address}`;
    }
    msg += `\n\n¿Confirmamos la reserva?`;
    return msg;
  },

  COMPLETED: (editUrl: string) =>
    `✅ *¡Reserva confirmada!*\n\nTe hemos enviado un email con los detalles.\n\nSi necesitas modificar o cancelar tu reserva, puedes hacerlo aquí:\n${editUrl}\n\n¡Te esperamos! 🍽️`,

  CANCELLED: () =>
    `Vale, he cancelado el proceso de reserva. Si cambias de opinión, escríbeme de nuevo. 👋`,

  NOT_UNDERSTOOD: () =>
    `Lo siento, no he entendido tu respuesta. ¿Podrías repetirlo de otra forma?`,

  HELP: () =>
    `💡 *Ayuda*\n\nPuedo ayudarte a:\n• Hacer una reserva\n• Modificar una reserva existente\n• Cancelar una reserva\n\nEscribe "reservar" para comenzar una nueva reserva.`,

  DATE_INVALID: () =>
    `No he podido entender esa fecha. Prueba con:\n• "Hoy" o "Mañana"\n• "Viernes" o "Sábado"\n• "15 de febrero"`,

  TIME_INVALID: () =>
    `No he entendido la hora. Prueba con:\n• "20:30" o "21:00"\n• "9 de la noche"\n• "a las 2 y media"`,

  GUESTS_INVALID: () =>
    `Por favor, indícame un número válido de personas (ej: "2", "4 personas")`,

  DATE_PAST: () =>
    `Esa fecha ya ha pasado. Por favor, elige una fecha futura.`,

  NO_AVAILABILITY: () =>
    `Lo siento, no tenemos disponibilidad en ese horario. ¿Te gustaría probar con otra hora o fecha?`,

  EMAIL_INVALID: () =>
    `Ese email no parece válido. ¿Podrías verificarlo?`,
};

// ============================================
// MOTOR DE CONVERSACIÓN
// ============================================

export async function processMessage(
  ctx: ConversationContext
): Promise<ConversationResponse> {
  // Buscar o crear conversación
  let conversation = await findOrCreateConversation(ctx);

  // Guardar mensaje entrante
  await saveMessage(conversation.id, 'INBOUND', ctx.userMessage);

  // Detectar intenciones especiales
  const intent = detectIntent(ctx.userMessage);

  if (intent === 'CANCEL') {
    await updateConversation(conversation.id, {
      status: 'CANCELLED',
      step: 'CANCEL',
    });
    return createResponse([MESSAGES.CANCELLED()]);
  }

  if (intent === 'HELP') {
    return createResponse([MESSAGES.HELP()]);
  }

  if (intent === 'START' && conversation.step !== 'GREETING') {
    // Reiniciar conversación
    conversation = await resetConversation(conversation.id);
  }

  // Procesar según el paso actual
  return processStep(conversation, ctx);
}

async function processStep(
  conversation: Conversation,
  ctx: ConversationContext
): Promise<ConversationResponse> {
  const { step } = conversation;

  switch (step) {
    case 'GREETING':
      return handleGreeting(conversation, ctx);

    case 'ASK_DATE':
      return handleDate(conversation, ctx);

    case 'ASK_TIME':
      return handleTime(conversation, ctx);

    case 'ASK_GUESTS':
      return handleGuests(conversation, ctx);

    case 'ASK_NAME':
      return handleName(conversation, ctx);

    case 'ASK_CONTACT':
      return handleContact(conversation, ctx);

    case 'ASK_OCCASION':
      return handleOccasion(conversation, ctx);

    case 'ASK_NOTES':
      return handleNotes(conversation, ctx);

    case 'CONFIRM':
      return handleConfirm(conversation, ctx);

    default:
      return createResponse([MESSAGES.HELP()]);
  }
}

// ============================================
// HANDLERS POR PASO
// ============================================

async function handleGreeting(
  conversation: Conversation,
  ctx: ConversationContext
): Promise<ConversationResponse> {
  const wantsToBook = detectAffirmative(ctx.userMessage) ||
    ctx.userMessage.toLowerCase().includes('reserv');

  if (wantsToBook) {
    await updateConversation(conversation.id, { step: 'ASK_DATE' });
    return createResponse(
      [MESSAGES.ASK_DATE()],
      ['Hoy', 'Mañana', 'Este viernes', 'Este sábado']
    );
  }

  // Si es el primer mensaje, mostrar saludo
  const messageCount = await prisma.conversationMessage.count({
    where: { conversationId: conversation.id },
  });

  if (messageCount <= 1) {
    return createResponse(
      [MESSAGES.GREETING(ctx.organizationName)],
      ['Sí, quiero reservar', 'Ver disponibilidad']
    );
  }

  return createResponse([MESSAGES.HELP()]);
}

async function handleDate(
  conversation: Conversation,
  ctx: ConversationContext
): Promise<ConversationResponse> {
  const parsedDate = parseDate(ctx.userMessage);

  if (!parsedDate) {
    return createResponse([MESSAGES.DATE_INVALID()]);
  }

  if (isBefore(parsedDate, startOfDay(new Date()))) {
    return createResponse([MESSAGES.DATE_PAST()]);
  }

  const dateStr = format(parsedDate, "EEEE d 'de' MMMM", { locale: es });

  await updateConversation(conversation.id, {
    step: 'ASK_TIME',
    bookingDate: parsedDate,
  });

  return createResponse(
    [MESSAGES.ASK_TIME(dateStr)],
    ['13:00', '14:00', '20:30', '21:00', '21:30']
  );
}

async function handleTime(
  conversation: Conversation,
  ctx: ConversationContext
): Promise<ConversationResponse> {
  const parsedTime = parseTime(ctx.userMessage);

  if (!parsedTime) {
    return createResponse([MESSAGES.TIME_INVALID()]);
  }

  await updateConversation(conversation.id, {
    step: 'ASK_GUESTS',
    bookingTime: parsedTime,
  });

  return createResponse(
    [MESSAGES.ASK_GUESTS(parsedTime)],
    ['2 personas', '3 personas', '4 personas', '5 personas', '6 o más']
  );
}

async function handleGuests(
  conversation: Conversation,
  ctx: ConversationContext
): Promise<ConversationResponse> {
  const guests = parseGuests(ctx.userMessage);

  if (!guests || guests < 1 || guests > 50) {
    return createResponse([MESSAGES.GUESTS_INVALID()]);
  }

  await updateConversation(conversation.id, {
    step: 'ASK_NAME',
    guests,
  });

  return createResponse([MESSAGES.ASK_NAME(guests)]);
}

async function handleName(
  conversation: Conversation,
  ctx: ConversationContext
): Promise<ConversationResponse> {
  const name = ctx.userMessage.trim();

  if (name.length < 2) {
    return createResponse(['Por favor, dime tu nombre completo']);
  }

  // Si ya tenemos teléfono de WhatsApp, solo pedir email
  const hasPhone = !!ctx.userPhone || !!conversation.customerPhone;

  if (ctx.userPhone && !conversation.customerPhone) {
    await updateConversation(conversation.id, {
      step: 'ASK_CONTACT',
      customerName: name,
      customerPhone: ctx.userPhone,
    });
  } else {
    await updateConversation(conversation.id, {
      step: 'ASK_CONTACT',
      customerName: name,
    });
  }

  return createResponse([MESSAGES.ASK_CONTACT(name, hasPhone)]);
}

async function handleContact(
  conversation: Conversation,
  ctx: ConversationContext
): Promise<ConversationResponse> {
  const input = ctx.userMessage.trim();

  // Detectar si es email o teléfono
  const email = extractEmail(input);
  const phone = extractPhone(input);

  if (!email && !phone) {
    return createResponse([MESSAGES.EMAIL_INVALID()]);
  }

  const updates: Partial<Conversation> = {
    step: 'ASK_OCCASION' as ConversationStep,
  };

  if (email) updates.customerEmail = email;
  if (phone && !conversation.customerPhone) updates.customerPhone = phone;

  await updateConversation(conversation.id, updates);

  return createResponse(
    [MESSAGES.ASK_OCCASION()],
    ['Cumpleaños', 'Aniversario', 'Negocios', 'Ninguna']
  );
}

async function handleOccasion(
  conversation: Conversation,
  ctx: ConversationContext
): Promise<ConversationResponse> {
  const occasion = ctx.userMessage.trim();

  await updateConversation(conversation.id, {
    step: 'ASK_NOTES',
    occasion: occasion.toLowerCase() === 'ninguna' ? null : occasion,
  });

  return createResponse(
    [MESSAGES.ASK_NOTES()],
    ['Ninguna', 'Alergia gluten', 'Silla de bebé', 'Mesa exterior']
  );
}

async function handleNotes(
  conversation: Conversation,
  ctx: ConversationContext
): Promise<ConversationResponse> {
  const notes = ctx.userMessage.trim();

  // Obtener conversación actualizada con todos los datos
  const conv = await prisma.conversation.findUnique({
    where: { id: conversation.id },
  });

  if (!conv) {
    return createResponse([MESSAGES.NOT_UNDERSTOOD()]);
  }

  await updateConversation(conversation.id, {
    step: 'CONFIRM',
    specialRequests: notes.toLowerCase() === 'ninguna' ? null : notes,
  });

  // Mostrar resumen
  const dateStr = conv.bookingDate
    ? format(conv.bookingDate, "EEEE d 'de' MMMM", { locale: es })
    : '';

  return createResponse(
    [MESSAGES.CONFIRM({
      name: conv.customerName || '',
      date: dateStr,
      time: conv.bookingTime || '',
      guests: conv.guests || 0,
      occasion: conv.occasion || undefined,
      notes: notes.toLowerCase() === 'ninguna' ? undefined : notes,
      restaurant: ctx.organizationName,
      address: ctx.organizationAddress,
    })],
    ['Sí, confirmar', 'No, cancelar']
  );
}

async function handleConfirm(
  conversation: Conversation,
  ctx: ConversationContext
): Promise<ConversationResponse> {
  const isConfirmed = detectAffirmative(ctx.userMessage);

  if (!isConfirmed) {
    await updateConversation(conversation.id, {
      status: 'CANCELLED',
      step: 'CANCEL',
    });
    return createResponse([MESSAGES.CANCELLED()]);
  }

  // Obtener datos actualizados
  const conv = await prisma.conversation.findUnique({
    where: { id: conversation.id },
  });

  if (!conv || !conv.bookingDate || !conv.bookingTime || !conv.customerName) {
    return createResponse([MESSAGES.NOT_UNDERSTOOD()]);
  }

  // Crear la reserva
  const editToken = generateEditToken();

  const booking = await prisma.booking.create({
    data: {
      organizationId: ctx.organizationId,
      customerName: conv.customerName,
      customerEmail: conv.customerEmail || '',
      customerPhone: conv.customerPhone,
      bookingDate: conv.bookingDate,
      bookingTime: conv.bookingTime,
      guests: conv.guests || 2,
      occasion: conv.occasion,
      specialRequests: conv.specialRequests,
      status: 'PENDING',
      source: `social:${ctx.platform.toLowerCase()}`,
      editToken,
      metadata: {
        conversationId: conversation.id,
        platform: ctx.platform,
      },
    },
  });

  // Actualizar conversación como completada
  await updateConversation(conversation.id, {
    status: 'COMPLETED',
    step: 'COMPLETED',
    bookingId: booking.id,
  });

  const editUrl = `${process.env.APP_URL || 'https://contacpro.app'}/reserva/${editToken}`;

  return {
    messages: [MESSAGES.COMPLETED(editUrl)],
    completed: true,
    bookingId: booking.id,
  };
}

// ============================================
// UTILIDADES
// ============================================

async function findOrCreateConversation(
  ctx: ConversationContext
): Promise<Conversation> {
  // Buscar conversación activa existente
  const existing = await prisma.conversation.findFirst({
    where: {
      organizationId: ctx.organizationId,
      platform: ctx.platform,
      externalId: ctx.externalId,
      status: 'ACTIVE',
    },
  });

  if (existing) {
    // Actualizar última actividad
    return prisma.conversation.update({
      where: { id: existing.id },
      data: { lastActivityAt: new Date() },
    });
  }

  // Crear nueva conversación
  return prisma.conversation.create({
    data: {
      organizationId: ctx.organizationId,
      socialChannelId: ctx.socialChannelId,
      platform: ctx.platform,
      externalId: ctx.externalId,
      status: 'ACTIVE',
      step: 'GREETING',
      customerName: ctx.userName,
      customerPhone: ctx.userPhone,
    },
  });
}

async function updateConversation(
  id: string,
  data: Partial<Conversation>
): Promise<Conversation> {
  return prisma.conversation.update({
    where: { id },
    data: {
      ...data,
      updatedAt: new Date(),
    },
  });
}

async function resetConversation(id: string): Promise<Conversation> {
  return prisma.conversation.update({
    where: { id },
    data: {
      step: 'ASK_DATE',
      customerName: null,
      customerEmail: null,
      customerPhone: null,
      bookingDate: null,
      bookingTime: null,
      guests: null,
      occasion: null,
      specialRequests: null,
      updatedAt: new Date(),
    },
  });
}

async function saveMessage(
  conversationId: string,
  direction: 'INBOUND' | 'OUTBOUND',
  content: string
): Promise<void> {
  await prisma.conversationMessage.create({
    data: {
      conversationId,
      direction,
      content,
      messageType: 'TEXT',
    },
  });
}

export async function saveOutboundMessage(
  conversationId: string,
  content: string,
  externalId?: string
): Promise<void> {
  await prisma.conversationMessage.create({
    data: {
      conversationId,
      direction: 'OUTBOUND',
      content,
      messageType: 'TEXT',
      externalId,
    },
  });
}

function createResponse(
  messages: string[],
  quickReplies?: string[]
): ConversationResponse {
  return { messages, quickReplies };
}

function detectIntent(message: string): 'CANCEL' | 'HELP' | 'START' | null {
  const lower = message.toLowerCase().trim();

  if (['cancelar', 'salir', 'no quiero', 'dejalo', 'olvidalo'].some(w => lower.includes(w))) {
    return 'CANCEL';
  }

  if (['ayuda', 'help', 'no entiendo', 'como funciona'].some(w => lower.includes(w))) {
    return 'HELP';
  }

  if (['nueva reserva', 'reservar', 'empezar', 'comenzar', 'inicio'].some(w => lower.includes(w))) {
    return 'START';
  }

  return null;
}

function detectAffirmative(message: string): boolean {
  const lower = message.toLowerCase().trim();
  return ['si', 'sí', 'ok', 'vale', 'claro', 'confirmar', 'adelante', 'perfecto', 'correcto', 'yes', 'confirm']
    .some(w => lower.includes(w));
}

function parseDate(input: string): Date | null {
  const lower = input.toLowerCase().trim();
  const today = new Date();

  // Palabras clave
  if (lower === 'hoy') return today;
  if (lower === 'mañana') return addDays(today, 1);
  if (lower === 'pasado mañana') return addDays(today, 2);

  // Días de la semana
  const weekDays: Record<string, number> = {
    'domingo': 0, 'lunes': 1, 'martes': 2, 'miercoles': 3, 'miércoles': 3,
    'jueves': 4, 'viernes': 5, 'sabado': 6, 'sábado': 6,
  };

  for (const [day, num] of Object.entries(weekDays)) {
    if (lower.includes(day)) {
      const currentDay = today.getDay();
      let daysUntil = num - currentDay;
      if (daysUntil <= 0) daysUntil += 7;
      return addDays(today, daysUntil);
    }
  }

  // Intentar parsear fecha específica
  const datePatterns = [
    /(\d{1,2})\s*(?:de\s*)?(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)/i,
    /(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?/,
  ];

  const months: Record<string, number> = {
    'enero': 0, 'febrero': 1, 'marzo': 2, 'abril': 3, 'mayo': 4, 'junio': 5,
    'julio': 6, 'agosto': 7, 'septiembre': 8, 'octubre': 9, 'noviembre': 10, 'diciembre': 11,
  };

  // Patrón "15 de febrero"
  const match1 = lower.match(datePatterns[0]);
  if (match1) {
    const day = parseInt(match1[1]);
    const month = months[match1[2].toLowerCase()];
    const year = today.getFullYear();
    const date = new Date(year, month, day);
    // Si la fecha ya pasó, asumir próximo año
    if (isBefore(date, today)) {
      date.setFullYear(year + 1);
    }
    return date;
  }

  // Patrón "15/02" o "15-02-2024"
  const match2 = lower.match(datePatterns[1]);
  if (match2) {
    const day = parseInt(match2[1]);
    const month = parseInt(match2[2]) - 1;
    const year = match2[3] ? parseInt(match2[3]) : today.getFullYear();
    return new Date(year, month, day);
  }

  return null;
}

function parseTime(input: string): string | null {
  const lower = input.toLowerCase().trim();

  // Formato directo "20:30" o "20.30"
  const directMatch = lower.match(/^(\d{1,2})[:.](\d{2})$/);
  if (directMatch) {
    const hours = parseInt(directMatch[1]);
    const mins = parseInt(directMatch[2]);
    if (hours >= 0 && hours <= 23 && mins >= 0 && mins <= 59) {
      return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
    }
  }

  // "a las 9" o "9 de la noche"
  const timeMatch = lower.match(/(?:a las?\s*)?(\d{1,2})(?:\s*(?:y\s*media|:30))?(?:\s*(?:de la\s*)?(noche|tarde|mañana))?/);
  if (timeMatch) {
    let hours = parseInt(timeMatch[1]);
    const period = timeMatch[2];
    const hasHalf = lower.includes('media') || lower.includes(':30');

    if (period === 'noche' && hours < 12) hours += 12;
    if (period === 'tarde' && hours < 12 && hours !== 12) hours += 12;

    if (hours >= 0 && hours <= 23) {
      return `${hours.toString().padStart(2, '0')}:${hasHalf ? '30' : '00'}`;
    }
  }

  return null;
}

function parseGuests(input: string): number | null {
  const lower = input.toLowerCase().trim();

  // Extraer número
  const match = lower.match(/(\d+)/);
  if (match) {
    return parseInt(match[1]);
  }

  // Palabras numéricas
  const words: Record<string, number> = {
    'uno': 1, 'una': 1, 'dos': 2, 'tres': 3, 'cuatro': 4,
    'cinco': 5, 'seis': 6, 'siete': 7, 'ocho': 8, 'nueve': 9, 'diez': 10,
  };

  for (const [word, num] of Object.entries(words)) {
    if (lower.includes(word)) {
      return num;
    }
  }

  return null;
}

function extractEmail(input: string): string | null {
  const match = input.match(/[^\s@]+@[^\s@]+\.[^\s@]+/);
  return match ? match[0].toLowerCase() : null;
}

function extractPhone(input: string): string | null {
  // Limpiar y buscar teléfono
  const cleaned = input.replace(/[\s\-\(\)]/g, '');
  const match = cleaned.match(/(\+?\d{9,15})/);
  return match ? match[1] : null;
}

function generateEditToken(): string {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
  let token = '';
  for (let i = 0; i < 16; i++) {
    token += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return token;
}

// ============================================
// LIMPIAR CONVERSACIONES EXPIRADAS
// ============================================

export async function cleanupExpiredConversations(): Promise<number> {
  const expirationTime = new Date();
  expirationTime.setHours(expirationTime.getHours() - 24); // 24h de inactividad

  const result = await prisma.conversation.updateMany({
    where: {
      status: 'ACTIVE',
      lastActivityAt: { lt: expirationTime },
    },
    data: { status: 'EXPIRED' },
  });

  return result.count;
}
