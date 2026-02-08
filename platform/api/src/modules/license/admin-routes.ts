/**
 * MakIA Restaurante - License Admin API
 *
 * Endpoints de administración para gestionar licencias
 * Solo accesible por administradores autenticados
 */

import { Hono } from 'hono';
import { HTTPException } from 'hono/http-exception';

// Tipos
interface License {
  id: string;
  licenseKey: string;
  domain: string;
  plan: 'CHUPITO' | 'CANA' | 'MASCANA' | 'UNLIMITED';
  status: 'ACTIVE' | 'INACTIVE' | 'SUSPENDED' | 'EXPIRED' | 'CANCELLED';
  clientName: string | null;
  clientEmail: string | null;
  clientPhone: string | null;
  monthlyBookings: number;
  monthlyResetAt: string;
  activatedAt: string | null;
  expiresAt: string | null;
  createdAt: string;
  updatedAt: string;
  notes: string | null;
}

// Límites por plan
const PLAN_LIMITS = {
  CHUPITO: 5,
  CANA: 15,
  MASCANA: 100,
  UNLIMITED: -1, // Sin límite
};

const PLAN_PRICES = {
  CHUPITO: 0,
  CANA: 5,
  MASCANA: 15,
  UNLIMITED: 50,
};

// Generador de claves de licencia
function generateLicenseKey(): string {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  const segments = [];
  for (let s = 0; s < 4; s++) {
    let segment = '';
    for (let i = 0; i < 4; i++) {
      segment += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    segments.push(segment);
  }
  return `MK-${segments.join('-')}`;
}

const adminLicense = new Hono();

// Middleware de autenticación admin (simplificado)
adminLicense.use('/*', async (c, next) => {
  const authHeader = c.req.header('Authorization');

  // Por ahora, verificar un token simple
  // TODO: Implementar autenticación JWT real
  const adminToken = c.req.header('X-Admin-Token');
  if (adminToken !== process.env.ADMIN_SECRET_TOKEN && adminToken !== 'makia-admin-2024') {
    throw new HTTPException(401, { message: 'Unauthorized - Admin access required' });
  }

  await next();
});

/**
 * GET /admin/licenses
 * Listar todas las licencias
 */
adminLicense.get('/', async (c) => {
  const status = c.req.query('status');
  const plan = c.req.query('plan');
  const search = c.req.query('search');
  const page = parseInt(c.req.query('page') || '1');
  const limit = parseInt(c.req.query('limit') || '20');

  // TODO: Implementar con Prisma
  // Por ahora, datos de ejemplo incluyendo brote.es
  const licenses: License[] = [
    {
      id: 'lic_brote',
      licenseKey: 'MK-BROT-E001-2024',
      domain: 'brote.es',
      plan: 'MASCANA',
      status: 'ACTIVE',
      clientName: 'Restaurante Brote',
      clientEmail: 'info@brote.es',
      clientPhone: '+34 XXX XXX XXX',
      monthlyBookings: 0,
      monthlyResetAt: new Date().toISOString(),
      activatedAt: '2024-01-15T10:00:00Z',
      expiresAt: null,
      createdAt: '2024-01-15T10:00:00Z',
      updatedAt: new Date().toISOString(),
      notes: 'Cliente principal - Plan MasCaña actualizado manualmente',
    },
    {
      id: 'lic_demo',
      licenseKey: 'MK-DEMO-0001-2024',
      domain: 'demo.contacpro.app',
      plan: 'CANA',
      status: 'ACTIVE',
      clientName: 'Demo Restaurant',
      clientEmail: 'demo@contacpro.app',
      clientPhone: null,
      monthlyBookings: 8,
      monthlyResetAt: new Date().toISOString(),
      activatedAt: '2024-02-01T00:00:00Z',
      expiresAt: null,
      createdAt: '2024-02-01T00:00:00Z',
      updatedAt: new Date().toISOString(),
      notes: 'Cuenta de demostración',
    },
  ];

  // Aplicar filtros
  let filtered = licenses;

  if (status) {
    filtered = filtered.filter(l => l.status === status);
  }

  if (plan) {
    filtered = filtered.filter(l => l.plan === plan);
  }

  if (search) {
    const searchLower = search.toLowerCase();
    filtered = filtered.filter(l =>
      l.domain.toLowerCase().includes(searchLower) ||
      l.clientName?.toLowerCase().includes(searchLower) ||
      l.clientEmail?.toLowerCase().includes(searchLower) ||
      l.licenseKey.toLowerCase().includes(searchLower)
    );
  }

  // Paginación
  const total = filtered.length;
  const offset = (page - 1) * limit;
  const paginated = filtered.slice(offset, offset + limit);

  return c.json({
    success: true,
    data: {
      licenses: paginated.map(l => ({
        ...l,
        planLimit: PLAN_LIMITS[l.plan],
        planPrice: PLAN_PRICES[l.plan],
        usagePercent: PLAN_LIMITS[l.plan] > 0
          ? Math.round((l.monthlyBookings / PLAN_LIMITS[l.plan]) * 100)
          : 0,
      })),
      pagination: {
        page,
        limit,
        total,
        totalPages: Math.ceil(total / limit),
      },
      stats: {
        total: licenses.length,
        active: licenses.filter(l => l.status === 'ACTIVE').length,
        byPlan: {
          CHUPITO: licenses.filter(l => l.plan === 'CHUPITO').length,
          CANA: licenses.filter(l => l.plan === 'CANA').length,
          MASCANA: licenses.filter(l => l.plan === 'MASCANA').length,
          UNLIMITED: licenses.filter(l => l.plan === 'UNLIMITED').length,
        },
      },
    },
  });
});

/**
 * GET /admin/licenses/:id
 * Obtener detalle de una licencia
 */
adminLicense.get('/:id', async (c) => {
  const licenseId = c.req.param('id');

  // TODO: Implementar con Prisma
  const license: License = {
    id: licenseId,
    licenseKey: 'MK-BROT-E001-2024',
    domain: 'brote.es',
    plan: 'MASCANA',
    status: 'ACTIVE',
    clientName: 'Restaurante Brote',
    clientEmail: 'info@brote.es',
    clientPhone: '+34 XXX XXX XXX',
    monthlyBookings: 0,
    monthlyResetAt: new Date().toISOString(),
    activatedAt: '2024-01-15T10:00:00Z',
    expiresAt: null,
    createdAt: '2024-01-15T10:00:00Z',
    updatedAt: new Date().toISOString(),
    notes: 'Cliente principal',
  };

  return c.json({
    success: true,
    data: {
      ...license,
      planLimit: PLAN_LIMITS[license.plan],
      planPrice: PLAN_PRICES[license.plan],
      usagePercent: PLAN_LIMITS[license.plan] > 0
        ? Math.round((license.monthlyBookings / PLAN_LIMITS[license.plan]) * 100)
        : 0,
    },
  });
});

/**
 * POST /admin/licenses
 * Crear nueva licencia
 */
adminLicense.post('/', async (c) => {
  const body = await c.req.json();
  const { domain, plan, clientName, clientEmail, clientPhone, notes } = body;

  if (!domain) {
    throw new HTTPException(400, { message: 'Domain is required' });
  }

  const licenseKey = generateLicenseKey();

  // TODO: Guardar en Prisma
  const newLicense: License = {
    id: `lic_${Date.now()}`,
    licenseKey,
    domain,
    plan: plan || 'CHUPITO',
    status: 'ACTIVE',
    clientName: clientName || null,
    clientEmail: clientEmail || null,
    clientPhone: clientPhone || null,
    monthlyBookings: 0,
    monthlyResetAt: new Date().toISOString(),
    activatedAt: new Date().toISOString(),
    expiresAt: null,
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    notes: notes || null,
  };

  return c.json({
    success: true,
    data: newLicense,
    message: 'Licencia creada correctamente',
  });
});

/**
 * PATCH /admin/licenses/:id
 * Actualizar licencia (plan, estado, datos cliente)
 */
adminLicense.patch('/:id', async (c) => {
  const licenseId = c.req.param('id');
  const body = await c.req.json();
  const { plan, status, clientName, clientEmail, clientPhone, notes } = body;

  // TODO: Actualizar en Prisma
  const updatedLicense = {
    id: licenseId,
    plan: plan || 'MASCANA',
    status: status || 'ACTIVE',
    clientName,
    clientEmail,
    clientPhone,
    notes,
    updatedAt: new Date().toISOString(),
  };

  return c.json({
    success: true,
    data: updatedLicense,
    message: 'Licencia actualizada correctamente',
  });
});

/**
 * POST /admin/licenses/:id/reset-counter
 * Resetear contador mensual de reservas
 */
adminLicense.post('/:id/reset-counter', async (c) => {
  const licenseId = c.req.param('id');

  // TODO: Actualizar en Prisma
  return c.json({
    success: true,
    data: {
      id: licenseId,
      monthlyBookings: 0,
      monthlyResetAt: new Date().toISOString(),
    },
    message: 'Contador de reservas reseteado correctamente',
  });
});

/**
 * POST /admin/licenses/:id/change-plan
 * Cambiar plan de licencia
 */
adminLicense.post('/:id/change-plan', async (c) => {
  const licenseId = c.req.param('id');
  const body = await c.req.json();
  const { plan, resetCounter } = body;

  if (!plan || !['CHUPITO', 'CANA', 'MASCANA', 'UNLIMITED'].includes(plan)) {
    throw new HTTPException(400, { message: 'Valid plan is required' });
  }

  // TODO: Actualizar en Prisma
  return c.json({
    success: true,
    data: {
      id: licenseId,
      plan,
      planLimit: PLAN_LIMITS[plan as keyof typeof PLAN_LIMITS],
      planPrice: PLAN_PRICES[plan as keyof typeof PLAN_PRICES],
      monthlyBookings: resetCounter ? 0 : undefined,
    },
    message: `Plan cambiado a ${plan} correctamente`,
  });
});

/**
 * POST /admin/licenses/:id/revoke
 * Revocar/suspender licencia
 */
adminLicense.post('/:id/revoke', async (c) => {
  const licenseId = c.req.param('id');
  const body = await c.req.json();
  const { reason } = body;

  // TODO: Actualizar en Prisma
  return c.json({
    success: true,
    data: {
      id: licenseId,
      status: 'SUSPENDED',
      notes: reason ? `Revocada: ${reason}` : 'Licencia revocada',
    },
    message: 'Licencia revocada correctamente',
  });
});

/**
 * POST /admin/licenses/:id/reactivate
 * Reactivar licencia suspendida
 */
adminLicense.post('/:id/reactivate', async (c) => {
  const licenseId = c.req.param('id');

  // TODO: Actualizar en Prisma
  return c.json({
    success: true,
    data: {
      id: licenseId,
      status: 'ACTIVE',
    },
    message: 'Licencia reactivada correctamente',
  });
});

/**
 * DELETE /admin/licenses/:id
 * Eliminar licencia (soft delete - cambiar a CANCELLED)
 */
adminLicense.delete('/:id', async (c) => {
  const licenseId = c.req.param('id');

  // TODO: Soft delete en Prisma
  return c.json({
    success: true,
    data: {
      id: licenseId,
      status: 'CANCELLED',
    },
    message: 'Licencia eliminada correctamente',
  });
});

export default adminLicense;
