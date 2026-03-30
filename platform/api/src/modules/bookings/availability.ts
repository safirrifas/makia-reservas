import { Hono } from 'hono';
import { z } from 'zod';
import { zValidator } from '@hono/zod-validator';
import { prisma } from '../../shared/database/client';
import type { Variables } from '../../shared/types';

export const availabilityRoutes = new Hono<{ Variables: Variables }>();

// ============================================
// SCHEMAS
// ============================================
const availabilityQuerySchema = z.object({
  org: z.string(), // Organization slug
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  guests: z.coerce.number().int().min(1).default(2),
});

const checkAvailabilitySchema = z.object({
  org: z.string(),
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  time: z.string().regex(/^\d{2}:\d{2}$/),
  guests: z.number().int().min(1),
});

// ============================================
// ROUTES
// ============================================

// Obtener slots disponibles para una fecha
availabilityRoutes.get('/', zValidator('query', availabilityQuerySchema), async (c) => {
  const { org, date, guests } = c.req.valid('query');

  const organization = await prisma.organization.findUnique({
    where: { slug: org },
  });

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Organization not found' },
    }, 404);
  }

  const targetDate = new Date(date);
  const dayOfWeek = targetDate.getDay();

  // Verificar si es día especial
  const specialDay = await prisma.specialDay.findUnique({
    where: {
      organizationId_date: {
        organizationId: organization.id,
        date: targetDate,
      },
    },
  });

  if (specialDay?.type === 'CLOSED') {
    return c.json({
      success: true,
      data: {
        date,
        isOpen: false,
        reason: specialDay.name || 'Closed',
        slots: [],
      },
    });
  }

  // Obtener horarios del día
  const businessHours = await prisma.businessHours.findUnique({
    where: {
      organizationId_dayOfWeek: {
        organizationId: organization.id,
        dayOfWeek,
      },
    },
  });

  if (!businessHours || !businessHours.isOpen) {
    return c.json({
      success: true,
      data: {
        date,
        isOpen: false,
        reason: 'Closed on this day',
        slots: [],
      },
    });
  }

  // Generar slots disponibles
  const slots = specialDay?.slots
    ? (specialDay.slots as { open: string; close: string }[])
    : (businessHours.slots as { open: string; close: string }[]);

  const timeSlots = generateTimeSlots(slots);

  // Obtener reservas existentes para ese día
  const existingBookings = await prisma.booking.findMany({
    where: {
      organizationId: organization.id,
      bookingDate: targetDate,
      status: { in: ['PENDING', 'CONFIRMED'] },
    },
    select: {
      bookingTime: true,
      guests: true,
    },
  });

  // Calcular disponibilidad por slot
  // TODO: Implementar lógica de capacidad real
  const capacity = (organization.settings as { maxGuestsPerSlot?: number })?.maxGuestsPerSlot || 20;

  const availableSlots = timeSlots.map((time) => {
    const bookingsAtTime = existingBookings.filter((b) => b.bookingTime === time);
    const guestsAtTime = bookingsAtTime.reduce((sum, b) => sum + b.guests, 0);
    const available = guestsAtTime + guests <= capacity;

    return {
      time,
      available,
      remainingCapacity: Math.max(0, capacity - guestsAtTime),
    };
  });

  return c.json({
    success: true,
    data: {
      date,
      isOpen: true,
      slots: availableSlots,
    },
  });
});

// Verificar disponibilidad específica
availabilityRoutes.post('/check', zValidator('json', checkAvailabilitySchema), async (c) => {
  const { org, date, time, guests } = c.req.valid('json');

  const organization = await prisma.organization.findUnique({
    where: { slug: org },
  });

  if (!organization) {
    return c.json({
      success: false,
      error: { code: 'NOT_FOUND', message: 'Organization not found' },
    }, 404);
  }

  const targetDate = new Date(date);

  // Verificar día especial cerrado
  const specialDay = await prisma.specialDay.findUnique({
    where: {
      organizationId_date: {
        organizationId: organization.id,
        date: targetDate,
      },
    },
  });

  if (specialDay?.type === 'CLOSED') {
    return c.json({
      success: true,
      data: {
        available: false,
        reason: 'Restaurant is closed on this date',
      },
    });
  }

  // Verificar horarios
  const dayOfWeek = targetDate.getDay();
  const businessHours = await prisma.businessHours.findUnique({
    where: {
      organizationId_dayOfWeek: {
        organizationId: organization.id,
        dayOfWeek,
      },
    },
  });

  if (!businessHours || !businessHours.isOpen) {
    return c.json({
      success: true,
      data: {
        available: false,
        reason: 'Restaurant is closed on this day',
      },
    });
  }

  // Verificar que el horario está dentro de los slots
  const slots = specialDay?.slots
    ? (specialDay.slots as { open: string; close: string }[])
    : (businessHours.slots as { open: string; close: string }[]);

  const timeSlots = generateTimeSlots(slots);
  if (!timeSlots.includes(time)) {
    return c.json({
      success: true,
      data: {
        available: false,
        reason: 'Time slot not available',
      },
    });
  }

  // Verificar capacidad
  const existingBookings = await prisma.booking.findMany({
    where: {
      organizationId: organization.id,
      bookingDate: targetDate,
      bookingTime: time,
      status: { in: ['PENDING', 'CONFIRMED'] },
    },
    select: { guests: true },
  });

  const guestsAtTime = existingBookings.reduce((sum, b) => sum + b.guests, 0);
  const capacity = (organization.settings as { maxGuestsPerSlot?: number })?.maxGuestsPerSlot || 20;

  if (guestsAtTime + guests > capacity) {
    return c.json({
      success: true,
      data: {
        available: false,
        reason: 'Not enough capacity for this time slot',
        remainingCapacity: Math.max(0, capacity - guestsAtTime),
      },
    });
  }

  return c.json({
    success: true,
    data: {
      available: true,
      remainingCapacity: capacity - guestsAtTime - guests,
    },
  });
});

// ============================================
// HELPERS
// ============================================
function generateTimeSlots(slots: { open: string; close: string }[]): string[] {
  const result: string[] = [];

  for (const slot of slots) {
    const [openHour, openMin] = slot.open.split(':').map(Number);
    const [closeHour, closeMin] = slot.close.split(':').map(Number);

    let currentMinutes = openHour * 60 + openMin;
    const endMinutes = closeHour * 60 + closeMin;

    while (currentMinutes <= endMinutes) {
      const hours = Math.floor(currentMinutes / 60);
      const mins = currentMinutes % 60;
      result.push(
        `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`
      );
      currentMinutes += 30; // Slots cada 30 minutos
    }
  }

  return result;
}
