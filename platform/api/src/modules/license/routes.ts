/**
 * MakIA Restaurante - License Management API
 *
 * Endpoints para gestión de licencias, facturación, soporte y notificaciones
 */

import { Hono } from 'hono';
import { HTTPException } from 'hono/http-exception';

// Tipos
interface LicenseData {
  status: 'active' | 'inactive' | 'trial' | 'expired' | 'cancelled';
  plan: 'free' | 'starter' | 'pro' | 'business';
  plan_name: string;
  expires_at: string | null;
  features: string[];
  usage: {
    reservations: number;
    reservations_limit: number;
    sms_sent: number;
    sms_limit: number;
    email_sent: number;
    email_limit: number;
  };
  organization: {
    id: string;
    name: string;
    email: string;
  };
}

interface BillingInfo {
  payment_method: {
    brand: string;
    last4: string;
    exp_month: number;
    exp_year: number;
  } | null;
  billing_info: {
    name: string;
    tax_id: string;
    address: string;
    city: string;
    postal_code: string;
    country: string;
    email: string;
  } | null;
  next_invoice: {
    amount: number;
    date: string;
    plan_name: string;
  } | null;
}

interface Invoice {
  id: string;
  number: string;
  date: string;
  amount: number;
  status: 'paid' | 'pending' | 'failed';
  pdf_url: string;
}

interface SupportTicket {
  id: string;
  subject: string;
  category: string;
  priority: string;
  status: 'open' | 'pending' | 'resolved' | 'closed';
  created_at: string;
  updated_at: string;
}

interface Notification {
  id: string;
  type: 'feature' | 'improvement' | 'maintenance' | 'announcement';
  title: string;
  content: string;
  read: boolean;
  created_at: string;
}

// Plan limits configuration
const PLAN_LIMITS = {
  free: {
    reservations: 50,
    emails: 100,
    sms: 0,
    features: ['widget_basic', 'email_notifications']
  },
  starter: {
    reservations: 200,
    emails: 500,
    sms: 50,
    features: ['widget_custom', 'email_notifications', 'sms_notifications', 'reminders']
  },
  pro: {
    reservations: -1, // unlimited
    emails: 2000,
    sms: 200,
    features: ['widget_custom', 'email_notifications', 'sms_notifications', 'reminders', 'whatsapp', 'telegram', 'analytics']
  },
  business: {
    reservations: -1,
    emails: -1,
    sms: -1,
    features: ['widget_custom', 'email_notifications', 'sms_notifications', 'reminders', 'whatsapp', 'telegram', 'analytics', 'multi_location', 'api_access', 'white_label']
  }
};

const license = new Hono();

/**
 * GET /license/current
 * Obtener información de la licencia actual
 */
license.get('/current', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');

  if (!orgId) {
    throw new HTTPException(400, { message: 'Organization ID required' });
  }

  // TODO: Fetch from database
  // For now, return mock data based on organization
  const licenseData: LicenseData = {
    status: 'active',
    plan: 'pro',
    plan_name: 'Pro',
    expires_at: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
    features: PLAN_LIMITS.pro.features,
    usage: {
      reservations: 127,
      reservations_limit: PLAN_LIMITS.pro.reservations,
      sms_sent: 45,
      sms_limit: PLAN_LIMITS.pro.sms,
      email_sent: 892,
      email_limit: PLAN_LIMITS.pro.emails
    },
    organization: {
      id: orgId,
      name: 'Mi Restaurante',
      email: 'contacto@mirestaurante.com'
    }
  };

  return c.json({
    success: true,
    data: licenseData
  });
});

/**
 * POST /license/activate
 * Activar una nueva licencia
 */
license.post('/activate', async (c) => {
  const body = await c.req.json();
  const { license_key, site_url } = body;

  if (!license_key) {
    throw new HTTPException(400, { message: 'License key required' });
  }

  // TODO: Validate license key against database
  // For now, accept any key starting with 'mk_'
  if (!license_key.startsWith('mk_')) {
    throw new HTTPException(400, { message: 'Invalid license key format' });
  }

  return c.json({
    success: true,
    data: {
      activated: true,
      plan: 'pro',
      expires_at: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString()
    }
  });
});

