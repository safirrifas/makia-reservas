/**
 * Scheduler de Notificaciones
 *
 * Maneja trabajos programados como:
 * - Recordatorios de reserva (24h antes)
 * - Seguimiento post-reserva
 * - Reportes diarios/semanales
 */

import { prisma } from '../../shared/database/client';
import { queueNotificationFromTemplate } from './queue';
import { generateBookingIcs } from './ics-generator';
import { format, addHours, subHours, startOfDay, endOfDay } from 'date-fns';
import { es } from 'date-fns/locale';

// ============================================
// TIPOS
// ============================================

interface SchedulerConfig {
  reminderHoursBefore: number; // Por defecto 24h
  enableReminders: boolean;
  enableFollowups: boolean;
  followupHoursAfter: number; // Por defecto 24h después
}

const DEFAULT_CONFIG: SchedulerConfig = {
  reminderHoursBefore: 24,
  enableReminders: true,
  enableFollowups: false,
  followupHoursAfter: 24,
};

// ============================================
// SCHEDULER PRINCIPAL
// ============================================

/**
 * Ejecutar el scheduler de recordatorios
 * Llamar desde un cron job cada hora o cada 15 minutos
 */
export async function runReminderScheduler(): Promise<{
  processed: number;
  sent: number;
  errors: string[];
}> {
  console.log('[Scheduler] Running reminder scheduler...');

  const now = new Date();
  const errors: string[] = [];
  let processed = 0;
  let sent = 0;

  try {
    // Buscar reservas confirmadas que necesitan recordatorio
    // (booking_date - reminder_hours <= now) AND no se ha enviado recordatorio
    const reminderWindow = addHours(now, DEFAULT_CONFIG.reminderHoursBefore);

    const bookings = await prisma.booking.findMany({
      where: {
        status: 'CONFIRMED',
        bookingDate: {
          gte: startOfDay(now),
          lte: endOfDay(reminderWindow),
        },
      },
      include: {
        organization: true,
      },
    });

    for (const booking of bookings) {
      processed++;

      // Verificar si ya se envió recordatorio
      const existingReminder = await prisma.notificationLog.findFirst({
        where: {
          bookingId: booking.id,
          event: 'BOOKING_REMINDER',
          status: { in: ['SENT', 'DELIVERED', 'QUEUED', 'SENDING'] },
        },
      });

      if (existingReminder) {
        continue; // Ya se envió o está en cola
      }

      // Calcular cuándo debería enviarse el recordatorio
      const bookingDateTime = combineDateAndTime(booking.bookingDate, booking.bookingTime);
      const reminderTime = subHours(bookingDateTime, DEFAULT_CONFIG.reminderHoursBefore);

      // Si el recordatorio debería haberse enviado ya (o en los próximos 15 min)
      const shouldSendNow = reminderTime <= addHours(now, 0.25); // 15 min de margen

      if (!shouldSendNow) {
        // Programar para más tarde
        await scheduleReminder(booking, booking.organization, reminderTime);
        continue;
      }

      // Enviar ahora
      try {
        await sendBookingReminder(booking, booking.organization);
        sent++;
      } catch (error) {
        errors.push(`Booking ${booking.id}: ${error instanceof Error ? error.message : 'Unknown error'}`);
      }
    }

    console.log(`[Scheduler] Processed: ${processed}, Sent: ${sent}, Errors: ${errors.length}`);

    return { processed, sent, errors };
  } catch (error) {
    console.error('[Scheduler] Error:', error);
    throw error;
  }
}

// ============================================
// ENVIAR RECORDATORIO
// ============================================

async function sendBookingReminder(
  booking: any,
  organization: any
): Promise<void> {
  const bookingDateTime = combineDateAndTime(booking.bookingDate, booking.bookingTime);

  // Variables para la plantilla
  const variables: Record<string, string> = {
    nombre: booking.customerName,
    email: booking.customerEmail,
    telefono: booking.customerPhone || '',
    fecha: format(booking.bookingDate, "EEEE d 'de' MMMM", { locale: es }),
    hora: booking.bookingTime,
    comensales: booking.guests.toString(),
    ocasion: booking.occasion || '',
    notas: booking.specialRequests || '',
    restaurante: organization.name,
    direccion: organization.address || '',
    telefono_restaurante: organization.phone || '',
    enlace_gestion: `${process.env.APP_URL || 'https://contacpro.app'}/reserva/${booking.editToken}`,
  };

  // Generar archivo ICS
  const icsData = {
    title: `Reserva en ${organization.name}`,
    description: `Reserva para ${booking.guests} personas`,
    location: organization.address || '',
    startDate: bookingDateTime,
    endDate: addHours(bookingDateTime, 2),
  };

  // Enviar por email
  if (booking.customerEmail) {
    await queueNotificationFromTemplate({
      organizationId: organization.id,
      bookingId: booking.id,
      channel: 'EMAIL',
      event: 'BOOKING_REMINDER',
      recipient: booking.customerEmail,
      variables,
      attachIcs: true,
      icsData,
    });
  }

  // Enviar por SMS si hay teléfono y está en un plan que lo permite
  if (booking.customerPhone && ['PRO', 'BUSINESS'].includes(organization.plan)) {
    await queueNotificationFromTemplate({
      organizationId: organization.id,
      bookingId: booking.id,
      channel: 'SMS',
      event: 'BOOKING_REMINDER',
      recipient: booking.customerPhone,
      variables,
    });
  }

  // Enviar por WhatsApp si hay teléfono y está en plan BUSINESS
  if (booking.customerPhone && organization.plan === 'BUSINESS') {
    await queueNotificationFromTemplate({
      organizationId: organization.id,
      bookingId: booking.id,
      channel: 'WHATSAPP',
      event: 'BOOKING_REMINDER',
      recipient: booking.customerPhone,
      variables,
    });
  }
}

