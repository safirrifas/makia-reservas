import { Hono } from 'hono';
import { z } from 'zod';
import { zValidator } from '@hono/zod-validator';
import { prisma } from '../../shared/database/client';
import { authMiddleware } from '../../shared/middleware/auth';
import type { Variables } from '../../shared/types';

export const templatesRoutes = new Hono<{ Variables: Variables }>();

// Todas las rutas requieren autenticación
templatesRoutes.use('*', authMiddleware);

// ============================================
// TIPOS Y CONSTANTES
// ============================================

const NOTIFICATION_TYPES = ['EMAIL', 'SMS', 'WHATSAPP', 'PUSH'] as const;
const NOTIFICATION_EVENTS = [
  'BOOKING_RECEIVED',
  'BOOKING_CONFIRMED',
  'BOOKING_CANCELLED',
  'BOOKING_REMINDER',
  'BOOKING_MODIFIED',
  'ADMIN_NEW_BOOKING',
] as const;

// Variables disponibles por evento
const TEMPLATE_VARIABLES: Record<string, Record<string, string>> = {
  BOOKING_RECEIVED: {
    nombre: 'Nombre del cliente',
    email: 'Email del cliente',
    telefono: 'Teléfono del cliente',
    fecha: 'Fecha de la reserva (ej: 15 de febrero)',
    hora: 'Hora de la reserva (ej: 20:30)',
    comensales: 'Número de comensales',
    ocasion: 'Ocasión especial',
    notas: 'Notas/peticiones especiales',
    restaurante: 'Nombre del restaurante',
    enlace_gestion: 'Enlace para modificar/cancelar reserva',
  },
  BOOKING_CONFIRMED: {
    nombre: 'Nombre del cliente',
    fecha: 'Fecha de la reserva',
    hora: 'Hora de la reserva',
    comensales: 'Número de comensales',
    restaurante: 'Nombre del restaurante',
    direccion: 'Dirección del restaurante',
    telefono_restaurante: 'Teléfono del restaurante',
    enlace_gestion: 'Enlace para modificar/cancelar reserva',
  },
  BOOKING_CANCELLED: {
    nombre: 'Nombre del cliente',
    fecha: 'Fecha de la reserva',
    hora: 'Hora de la reserva',
    restaurante: 'Nombre del restaurante',
    enlace_nueva_reserva: 'Enlace para hacer nueva reserva',
  },
  BOOKING_REMINDER: {
    nombre: 'Nombre del cliente',
    fecha: 'Fecha de la reserva',
    hora: 'Hora de la reserva',
    comensales: 'Número de comensales',
    restaurante: 'Nombre del restaurante',
    direccion: 'Dirección del restaurante',
    enlace_gestion: 'Enlace para modificar/cancelar reserva',
  },
  BOOKING_MODIFIED: {
    nombre: 'Nombre del cliente',
    fecha_anterior: 'Fecha anterior',
    hora_anterior: 'Hora anterior',
    fecha_nueva: 'Nueva fecha',
    hora_nueva: 'Nueva hora',
    comensales: 'Número de comensales',
    restaurante: 'Nombre del restaurante',
  },
  ADMIN_NEW_BOOKING: {
    nombre: 'Nombre del cliente',
    email: 'Email del cliente',
    telefono: 'Teléfono del cliente',
    fecha: 'Fecha de la reserva',
    hora: 'Hora de la reserva',
    comensales: 'Número de comensales',
    ocasion: 'Ocasión especial',
    notas: 'Notas/peticiones especiales',
    enlace_admin: 'Enlace al panel de administración',
  },
};

