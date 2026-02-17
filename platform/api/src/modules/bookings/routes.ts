import { Hono } from 'hono';
import { z } from 'zod';
import { zValidator } from '@hono/zod-validator';
import { prisma } from '../../shared/database/client';
import { authMiddleware, optionalAuth, requireScope } from '../../shared/middleware/auth';
import type { Variables, BookingFilters } from '../../shared/types';
import { nanoid } from 'nanoid';

export const bookingsRoutes = new Hono<{ Variables: Variables }>();

// ============================================
// SCHEMAS
// ============================================
const createBookingSchema = z.object({
  customerName: z.string().min(2).max(100),
  customerEmail: z.string().email(),
  customerPhone: z.string().optional(),
  bookingDate: z.string().regex(/^\d{4}-\d{2}-\d{2}$/), // YYYY-MM-DD
  bookingTime: z.string().regex(/^\d{2}:\d{2}$/), // HH:MM
  guests: z.number().int().min(1).max(50),
  occasion: z.string().optional(),
  specialRequests: z.string().optional(),
  source: z.enum(['web', 'widget', 'api', 'mobile']).default('api'),
});

const updateBookingSchema = z.object({
  customerName: z.string().min(2).max(100).optional(),
  customerEmail: z.string().email().optional(),
  customerPhone: z.string().optional(),
  bookingDate: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
  bookingTime: z.string().regex(/^\d{2}:\d{2}$/).optional(),
  guests: z.number().int().min(1).max(50).optional(),
  occasion: z.string().optional(),
  specialRequests: z.string().optional(),
  status: z.enum(['PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED', 'NO_SHOW']).optional(),
});

const querySchema = z.object({
  page: z.coerce.number().int().min(1).default(1),
  limit: z.coerce.number().int().min(1).max(100).default(20),
  status: z.string().optional(),
  dateFrom: z.string().optional(),
  dateTo: z.string().optional(),
  search: z.string().optional(),
  sortBy: z.enum(['bookingDate', 'createdAt', 'customerName']).default('bookingDate'),
  sortOrder: z.enum(['asc', 'desc']).default('asc'),
});

// ============================================
// ROUTES
// ============================================

// Listar reservas (requiere auth)
bookingsRoutes.get('/', authMiddleware, zValidator('query', querySchema), async (c) => {
  const organization = c.get('organization');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const { page, limit, status, dateFrom, dateTo, search, sortBy, sortOrder } = c.req.valid('query');

  const where: Record<string, unknown> = {
    organizationId: organization.id,
  };

  if (status) {
    where.status = status.toUpperCase();
  }

  if (dateFrom || dateTo) {
    where.bookingDate = {};
    if (dateFrom) (where.bookingDate as Record<string, Date>).gte = new Date(dateFrom);
    if (dateTo) (where.bookingDate as Record<string, Date>).lte = new Date(dateTo);
  }

  if (search) {
    where.OR = [
      { customerName: { contains: search, mode: 'insensitive' } },
      { customerEmail: { contains: search, mode: 'insensitive' } },
      { customerPhone: { contains: search } },
    ];
  }

  const [bookings, total] = await Promise.all([
    prisma.booking.findMany({
      where,
      skip: (page - 1) * limit,
      take: limit,
      orderBy: { [sortBy]: sortOrder },
      include: {
        notes: {
          include: { user: { select: { name: true } } },
          orderBy: { createdAt: 'desc' },
          take: 3,
        },
      },
    }),
    prisma.booking.count({ where }),
  ]);

  return c.json({
    success: true,
    data: bookings,
    meta: {
      page,
      limit,
      total,
      totalPages: Math.ceil(total / limit),
      hasMore: page * limit < total,
    },
  });
});

// Obtener una reserva
bookingsRoutes.get('/:id', authMiddleware, async (c) => {
  const organization = c.get('organization');
  const bookingId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const booking = await prisma.booking.findFirst({
    where: {
      id: bookingId,
      organizationId: organization.id,
    },
    include: {
      notes: {
        include: { user: { select: { id: true, name: true } } },
        orderBy: { createdAt: 'desc' },
      },
    },
  });

  if (!booking) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Booking not found' },
    }, 404);
  }

  return c.json({ success: true, data: booking });
});

