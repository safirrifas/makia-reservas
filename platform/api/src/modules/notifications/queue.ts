/**
 * Cola de Notificaciones
 *
 * Maneja el envío asíncrono de notificaciones usando Redis/BullMQ
 * con soporte para reintentos, programación y logs.
 */

import { Queue, Worker, Job } from 'bullmq';
import { prisma } from '../../shared/database/client';
import { sendEmail, sendSms, sendWhatsApp, sendPushNotification } from './sender';
import { processTemplate } from './templates';
import { generateIcsFile } from './ics-generator';
import type { NotificationType, NotificationEvent, NotificationStatus } from '@prisma/client';

// ============================================
// CONFIGURACIÓN
// ============================================

const REDIS_CONFIG = {
  host: process.env.REDIS_HOST || 'localhost',
  port: parseInt(process.env.REDIS_PORT || '6379'),
  password: process.env.REDIS_PASSWORD,
};

const QUEUE_NAME = 'makia-notifications';

// ============================================
// TIPOS
// ============================================

export interface NotificationJobData {
  logId: string;
  organizationId: string;
  bookingId?: string;
  channel: NotificationType;
  event: NotificationEvent;
  recipient: string;
  subject?: string;
  body: string;
  attachIcs?: boolean;
  icsData?: {
    title: string;
    description: string;
    location: string;
    startDate: Date;
    endDate: Date;
  };
  metadata?: Record<string, unknown>;
}

export interface QueueNotificationParams {
  organizationId: string;
  bookingId?: string;
  channel: NotificationType;
  event: NotificationEvent;
  recipient: string;
  subject?: string;
  body: string;
  scheduledFor?: Date;
  attachIcs?: boolean;
  icsData?: NotificationJobData['icsData'];
  metadata?: Record<string, unknown>;
}

// ============================================
// COLA
// ============================================

let notificationQueue: Queue<NotificationJobData> | null = null;

export function getNotificationQueue(): Queue<NotificationJobData> {
  if (!notificationQueue) {
    notificationQueue = new Queue<NotificationJobData>(QUEUE_NAME, {
      connection: REDIS_CONFIG,
      defaultJobOptions: {
        attempts: 3,
        backoff: {
          type: 'exponential',
          delay: 5000, // 5s inicial, luego 10s, 20s...
        },
        removeOnComplete: {
          count: 1000, // Mantener últimos 1000 completados
          age: 24 * 60 * 60, // Mantener por 24h
        },
        removeOnFail: {
          count: 5000, // Mantener últimos 5000 fallidos
          age: 7 * 24 * 60 * 60, // Mantener por 7 días
        },
      },
    });
  }
  return notificationQueue;
}

// ============================================
// ENCOLAR NOTIFICACIÓN
// ============================================

export async function queueNotification(
  params: QueueNotificationParams
): Promise<{ logId: string; jobId: string }> {
  const queue = getNotificationQueue();

  // Verificar opt-out del destinatario
  const optedOut = await checkOptOut(
    params.organizationId,
    params.channel,
    params.recipient
  );

  if (optedOut) {
    // Crear log como cancelado
    const log = await prisma.notificationLog.create({
      data: {
        organizationId: params.organizationId,
        bookingId: params.bookingId,
        channel: params.channel,
        event: params.event,
        recipient: params.recipient,
        subject: params.subject,
        body: params.body,
        status: 'CANCELLED',
        metadata: { reason: 'opt_out', ...params.metadata },
      },
    });

    return { logId: log.id, jobId: '' };
  }

  // Crear log en estado QUEUED
  const log = await prisma.notificationLog.create({
    data: {
      organizationId: params.organizationId,
      bookingId: params.bookingId,
      channel: params.channel,
      event: params.event,
      recipient: params.recipient,
      subject: params.subject,
      body: params.body,
      status: 'QUEUED',
      scheduledFor: params.scheduledFor,
      metadata: params.metadata || {},
    },
  });

  // Crear job data
  const jobData: NotificationJobData = {
    logId: log.id,
    organizationId: params.organizationId,
    bookingId: params.bookingId,
    channel: params.channel,
    event: params.event,
    recipient: params.recipient,
    subject: params.subject,
    body: params.body,
    attachIcs: params.attachIcs,
    icsData: params.icsData,
    metadata: params.metadata,
  };

  // Encolar job
  const jobOptions: { delay?: number; jobId?: string } = {
    jobId: `notification-${log.id}`,
  };

  // Si está programado para el futuro, calcular delay
  if (params.scheduledFor && params.scheduledFor > new Date()) {
    jobOptions.delay = params.scheduledFor.getTime() - Date.now();
  }

  const job = await queue.add('send-notification', jobData, jobOptions);

  return { logId: log.id, jobId: job.id || '' };
}

// ============================================
// ENCOLAR NOTIFICACIÓN DESDE TEMPLATE
// ============================================

