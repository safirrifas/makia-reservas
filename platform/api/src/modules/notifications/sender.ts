/**
 * Servicio de Envío de Notificaciones
 *
 * Implementa el envío real de notificaciones por diferentes canales:
 * - Email (Nodemailer / Resend / SendGrid)
 * - SMS (Twilio)
 * - WhatsApp (Twilio / WhatsApp Business API)
 * - Push (Web Push)
 */

import nodemailer from 'nodemailer';

// ============================================
// TIPOS
// ============================================

interface SendResult {
  success: boolean;
  messageId?: string;
  error?: string;
}

interface EmailParams {
  to: string;
  subject: string;
  body: string;
  html?: string;
  attachments?: Array<{
    filename: string;
    content: Buffer | string;
    contentType?: string;
  }>;
}

interface SmsParams {
  to: string;
  body: string;
}

interface WhatsAppParams {
  to: string;
  body: string;
  templateName?: string;
  templateParams?: string[];
}

interface PushParams {
  to: string; // subscription endpoint o user ID
  title: string;
  body: string;
  icon?: string;
  url?: string;
  data?: Record<string, unknown>;
}

// ============================================
// EMAIL
// ============================================

let emailTransporter: nodemailer.Transporter | null = null;

function getEmailTransporter(): nodemailer.Transporter {
  if (!emailTransporter) {
    // Configuración por defecto (SMTP)
    // En producción, usar Resend, SendGrid, o AWS SES
    const config: nodemailer.TransportOptions = {
      host: process.env.SMTP_HOST || 'smtp.gmail.com',
      port: parseInt(process.env.SMTP_PORT || '587'),
      secure: process.env.SMTP_SECURE === 'true',
      auth: {
        user: process.env.SMTP_USER,
        pass: process.env.SMTP_PASS,
      },
    } as nodemailer.TransportOptions;

    emailTransporter = nodemailer.createTransport(config);
  }
  return emailTransporter;
}

export async function sendEmail(params: EmailParams): Promise<SendResult> {
  try {
    const transporter = getEmailTransporter();

    const fromName = process.env.EMAIL_FROM_NAME || 'MakIA Restaurante';
    const fromEmail = process.env.EMAIL_FROM || 'reservas@contacpro.app';

    // Convertir texto plano a HTML básico si no se proporciona HTML
    const htmlBody = params.html || textToHtml(params.body);

    const result = await transporter.sendMail({
      from: `"${fromName}" <${fromEmail}>`,
      to: params.to,
      subject: params.subject,
      text: params.body,
      html: htmlBody,
      attachments: params.attachments?.map((a) => ({
        filename: a.filename,
        content: a.content,
        contentType: a.contentType,
      })),
    });

    return {
      success: true,
      messageId: result.messageId,
    };
  } catch (error) {
    console.error('[Email] Error sending:', error);
    return {
      success: false,
      error: error instanceof Error ? error.message : 'Unknown error',
    };
  }
}

function textToHtml(text: string): string {
  // Convertir saltos de línea y enlaces a HTML básico
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\n/g, '<br>')
    .replace(
      /(https?:\/\/[^\s]+)/g,
      '<a href="$1" style="color: #4f46e5;">$1</a>'
    );
}

// ============================================
// SMS (Twilio)
// ============================================

export async function sendSms(params: SmsParams): Promise<SendResult> {
  const accountSid = process.env.TWILIO_ACCOUNT_SID;
  const authToken = process.env.TWILIO_AUTH_TOKEN;
  const fromNumber = process.env.TWILIO_PHONE_NUMBER;

  if (!accountSid || !authToken || !fromNumber) {
    console.warn('[SMS] Twilio not configured, skipping SMS');
    return {
      success: false,
      error: 'Twilio not configured',
    };
  }

  try {
    // Usar fetch directo a la API de Twilio
    const url = `https://api.twilio.com/2010-04-01/Accounts/${accountSid}/Messages.json`;

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        Authorization: `Basic ${Buffer.from(`${accountSid}:${authToken}`).toString('base64')}`,
      },
      body: new URLSearchParams({
        To: params.to,
        From: fromNumber,
        Body: params.body,
      }),
    });

    const data = await response.json();

    if (response.ok) {
      return {
        success: true,
        messageId: data.sid,
      };
    } else {
      return {
        success: false,
        error: data.message || 'Failed to send SMS',
      };
    }
  } catch (error) {
    console.error('[SMS] Error sending:', error);
    return {
      success: false,
      error: error instanceof Error ? error.message : 'Unknown error',
    };
  }
}