// Crear reserva (público o autenticado)
bookingsRoutes.post('/', optionalAuth, zValidator('json', createBookingSchema), async (c) => {
  const organization = c.get('organization');
  const apiKey = c.get('apiKey');
  const data = c.req.valid('json');

  // Si no hay auth, necesitamos el organizationId en el body o query
  let orgId = organization?.id;

  if (!orgId) {
    const orgSlug = c.req.query('org') || c.req.header('X-Organization');
    if (orgSlug) {
      const org = await prisma.organization.findUnique({
        where: { slug: orgSlug },
      });
      if (org) orgId = org.id;
    }
  }

  if (!orgId) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization slug required (query param "org" or header "X-Organization")' },
    }, 400);
  }

  // Verificar blacklist
  const blacklisted = await prisma.blacklistEntry.findFirst({
    where: {
      organizationId: orgId,
      isActive: true,
      OR: [
        { email: data.customerEmail },
        data.customerPhone ? { phone: data.customerPhone } : {},
      ],
    },
  });

  if (blacklisted) {
    return c.json({
      success: false,
      error: {
        code: 'BLACKLISTED',
        message: 'This email or phone is not allowed to make reservations',
      },
    }, 403);
  }

  // Generar token de edición
  const editToken = nanoid(32);

  const booking = await prisma.booking.create({
    data: {
      ...data,
      bookingDate: new Date(data.bookingDate),
      organizationId: orgId,
      editToken,
      source: apiKey ? 'api' : data.source,
    },
  });

  // TODO: Enviar email de confirmación
  // TODO: Disparar webhook booking.created

  return c.json({
    success: true,
    data: {
      ...booking,
      editToken, // Solo se muestra una vez
    },
  }, 201);
});

// Actualizar reserva
bookingsRoutes.patch('/:id', authMiddleware, zValidator('json', updateBookingSchema), async (c) => {
  const organization = c.get('organization');
  const user = c.get('user');
  const bookingId = c.req.param('id');
  const data = c.req.valid('json');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

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

  const updateData: Record<string, unknown> = { ...data };
  if (data.bookingDate) {
    updateData.bookingDate = new Date(data.bookingDate);
  }

  const updated = await prisma.booking.update({
    where: { id: bookingId },
    data: updateData,
  });

  // Si cambió el status, registrar en audit log
  if (data.status && data.status !== booking.status) {
    await prisma.auditLog.create({
      data: {
        action: 'BOOKING_STATUS_CHANGED',
        entity: 'booking',
        entityId: bookingId,
        oldData: { status: booking.status },
        newData: { status: data.status },
        userId: user?.id,
      },
    });

    // TODO: Disparar webhook booking.updated
  }

  return c.json({ success: true, data: updated });
});

// Confirmar reserva (shortcut)
bookingsRoutes.post('/:id/confirm', authMiddleware, async (c) => {
  const organization = c.get('organization');
  const bookingId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const booking = await prisma.booking.updateMany({
    where: {
      id: bookingId,
      organizationId: organization.id,
      status: 'PENDING',
    },
    data: { status: 'CONFIRMED' },
  });

  if (booking.count === 0) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Booking not found or not pending' },
    }, 404);
  }

  // TODO: Enviar email de confirmación
  // TODO: Disparar webhook booking.confirmed

  return c.json({ success: true, message: 'Booking confirmed' });
});

// Cancelar reserva
bookingsRoutes.post('/:id/cancel', authMiddleware, async (c) => {
  const organization = c.get('organization');
  const bookingId = c.req.param('id');

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  const booking = await prisma.booking.updateMany({
    where: {
      id: bookingId,
      organizationId: organization.id,
      status: { in: ['PENDING', 'CONFIRMED'] },
    },
    data: { status: 'CANCELLED' },
  });

  if (booking.count === 0) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Booking not found or cannot be cancelled' },
    }, 404);
  }

  // TODO: Enviar email de cancelación
  // TODO: Disparar webhook booking.cancelled

  return c.json({ success: true, message: 'Booking cancelled' });
});

// Agregar nota a reserva
bookingsRoutes.post('/:id/notes', authMiddleware, async (c) => {
  const organization = c.get('organization');
  const user = c.get('user')!;
  const bookingId = c.req.param('id');

  const body = await c.req.json();
  const content = body.content;

  if (!content || typeof content !== 'string') {
    return c.json({
      success: false,
      error: { code: 'VALIDATION_ERROR', message: 'Note content required' },
    }, 400);
  }

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NO_ORGANIZATION', message: 'Organization required' },
    }, 400);
  }

  // Verificar que la reserva existe
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

  const note = await prisma.bookingNote.create({
    data: {
      content,
      bookingId,
      userId: user.id,
    },
    include: {
      user: { select: { name: true } },
    },
  });

  return c.json({ success: true, data: note }, 201);
});

// Cancelar reserva por token (público)
bookingsRoutes.post('/cancel-by-token', async (c) => {
  const body = await c.req.json();
  const token = body.token;

  if (!token) {
    return c.json({
      success: false,
      error: { code: 'VALIDATION_ERROR', message: 'Edit token required' },
    }, 400);
  }

  const booking = await prisma.booking.updateMany({
    where: {
      editToken: token,
      status: { in: ['PENDING', 'CONFIRMED'] },
    },
    data: { status: 'CANCELLED' },
  });

  if (booking.count === 0) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Booking not found or cannot be cancelled' },
    }, 404);
  }

  return c.json({ success: true, message: 'Booking cancelled' });
});