/**
 * POST /license/deactivate
 * Desactivar una licencia
 */
license.post('/deactivate', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');

  // TODO: Deactivate in database

  return c.json({
    success: true,
    data: {
      deactivated: true
    }
  });
});

/**
 * GET /license/usage
 * Obtener estadísticas de uso
 */
license.get('/usage', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');
  const period = c.req.query('period') || 'month'; // month, week, year

  // TODO: Fetch from database
  return c.json({
    success: true,
    data: {
      period,
      reservations: {
        total: 127,
        confirmed: 98,
        cancelled: 15,
        pending: 14
      },
      notifications: {
        email_sent: 892,
        sms_sent: 45,
        whatsapp_sent: 234
      },
      widget: {
        impressions: 5420,
        interactions: 1230,
        conversions: 127
      }
    }
  });
});

// ============================================================================
// BILLING ENDPOINTS
// ============================================================================

/**
 * GET /billing/info
 * Obtener información de facturación
 */
license.get('/billing/info', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');

  // TODO: Fetch from payment provider (Stripe)
  const billingInfo: BillingInfo = {
    payment_method: {
      brand: 'visa',
      last4: '4242',
      exp_month: 12,
      exp_year: 2025
    },
    billing_info: {
      name: 'Restaurante Ejemplo S.L.',
      tax_id: 'B12345678',
      address: 'Calle Principal 123',
      city: 'Madrid',
      postal_code: '28001',
      country: 'España',
      email: 'facturacion@ejemplo.com'
    },
    next_invoice: {
      amount: 29,
      date: new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString(),
      plan_name: 'Pro'
    }
  };

  return c.json({
    success: true,
    data: billingInfo
  });
});

/**
 * GET /billing/invoices
 * Obtener historial de facturas
 */
license.get('/billing/invoices', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');

  // TODO: Fetch from payment provider (Stripe)
  const invoices: Invoice[] = [
    {
      id: 'inv_001',
      number: 'INV-2024-0042',
      date: '2024-02-01',
      amount: 29,
      status: 'paid',
      pdf_url: 'https://api.contacpro.app/v1/billing/invoices/inv_001/pdf'
    },
    {
      id: 'inv_002',
      number: 'INV-2024-0035',
      date: '2024-01-01',
      amount: 29,
      status: 'paid',
      pdf_url: 'https://api.contacpro.app/v1/billing/invoices/inv_002/pdf'
    },
    {
      id: 'inv_003',
      number: 'INV-2023-0128',
      date: '2023-12-01',
      amount: 29,
      status: 'paid',
      pdf_url: 'https://api.contacpro.app/v1/billing/invoices/inv_003/pdf'
    }
  ];

  return c.json({
    success: true,
    data: invoices
  });
});

/**
 * GET /billing/invoices/:id/pdf
 * Descargar PDF de factura
 */
license.get('/billing/invoices/:id/pdf', async (c) => {
  const invoiceId = c.req.param('id');

  // TODO: Generate or fetch PDF from Stripe
  // For now, redirect to Stripe invoice
  return c.redirect(`https://invoice.stripe.com/${invoiceId}`);
});

/**
 * POST /billing/upgrade
 * Cambiar de plan
 */
license.post('/billing/upgrade', async (c) => {
  const body = await c.req.json();
  const { plan, return_url } = body;

  if (!plan) {
    throw new HTTPException(400, { message: 'Plan required' });
  }

  const validPlans = ['free', 'starter', 'pro', 'business'];
  if (!validPlans.includes(plan)) {
    throw new HTTPException(400, { message: 'Invalid plan' });
  }

  // TODO: Create Stripe checkout session
  const checkoutUrl = `https://checkout.contacpro.app/upgrade?plan=${plan}&return=${encodeURIComponent(return_url || '')}`;

  return c.json({
    success: true,
    data: {
      checkout_url: checkoutUrl
    }
  });
});

/**
 * POST /billing/payment-method/update-url
 * Obtener URL para actualizar método de pago
 */
license.post('/billing/payment-method/update-url', async (c) => {
  const body = await c.req.json();
  const { return_url } = body;

  // TODO: Create Stripe portal session
  const portalUrl = `https://billing.contacpro.app/portal?return=${encodeURIComponent(return_url || '')}`;

  return c.json({
    success: true,
    data: {
      url: portalUrl
    }
  });
});