// Plantillas por defecto
const DEFAULT_TEMPLATES = {
  EMAIL: {
    BOOKING_RECEIVED: {
      name: 'Email - Reserva recibida',
      subject: 'Hemos recibido tu reserva en {restaurante}',
      body: `Hola {nombre},

Hemos recibido tu solicitud de reserva con los siguientes datos:

📅 Fecha: {fecha}
🕐 Hora: {hora}
👥 Comensales: {comensales}
${'{ocasion}' ? '🎉 Ocasión: {ocasion}' : ''}

Tu reserva está pendiente de confirmación. Te enviaremos un email cuando sea confirmada.

Si necesitas modificar o cancelar tu reserva, puedes hacerlo aquí:
{enlace_gestion}

¡Gracias por elegirnos!

{restaurante}`,
    },
    BOOKING_CONFIRMED: {
      name: 'Email - Reserva confirmada',
      subject: '✅ Tu reserva en {restaurante} está confirmada',
      body: `¡Hola {nombre}!

¡Tu reserva ha sido confirmada! Te esperamos:

📅 Fecha: {fecha}
🕐 Hora: {hora}
👥 Comensales: {comensales}

📍 Dirección: {direccion}
📞 Teléfono: {telefono_restaurante}

Si necesitas modificar o cancelar tu reserva:
{enlace_gestion}

¡Te esperamos!

{restaurante}`,
    },
    BOOKING_CANCELLED: {
      name: 'Email - Reserva cancelada',
      subject: 'Tu reserva en {restaurante} ha sido cancelada',
      body: `Hola {nombre},

Tu reserva para el {fecha} a las {hora} ha sido cancelada.

Si deseas hacer una nueva reserva, puedes hacerlo aquí:
{enlace_nueva_reserva}

Esperamos verte pronto.

{restaurante}`,
    },
    BOOKING_REMINDER: {
      name: 'Email - Recordatorio de reserva',
      subject: '⏰ Recordatorio: Tu reserva mañana en {restaurante}',
      body: `¡Hola {nombre}!

Te recordamos que tienes una reserva mañana:

📅 Fecha: {fecha}
🕐 Hora: {hora}
👥 Comensales: {comensales}

📍 Dirección: {direccion}

Si necesitas modificar o cancelar:
{enlace_gestion}

¡Te esperamos!

{restaurante}`,
    },
    ADMIN_NEW_BOOKING: {
      name: 'Email - Nueva reserva (Admin)',
      subject: '🔔 Nueva reserva: {nombre} - {fecha} {hora}',
      body: `Nueva reserva recibida:

👤 Cliente: {nombre}
📧 Email: {email}
📞 Teléfono: {telefono}

📅 Fecha: {fecha}
🕐 Hora: {hora}
👥 Comensales: {comensales}
${'{ocasion}' ? '🎉 Ocasión: {ocasion}' : ''}
${'{notas}' ? '📝 Notas: {notas}' : ''}

Gestionar reserva: {enlace_admin}`,
    },
  },
  SMS: {
    BOOKING_RECEIVED: {
      name: 'SMS - Reserva recibida',
      subject: null,
      body: '{restaurante}: Reserva recibida para {fecha} {hora}. Pendiente de confirmación.',
    },
    BOOKING_CONFIRMED: {
      name: 'SMS - Reserva confirmada',
      subject: null,
      body: '✅ {restaurante}: Tu reserva para {fecha} a las {hora} está confirmada. ¡Te esperamos!',
    },
    BOOKING_REMINDER: {
      name: 'SMS - Recordatorio',
      subject: null,
      body: '⏰ {restaurante}: Recordatorio de tu reserva mañana {fecha} a las {hora}.',
    },
  },
  WHATSAPP: {
    BOOKING_CONFIRMED: {
      name: 'WhatsApp - Reserva confirmada',
      subject: null,
      body: `¡Hola {nombre}! 👋

Tu reserva en *{restaurante}* está *confirmada* ✅

📅 *{fecha}*
🕐 *{hora}*
👥 *{comensales} personas*

📍 {direccion}

¡Te esperamos! 🍽️`,
    },
    BOOKING_REMINDER: {
      name: 'WhatsApp - Recordatorio',
      subject: null,
      body: `¡Hola {nombre}! 👋

Te recordamos tu reserva *mañana* en *{restaurante}*:

📅 {fecha}
🕐 {hora}

¡Te esperamos! 🍽️`,
    },
  },
};

