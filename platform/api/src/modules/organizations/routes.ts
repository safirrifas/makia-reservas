import { Hono } from 'hono';
import { z } from 'zod';
import { zValidator } from '@hono/zod-validator';
import { prisma } from '../../shared/database/client';
import { authMiddleware } from '../../shared/middleware/auth';
import type { Variables } from '../../shared/types';

export const organizationsRoutes = new Hono<{ Variables: Variables }>();

// Todas las rutas requieren autenticación
organizationsRoutes.use('*', authMiddleware);

// ============================================
// SCHEMAS
// ============================================
const updateOrgSchema = z.object({
  name: z.string().min(2).optional(),
  email: z.string().email().optional(),
  phone: z.string().optional(),
  address: z.string().optional(),
  timezone: z.string().optional(),
  currency: z.string().length(3).optional(),
  settings: z.record(z.unknown()).optional(),
});

const businessHoursSchema = z.array(z.object({
  dayOfWeek: z.number().int().min(0).max(6),
  isOpen: z.boolean(),
  slots: z.array(z.object({
    open: z.string().regex(/^\d{2}:\d{2}$/),
    close: z.string().regex(/^\d{2}:\d{2}$/),
  })),
}));

const specialDaySchema = z.object({
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  type: z.enum(['CLOSED', 'SPECIAL_HOURS', 'HOLIDAY']),
  name: z.string().optional(),
  slots: z.array(z.object({
    open: z.string().regex(/^\d{2}:\d{2}$/),
    close: z.string().regex(/^\d{2}:\d{2}$/),
  })).optional(),
});

// ============================================
// ROUTES
// ============================================

// Obtener organización actual
organizationsRoutes.get('/current', async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  const org = await prisma.organization.findUnique({
    where: { id: organization.id },
    include: {
      _count: {
        select: {
          users: true,
          bookings: true,
        },
      },
    },
  });

  return c.json({ success: true, data: org });
});

// Actualizar organización
organizationsRoutes.patch('/current', zValidator('json', updateOrgSchema), async (c) => {
  const organization = c.get('organization');
  const user = c.get('user')!;

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  if (user.role !== 'OWNER' && user.role !== 'ADMIN') {
    return c.json({
      success: false,
      error: { code: 'FORBIDDEN', message: 'Only owners and admins can update organization' },
    }, 403);
  }

  const data = c.req.valid('json');

  const updated = await prisma.organization.update({
    where: { id: organization.id },
    data,
  });

  return c.json({ success: true, data: updated });
});

// Obtener estadísticas
organizationsRoutes.get('/current/stats', async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  const now = new Date();
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  const [
    totalBookings,
    monthlyBookings,
    pendingBookings,
    confirmedBookings,
    todayBookings,
  ] = await Promise.all([
    prisma.booking.count({ where: { organizationId: organization.id } }),
    prisma.booking.count({
      where: {
        organizationId: organization.id,
        createdAt: { gte: startOfMonth, lte: endOfMonth },
      },
    }),
    prisma.booking.count({
      where: { organizationId: organization.id, status: 'PENDING' },
    }),
    prisma.booking.count({
      where: { organizationId: organization.id, status: 'CONFIRMED' },
    }),
    prisma.booking.count({
      where: {
        organizationId: organization.id,
        bookingDate: {
          gte: new Date(now.toISOString().split('T')[0]),
          lt: new Date(new Date(now).setDate(now.getDate() + 1)),
        },
      },
    }),
  ]);

  // Límites de plan
  const planLimits = {
    FREE: 20,
    STARTER: 100,
    PRO: 500,
    BUSINESS: Infinity,
  };

  const limit = planLimits[organization.plan];

  return c.json({
    success: true,
    data: {
      totalBookings,
      monthlyBookings,
      pendingBookings,
      confirmedBookings,
      todayBookings,
      plan: {
        name: organization.plan,
        limit,
        used: monthlyBookings,
        remaining: Math.max(0, limit - monthlyBookings),
        usagePercentage: limit === Infinity ? 0 : Math.round((monthlyBookings / limit) * 100),
      },
    },
  });
});

// ============================================
// HORARIOS
// ============================================

// Obtener horarios de apertura
organizationsRoutes.get('/current/business-hours', async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  const hours = await prisma.businessHours.findMany({
    where: { organizationId: organization.id },
    orderBy: { dayOfWeek: 'asc' },
  });

  return c.json({ success: true, data: hours });
});

// Actualizar horarios de apertura
organizationsRoutes.put('/current/business-hours', zValidator('json', businessHoursSchema), async (c) => {
  const organization = c.get('organization');
  const user = c.get('user')!;

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  if (user.role !== 'OWNER' && user.role !== 'ADMIN') {
    return c.json({
      success: false,
      error: { code: 'FORBIDDEN', message: 'Only owners and admins can update business hours' },
    }, 403);
  }

  const hours = c.req.valid('json');

  // Upsert todos los días
  await prisma.$transaction(
    hours.map((h) =>
      prisma.businessHours.upsert({
        where: {
          organizationId_dayOfWeek: {
            organizationId: organization.id,
            dayOfWeek: h.dayOfWeek,
          },
        },
        update: {
          isOpen: h.isOpen,
          slots: h.slots,
        },
        create: {
          organizationId: organization.id,
          dayOfWeek: h.dayOfWeek,
          isOpen: h.isOpen,
          slots: h.slots,
        },
      })
    )
  );

  const updated = await prisma.businessHours.findMany({
    where: { organizationId: organization.id },
    orderBy: { dayOfWeek: 'asc' },
  });

  return c.json({ success: true, data: updated });
});

// ============================================
// DÍAS ESPECIALES
// ============================================

// Listar días especiales
organizationsRoutes.get('/current/special-days', async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  const days = await prisma.specialDay.findMany({
    where: {
      organizationId: organization.id,
      date: { gte: new Date() }, // Solo futuros
    },
    orderBy: { date: 'asc' },
  });

  return c.json({ success: true, data: days });
});

// Crear día especial
organizationsRoutes.post('/current/special-days', zValidator('json', specialDaySchema), async (c) => {
  const organization = c.get('organization');
  const user = c.get('user')!;

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  if (user.role !== 'OWNER' && user.role !== 'ADMIN') {
    return c.json({
      success: false,
      error: { code: 'FORBIDDEN', message: 'Only owners and admins can create special days' },
    }, 403);
  }

  const data = c.req.valid('json');

  const specialDay = await prisma.specialDay.upsert({
    where: {
      organizationId_date: {
        organizationId: organization.id,
        date: new Date(data.date),
      },
    },
    update: {
      type: data.type,
      name: data.name,
      slots: data.slots || [],
    },
    create: {
      organizationId: organization.id,
      date: new Date(data.date),
      type: data.type,
      name: data.name,
      slots: data.slots || [],
    },
  });

  return c.json({ success: true, data: specialDay }, 201);
});

// Eliminar día especial
organizationsRoutes.delete('/current/special-days/:id', async (c) => {
  const organization = c.get('organization');
  const dayId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'User must belong to an organization' },
    }, 400);
  }

  await prisma.specialDay.deleteMany({
    where: {
      id: dayId,
      organizationId: organization.id,
    },
  });

  return c.json({ success: true });
});