/**
 * POST /billing/cancel
 * Cancelar suscripción
 */
license.post('/billing/cancel', async (c) => {
  const body = await c.req.json();
  const { reason, feedback } = body;

  // TODO: Cancel subscription in Stripe

  return c.json({
    success: true,
    data: {
      cancelled: true,
      effective_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
      message: 'Tu suscripción se cancelará al final del período de facturación actual.'
    }
  });
});

// ============================================================================
// SUPPORT ENDPOINTS
// ============================================================================

/**
 * GET /support/tickets
 * Obtener tickets de soporte
 */
license.get('/support/tickets', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');

  // TODO: Fetch from database
  const tickets: SupportTicket[] = [
    {
      id: 'TKT-2024-0015',
      subject: 'Problema con notificaciones SMS',
      category: 'technical',
      priority: 'high',
      status: 'pending',
      created_at: '2024-02-03T10:30:00Z',
      updated_at: '2024-02-04T14:20:00Z'
    },
    {
      id: 'TKT-2024-0012',
      subject: 'Consulta sobre integración API',
      category: 'general',
      priority: 'medium',
      status: 'resolved',
      created_at: '2024-01-28T09:15:00Z',
      updated_at: '2024-01-29T11:45:00Z'
    }
  ];

  return c.json({
    success: true,
    data: tickets
  });
});

/**
 * POST /support/tickets
 * Crear nuevo ticket de soporte
 */
license.post('/support/tickets', async (c) => {
  const body = await c.req.json();
  const { category, priority, subject, message, site_url, wp_version, plugin_version } = body;

  if (!category || !subject || !message) {
    throw new HTTPException(400, { message: 'Category, subject and message required' });
  }

  // TODO: Create ticket in support system
  const ticketId = 'TKT-2024-' + String(Math.floor(Math.random() * 9999)).padStart(4, '0');

  return c.json({
    success: true,
    data: {
      id: ticketId,
      status: 'open',
      message: 'Ticket creado correctamente. Recibirás una respuesta en las próximas 24 horas.'
    }
  });
});

/**
 * GET /support/tickets/:id
 * Obtener detalle de un ticket
 */
license.get('/support/tickets/:id', async (c) => {
  const ticketId = c.req.param('id');

  // TODO: Fetch from database
  return c.json({
    success: true,
    data: {
      id: ticketId,
      subject: 'Problema con notificaciones SMS',
      category: 'technical',
      priority: 'high',
      status: 'pending',
      messages: [
        {
          id: 'msg_001',
          from: 'user',
          content: 'Los SMS no se están enviando correctamente...',
          created_at: '2024-02-03T10:30:00Z'
        },
        {
          id: 'msg_002',
          from: 'support',
          content: 'Hemos detectado un problema con el proveedor de SMS...',
          created_at: '2024-02-04T14:20:00Z'
        }
      ],
      created_at: '2024-02-03T10:30:00Z',
      updated_at: '2024-02-04T14:20:00Z'
    }
  });
});

/**
 * POST /support/tickets/:id/reply
 * Responder a un ticket
 */
license.post('/support/tickets/:id/reply', async (c) => {
  const ticketId = c.req.param('id');
  const body = await c.req.json();
  const { message } = body;

  if (!message) {
    throw new HTTPException(400, { message: 'Message required' });
  }

  // TODO: Add reply to ticket

  return c.json({
    success: true,
    data: {
      id: 'msg_' + Date.now(),
      ticket_id: ticketId,
      created_at: new Date().toISOString()
    }
  });
});

// ============================================================================
// NOTIFICATIONS ENDPOINTS
// ============================================================================

/**
 * GET /notifications
 * Obtener notificaciones del sistema
 */