// ============================================
// SCHEMAS
// ============================================

const updateTemplateSchema = z.object({
  subject: z.string().optional(),
  body: z.string().min(1),
  isActive: z.boolean().optional(),
});

// ============================================
// ROUTES
// ============================================

// Listar todas las plantillas
templatesRoutes.get('/', async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  // Obtener plantillas existentes
  let templates = await prisma.notificationTemplate.findMany({
    where: { organizationId: organization.id },
    orderBy: [{ type: 'asc' }, { event: 'asc' }],
  });

  // Si no hay plantillas, crear las por defecto
  if (templates.length === 0) {
    await createDefaultTemplates(organization.id);
    templates = await prisma.notificationTemplate.findMany({
      where: { organizationId: organization.id },
      orderBy: [{ type: 'asc' }, { event: 'asc' }],
    });
  }

  // Agrupar por tipo
  const grouped = {
    EMAIL: templates.filter((t) => t.type === 'EMAIL'),
    SMS: templates.filter((t) => t.type === 'SMS'),
    WHATSAPP: templates.filter((t) => t.type === 'WHATSAPP'),
    PUSH: templates.filter((t) => t.type === 'PUSH'),
  };

  return c.json({
    success: true,
    data: grouped,
    meta: {
      availableVariables: TEMPLATE_VARIABLES,
    },
  });
});

// Obtener una plantilla específica
templatesRoutes.get('/:type/:event', async (c) => {
  const organization = c.get('organization');
  const type = c.req.param('type').toUpperCase();
  const event = c.req.param('event').toUpperCase();

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const template = await prisma.notificationTemplate.findFirst({
    where: {
      organizationId: organization.id,
      type: type as any,
      event: event as any,
    },
  });

  if (!template) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Template not found' },
    }, 404);
  }

  return c.json({
    success: true,
    data: template,
    meta: {
      variables: TEMPLATE_VARIABLES[event] || {},
    },
  });
});

// Actualizar plantilla
templatesRoutes.patch('/:id', zValidator('json', updateTemplateSchema), async (c) => {
  const organization = c.get('organization');
  const user = c.get('user')!;
  const templateId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  if (user.role !== 'OWNER' && user.role !== 'ADMIN') {
    return c.json({
      success: false,
      error: { code: 'FORBIDDEN', message: 'Only owners and admins can update templates' },
    }, 403);
  }

  const data = c.req.valid('json');

  const updated = await prisma.notificationTemplate.updateMany({
    where: {
      id: templateId,
      organizationId: organization.id,
    },
    data: {
      ...data,
      isDefault: false, // Ya no es default si se modifica
    },
  });

  if (updated.count === 0) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Template not found' },
    }, 404);
  }

  const template = await prisma.notificationTemplate.findUnique({
    where: { id: templateId },
  });

  return c.json({ success: true, data: template });
});

// Restaurar plantilla a valores por defecto
templatesRoutes.post('/:id/restore', async (c) => {
  const organization = c.get('organization');
  const user = c.get('user')!;
  const templateId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  if (user.role !== 'OWNER' && user.role !== 'ADMIN') {
    return c.json({
      success: false,
      error: { code: 'FORBIDDEN', message: 'Only owners and admins can restore templates' },
    }, 403);
  }

  // Obtener plantilla actual
  const template = await prisma.notificationTemplate.findFirst({
    where: {
      id: templateId,
      organizationId: organization.id,
    },
  });

  if (!template) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Template not found' },
    }, 404);
  }

  // Buscar plantilla por defecto
  const defaultTemplate = (DEFAULT_TEMPLATES as any)[template.type]?.[template.event];

  if (!defaultTemplate) {
    return c.json({
      success: false,
      error: { code: 'NO_DEFAULT', message: 'No default template available for this type/event' },
    }, 400);
  }

  // Restaurar
  const restored = await prisma.notificationTemplate.update({
    where: { id: templateId },
    data: {
      subject: defaultTemplate.subject,
      body: defaultTemplate.body,
      isDefault: true,
    },
  });

  return c.json({ success: true, data: restored });
});