// ============================================
// PROGRAMAR RECORDATORIO FUTURO
// ============================================

async function scheduleReminder(
  booking: any,
  organization: any,
  scheduledFor: Date
): Promise<void> {
  // Crear trabajo programado
  await prisma.scheduledJob.upsert({
    where: {
      id: `reminder-${booking.id}`, // Usar ID fijo para evitar duplicados
    },
    create: {
      id: `reminder-${booking.id}`,
      type: 'BOOKING_REMINDER',
      organizationId: organization.id,
      bookingId: booking.id,
      scheduledFor,
      payload: {
        bookingId: booking.id,
        organizationId: organization.id,
      },
    },
    update: {
      scheduledFor,
      status: 'PENDING',
    },
  });
}

// ============================================
// EJECUTAR TRABAJOS PROGRAMADOS
// ============================================

export async function runScheduledJobs(): Promise<{
  executed: number;
  failed: number;
}> {
  console.log('[Scheduler] Running scheduled jobs...');

  const now = new Date();
  let executed = 0;
  let failed = 0;

  // Buscar trabajos pendientes que deberían ejecutarse
  const jobs = await prisma.scheduledJob.findMany({
    where: {
      status: 'PENDING',
      scheduledFor: { lte: now },
    },
    take: 100, // Procesar en lotes
  });

  for (const job of jobs) {
    try {
      // Marcar como en ejecución
      await prisma.scheduledJob.update({
        where: { id: job.id },
        data: { status: 'RUNNING', attempts: { increment: 1 } },
      });

      // Ejecutar según tipo
      switch (job.type) {
        case 'BOOKING_REMINDER':
          await executeBookingReminderJob(job);
          break;

        case 'BOOKING_FOLLOWUP':
          // TODO: Implementar followup
          break;

        case 'REPORT_DAILY':
          // TODO: Implementar reporte diario
          break;

        default:
          console.warn(`[Scheduler] Unknown job type: ${job.type}`);
      }

      // Marcar como completado
      await prisma.scheduledJob.update({
        where: { id: job.id },
        data: { status: 'COMPLETED', executedAt: new Date() },
      });

      executed++;
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Unknown error';

      // Si no ha superado los reintentos, volver a PENDING
      if (job.attempts < job.maxAttempts) {
        await prisma.scheduledJob.update({
          where: { id: job.id },
          data: {
            status: 'PENDING',
            error: errorMessage,
            // Retry en 5 minutos
            scheduledFor: addHours(now, 5 / 60),
          },
        });
      } else {
        // Marcar como fallido
        await prisma.scheduledJob.update({
          where: { id: job.id },
          data: { status: 'FAILED', error: errorMessage },
        });
      }

      failed++;
    }
  }

  console.log(`[Scheduler] Executed: ${executed}, Failed: ${failed}`);

  return { executed, failed };
}

async function executeBookingReminderJob(job: any): Promise<void> {
  const booking = await prisma.booking.findUnique({
    where: { id: job.bookingId },
    include: { organization: true },
  });

  if (!booking) {
    throw new Error('Booking not found');
  }

  // Solo enviar si sigue confirmada
  if (booking.status !== 'CONFIRMED') {
    console.log(`[Scheduler] Booking ${booking.id} is no longer confirmed, skipping reminder`);
    return;
  }

  await sendBookingReminder(booking, booking.organization);
}

// ============================================
// CANCELAR RECORDATORIO
// ============================================

export async function cancelBookingReminder(bookingId: string): Promise<void> {
  await prisma.scheduledJob.updateMany({
    where: {
      bookingId,
      type: 'BOOKING_REMINDER',
      status: 'PENDING',
    },
    data: { status: 'CANCELLED' },
  });
}

// ============================================
// HELPERS
// ============================================

function combineDateAndTime(date: Date, time: string): Date {
  const [hours, minutes] = time.split(':').map(Number);
  const combined = new Date(date);
  combined.setHours(hours, minutes, 0, 0);
  return combined;
}

// ============================================
// CRON ENTRY POINT
// ============================================

/**
 * Función principal para ejecutar desde cron
 * Llamar cada 15 minutos: */15 * * * *
 */
export async function cronHandler(): Promise<void> {
  console.log(`[Cron] Starting at ${new Date().toISOString()}`);

  try {
    // 1. Ejecutar trabajos programados
    await runScheduledJobs();

    // 2. Procesar recordatorios
    await runReminderScheduler();

    console.log('[Cron] Completed successfully');
  } catch (error) {
    console.error('[Cron] Error:', error);
  }
}