license.get('/notifications', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');
  const type = c.req.query('type');
  const limit = parseInt(c.req.query('limit') || '20');

  // TODO: Fetch from database
  let notifications: Notification[] = [
    {
      id: 'notif_001',
      type: 'feature',
      title: 'Nueva integración con WhatsApp Business',
      content: 'Ya puedes recibir reservas a través de WhatsApp Business. Configúralo en Ajustes > Canales.',
      read: false,
      created_at: '2024-02-05T09:00:00Z'
    },
    {
      id: 'notif_002',
      type: 'improvement',
      title: 'Mejoras en el rendimiento del widget',
      content: 'Hemos optimizado el widget de reservas para cargar un 40% más rápido.',
      read: false,
      created_at: '2024-02-01T12:00:00Z'
    },
    {
      id: 'notif_003',
      type: 'maintenance',
      title: 'Mantenimiento programado',
      content: 'El próximo domingo 11 de febrero realizaremos tareas de mantenimiento entre las 03:00 y las 05:00.',
      read: true,
      created_at: '2024-01-28T10:00:00Z'
    },
    {
      id: 'notif_004',
      type: 'announcement',
      title: 'Nuevos planes de precios',
      content: 'Hemos actualizado nuestros planes para ofrecerte más valor. Revisa las nuevas opciones disponibles.',
      read: true,
      created_at: '2024-01-15T08:00:00Z'
    }
  ];

  // Filter by type if specified
  if (type && type !== 'all') {
    notifications = notifications.filter(n => n.type === type);
  }

  // Limit results
  notifications = notifications.slice(0, limit);

  return c.json({
    success: true,
    data: notifications
  });
});

/**
 * POST /notifications/:id/read
 * Marcar notificación como leída
 */
license.post('/notifications/:id/read', async (c) => {
  const notificationId = c.req.param('id');

  // TODO: Update in database

  return c.json({
    success: true,
    data: {
      id: notificationId,
      read: true
    }
  });
});

/**
 * POST /notifications/mark-all-read
 * Marcar todas las notificaciones como leídas
 */
license.post('/notifications/mark-all-read', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');

  // TODO: Update all in database

  return c.json({
    success: true,
    data: {
      marked: true
    }
  });
});

/**
 * GET /notifications/unread-count
 * Obtener contador de notificaciones no leídas
 */
license.get('/notifications/unread-count', async (c) => {
  const orgId = c.req.query('org') || c.get('organizationId');

  // TODO: Count from database
  return c.json({
    success: true,
    data: {
      count: 2
    }
  });
});

// ============================================================================
// CHANGELOG & ROADMAP ENDPOINTS
// ============================================================================

/**
 * GET /changelog
 * Obtener historial de versiones
 */
license.get('/changelog', async (c) => {
  const changelog = [
    {
      version: '1.1.0',
      date: '2024-02-05',
      changes: [
        { type: 'feature', text: 'Sistema de gestión de licencias integrado' },
        { type: 'feature', text: 'Integración con WhatsApp Business' },
        { type: 'feature', text: 'Bot de Telegram para reservas' },
        { type: 'improvement', text: 'Nuevo panel de administración' }
      ]
    },
    {
      version: '1.0.0',
      date: '2024-01-15',
      changes: [
        { type: 'feature', text: 'Lanzamiento inicial' },
        { type: 'feature', text: 'Widget de reservas embebible' },
        { type: 'feature', text: 'Sistema de notificaciones por email' },
        { type: 'feature', text: 'Dashboard de gestión' }
      ]
    }
  ];

  return c.json({
    success: true,
    data: changelog
  });
});

/**
 * GET /roadmap
 * Obtener roadmap de funcionalidades
 */
license.get('/roadmap', async (c) => {
  const roadmap = [
    {
      status: 'in-progress',
      title: 'Integración con Google Calendar',
      description: 'Sincronización bidireccional de reservas',
      expected: '2024-Q1'
    },
    {
      status: 'planned',
      title: 'App móvil para restaurantes',
      description: 'Gestiona tus reservas desde tu smartphone',
      expected: '2024-Q2'
    },
    {
      status: 'beta',
      title: 'Sistema de lista de espera',
      description: 'Permite a los clientes apuntarse cuando no hay disponibilidad',
      expected: '2024-Q1'
    },
    {
      status: 'planned',
      title: 'Inteligencia artificial para predicciones',
      description: 'Predicción de demanda y optimización de mesas',
      expected: '2024-Q3'
    }
  ];

  return c.json({
    success: true,
    data: roadmap
  });
});

export default license;