export async function queueNotificationFromTemplate(params: {
  organizationId: string;
  bookingId?: string;
  channel: NotificationType;
  event: NotificationEvent;
  recipient: string;
  variables: Record<string, string>;
  scheduledFor?: Date;
  attachIcs?: boolean;
  icsData?: NotificationJobData['icsData'];
}): Promise<{ logId: string; jobId: string } | null> {
  // Buscar plantilla activa
  const template = await prisma.notificationTemplate.findFirst({
    where: {
      organizationId: params.organizationId,
      type: params.channel,
      event: params.event,
      isActive: true,
    },
  });

  if (!template) {
    console.warn(
      `[Notifications] No active template found for ${params.channel}/${params.event}`
    );
    return null;
  }

  // Procesar plantilla
  const { subject, body } = processTemplate(
    { subject: template.subject, body: template.body },
    params.variables
  );

  return queueNotification({
    organizationId: params.organizationId,
    bookingId: params.bookingId,
    channel: params.channel,
    event: params.event,
    recipient: params.recipient,
    subject,
    body,
    scheduledFor: params.scheduledFor,
    attachIcs: params.attachIcs,
    icsData: params.icsData,
    metadata: { templateId: template.id },
  });
}

// ============================================
// WORKER
// ============================================

let notificationWorker: Worker<NotificationJobData> | null = null;

export function startNotificationWorker(): Worker<NotificationJobData> {
  if (notificationWorker) {
    return notificationWorker;
  }

  notificationWorker = new Worker<NotificationJobData>(
    QUEUE_NAME,
    async (job: Job<NotificationJobData>) => {
      const { logId, channel, recipient, subject, body, attachIcs, icsData } = job.data;

      // Actualizar estado a SENDING
      await prisma.notificationLog.update({
        where: { id: logId },
        data: {
          status: 'SENDING',
          attempts: { increment: 1 },
        },
      });

      try {
        let result: { success: boolean; messageId?: string; error?: string };

        // Generar archivo ICS si es necesario
        let icsAttachment: Buffer | undefined;
        if (attachIcs && icsData) {
          icsAttachment = generateIcsFile(icsData);
        }

        // Enviar según canal
        switch (channel) {
          case 'EMAIL':
            result = await sendEmail({
              to: recipient,
              subject: subject || '',
              body,
              attachments: icsAttachment
                ? [{ filename: 'reserva.ics', content: icsAttachment }]
                : undefined,
            });
            break;

          case 'SMS':
            result = await sendSms({ to: recipient, body });
            break;

          case 'WHATSAPP':
            result = await sendWhatsApp({ to: recipient, body });
            break;

          case 'PUSH':
            result = await sendPushNotification({
              to: recipient,
              title: subject || 'MakIA Reservas',
              body,
            });
            break;

          default:
            throw new Error(`Unknown channel: ${channel}`);
        }

        if (result.success) {
          // Actualizar log como SENT
          await prisma.notificationLog.update({
            where: { id: logId },
            data: {
              status: 'SENT',
              sentAt: new Date(),
              metadata: {
                ...(job.data.metadata || {}),
                messageId: result.messageId,
              },
            },
          });

          return { success: true, messageId: result.messageId };
        } else {
          throw new Error(result.error || 'Unknown error');
        }
      } catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Unknown error';

        // Si es el último intento, marcar como FAILED
        if (job.attemptsMade >= (job.opts.attempts || 3) - 1) {
          await prisma.notificationLog.update({
            where: { id: logId },
            data: {
              status: 'FAILED',
              error: errorMessage,
              failedAt: new Date(),
            },
          });
        }

        throw error; // Re-throw para que BullMQ maneje el retry
      }
    },
    {
      connection: REDIS_CONFIG,
      concurrency: 10, // Procesar hasta 10 notificaciones en paralelo
    }
  );

  // Event handlers
  notificationWorker.on('completed', (job) => {
    console.log(`[Notifications] Job ${job.id} completed`);
  });

  notificationWorker.on('failed', (job, error) => {
    console.error(`[Notifications] Job ${job?.id} failed:`, error.message);
  });

  return notificationWorker;
}

// ============================================
// HELPERS
// ============================================

async function checkOptOut(
  organizationId: string,
  channel: NotificationType,
  recipient: string
): Promise<boolean> {
  const isEmail = recipient.includes('@');

  const preference = await prisma.notificationPreference.findFirst({
    where: {
      organizationId,
      OR: [
        isEmail ? { email: recipient } : { phone: recipient },
      ],
    },
  });

  if (!preference) return false;

  switch (channel) {
    case 'EMAIL':
      return preference.optOutEmail;
    case 'SMS':
      return preference.optOutSms;
    case 'WHATSAPP':
      return preference.optOutWhatsapp;
    case 'PUSH':
      return preference.optOutPush;
    default:
      return false;
  }
}

// ============================================
// CANCELAR NOTIFICACIÓN PROGRAMADA
// ============================================

export async function cancelScheduledNotification(logId: string): Promise<boolean> {
  const queue = getNotificationQueue();

  const job = await queue.getJob(`notification-${logId}`);

  if (job) {
    await job.remove();
  }

  await prisma.notificationLog.update({
    where: { id: logId },
    data: { status: 'CANCELLED' },
  });

  return true;
}

// ============================================
// ESTADÍSTICAS
// ============================================

export async function getQueueStats() {
  const queue = getNotificationQueue();

  const [waiting, active, completed, failed, delayed] = await Promise.all([
    queue.getWaitingCount(),
    queue.getActiveCount(),
    queue.getCompletedCount(),
    queue.getFailedCount(),
    queue.getDelayedCount(),
  ]);

  return { waiting, active, completed, failed, delayed };
}