// Restaurar todas las plantillas a valores por defecto
templatesRoutes.post('/restore-all', async (c) => {
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
      error: { code: 'FORBIDDEN', message: 'Only owners and admins can restore templates' },
    }, 403);
  }

  // Eliminar plantillas existentes
  await prisma.notificationTemplate.deleteMany({
    where: { organizationId: organization.id },
  });

  // Crear nuevas con valores por defecto
  await createDefaultTemplates(organization.id);

  const templates = await prisma.notificationTemplate.findMany({
    where: { organizationId: organization.id },
    orderBy: [{ type: 'asc' }, { event: 'asc' }],
  });

  return c.json({
    success: true,
    data: templates,
    message: 'All templates restored to defaults',
  });
});

// Previsualizar plantilla con datos de ejemplo
templatesRoutes.post('/:id/preview', async (c) => {
  const organization = c.get('organization');
  const templateId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const template = await prisma.notificationTemplate.findFirst({
    where: {
      id: templateId,
      organizationId: organization.id,
    },
  });

  if (!template) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Template not found' },
    }, 404);
  }

  // Datos de ejemplo
  const sampleData: Record<string, string> = {
    nombre: 'Juan García',
    email: 'juan@ejemplo.com',
    telefono: '+34 612 345 678',
    fecha: '15 de febrero de 2024',
    hora: '20:30',
    comensales: '4',
    ocasion: 'Cumpleaños',
    notas: 'Mesa cerca de la ventana si es posible',
    restaurante: organization.name,
    direccion: organization.address || 'Calle Principal 123, Madrid',
    telefono_restaurante: organization.phone || '+34 912 345 678',
    enlace_gestion: 'https://example.com/reserva/abc123',
    enlace_nueva_reserva: 'https://example.com/reservar',
    enlace_admin: 'https://dashboard.contacpro.app/bookings/abc123',
    fecha_anterior: '14 de febrero de 2024',
    hora_anterior: '21:00',
    fecha_nueva: '15 de febrero de 2024',
    hora_nueva: '20:30',
  };

  // Procesar plantilla
  let processedSubject = template.subject || '';
  let processedBody = template.body;

  for (const [key, value] of Object.entries(sampleData)) {
    const regex = new RegExp(`\\{${key}\\}`, 'g');
    processedSubject = processedSubject.replace(regex, value);
    processedBody = processedBody.replace(regex, value);
  }

  return c.json({
    success: true,
    data: {
      subject: processedSubject,
      body: processedBody,
      sampleData,
    },
  });
});

// ============================================
// HELPERS
// ============================================

async function createDefaultTemplates(organizationId: string): Promise<void> {
  const templates: Array<{
    organizationId: string;
    type: string;
    event: string;
    name: string;
    subject: string | null;
    body: string;
    variables: object;
    isDefault: boolean;
  }> = [];

  for (const [type, events] of Object.entries(DEFAULT_TEMPLATES)) {
    for (const [event, template] of Object.entries(events)) {
      templates.push({
        organizationId,
        type,
        event,
        name: template.name,
        subject: template.subject,
        body: template.body,
        variables: TEMPLATE_VARIABLES[event] || {},
        isDefault: true,
      });
    }
  }

  await prisma.notificationTemplate.createMany({
    data: templates as any,
    skipDuplicates: true,
  });
}

/**
 * Procesar plantilla con variables reales
 */
export function processTemplate(
  template: { subject?: string | null; body: string },
  variables: Record<string, string>
): { subject: string; body: string } {
  let subject = template.subject || '';
  let body = template.body;

  for (const [key, value] of Object.entries(variables)) {
    const regex = new RegExp(`\\{${key}\\}`, 'g');
    subject = subject.replace(regex, value || '');
    body = body.replace(regex, value || '');
  }

  return { subject, body };
}