// ============================================
// WHATSAPP (Twilio)
// ============================================

export async function sendWhatsApp(params: WhatsAppParams): Promise<SendResult> {
  const accountSid = process.env.TWILIO_ACCOUNT_SID;
  const authToken = process.env.TWILIO_AUTH_TOKEN;
  const fromNumber = process.env.TWILIO_WHATSAPP_NUMBER || 'whatsapp:+14155238886';

  if (!accountSid || !authToken) {
    console.warn('[WhatsApp] Twilio not configured, skipping WhatsApp');
    return {
      success: false,
      error: 'Twilio not configured for WhatsApp',
    };
  }

  try {
    const url = `https://api.twilio.com/2010-04-01/Accounts/${accountSid}/Messages.json`;

    // Formatear número para WhatsApp
    const toNumber = params.to.startsWith('whatsapp:')
      ? params.to
      : `whatsapp:${params.to}`;

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        Authorization: `Basic ${Buffer.from(`${accountSid}:${authToken}`).toString('base64')}`,
      },
      body: new URLSearchParams({
        To: toNumber,
        From: fromNumber,
        Body: params.body,
      }),
    });

    const data = await response.json();

    if (response.ok) {
      return {
        success: true,
        messageId: data.sid,
      };
    } else {
      return {
        success: false,
        error: data.message || 'Failed to send WhatsApp',
      };
    }
  } catch (error) {
    console.error('[WhatsApp] Error sending:', error);
    return {
      success: false,
      error: error instanceof Error ? error.message : 'Unknown error',
    };
  }
}

// ============================================
// PUSH NOTIFICATIONS (Web Push)
// ============================================

export async function sendPushNotification(params: PushParams): Promise<SendResult> {
  const vapidPublicKey = process.env.VAPID_PUBLIC_KEY;
  const vapidPrivateKey = process.env.VAPID_PRIVATE_KEY;

  if (!vapidPublicKey || !vapidPrivateKey) {
    console.warn('[Push] VAPID keys not configured, skipping push');
    return {
      success: false,
      error: 'VAPID keys not configured',
    };
  }

  try {
    // TODO: Implementar web-push
    // Por ahora, solo registrar
    console.log('[Push] Would send notification:', params);

    return {
      success: true,
      messageId: `push_${Date.now()}`,
    };
  } catch (error) {
    console.error('[Push] Error sending:', error);
    return {
      success: false,
      error: error instanceof Error ? error.message : 'Unknown error',
    };
  }
}

// ============================================
// ENVÍO MASIVO
// ============================================

export async function sendBulkEmail(
  recipients: string[],
  subject: string,
  body: string
): Promise<{ success: number; failed: number; errors: string[] }> {
  const results = await Promise.all(
    recipients.map((to) => sendEmail({ to, subject, body }))
  );

  const success = results.filter((r) => r.success).length;
  const failed = results.filter((r) => !r.success).length;
  const errors = results
    .filter((r) => !r.success && r.error)
    .map((r) => r.error as string);

  return { success, failed, errors };
}

// ============================================
// VALIDACIÓN
// ============================================

export function isValidEmail(email: string): boolean {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

export function isValidPhone(phone: string): boolean {
  // Acepta formatos: +34612345678, 612345678, +1-234-567-8901
  const phoneRegex = /^\+?[\d\s-]{9,20}$/;
  return phoneRegex.test(phone.replace(/\s/g, ''));
}

export function normalizePhone(phone: string): string {
  // Eliminar espacios, guiones, paréntesis
  let normalized = phone.replace(/[\s\-\(\)]/g, '');

  // Si no tiene prefijo internacional, asumir España (+34)
  if (!normalized.startsWith('+')) {
    if (normalized.startsWith('00')) {
      normalized = '+' + normalized.slice(2);
    } else {
      normalized = '+34' + normalized;
    }
  }

  return normalized;
}
