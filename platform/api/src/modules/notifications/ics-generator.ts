/**
 * Generador de archivos ICS (iCalendar)
 *
 * Crea archivos .ics para que los clientes puedan agregar
 * sus reservas al calendario (Google Calendar, Apple Calendar, Outlook, etc.)
 */

// ============================================
// TIPOS
// ============================================

export interface IcsEventData {
  title: string;
  description: string;
  location: string;
  startDate: Date;
  endDate: Date;
  organizer?: {
    name: string;
    email: string;
  };
  attendee?: {
    name: string;
    email: string;
  };
  url?: string;
  uid?: string;
  reminder?: number; // minutos antes
}

// ============================================
// GENERADOR
// ============================================

export function generateIcsFile(event: IcsEventData): Buffer {
  const uid = event.uid || `${Date.now()}-${Math.random().toString(36).slice(2)}@contacpro.app`;
  const now = formatIcsDate(new Date());
  const start = formatIcsDate(event.startDate);
  const end = formatIcsDate(event.endDate);

  const lines: string[] = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//MakIA Restaurante//ES',
    'CALSCALE:GREGORIAN',
    'METHOD:REQUEST',
    'BEGIN:VEVENT',
    `UID:${uid}`,
    `DTSTAMP:${now}`,
    `DTSTART:${start}`,
    `DTEND:${end}`,
    `SUMMARY:${escapeIcsText(event.title)}`,
    `DESCRIPTION:${escapeIcsText(event.description)}`,
    `LOCATION:${escapeIcsText(event.location)}`,
    'STATUS:CONFIRMED',
    'SEQUENCE:0',
  ];

  // Organizador
  if (event.organizer) {
    lines.push(
      `ORGANIZER;CN="${escapeIcsText(event.organizer.name)}":mailto:${event.organizer.email}`
    );
  }

  // Asistente
  if (event.attendee) {
    lines.push(
      `ATTENDEE;CN="${escapeIcsText(event.attendee.name)}";RSVP=TRUE;PARTSTAT=ACCEPTED:mailto:${event.attendee.email}`
    );
  }

  // URL
  if (event.url) {
    lines.push(`URL:${event.url}`);
  }

  // Recordatorio (alarma)
  if (event.reminder !== undefined) {
    lines.push(
      'BEGIN:VALARM',
      'ACTION:DISPLAY',
      `DESCRIPTION:Recordatorio: ${escapeIcsText(event.title)}`,
      `TRIGGER:-PT${event.reminder}M`,
      'END:VALARM'
    );
  }

  lines.push('END:VEVENT', 'END:VCALENDAR');

  // Unir con CRLF según especificación iCalendar
  const icsContent = lines.join('\r\n');

  return Buffer.from(icsContent, 'utf-8');
}

// ============================================
// HELPERS
// ============================================

function formatIcsDate(date: Date): string {
  // Formato: YYYYMMDDTHHMMSSZ (UTC)
  return date.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
}

function escapeIcsText(text: string): string {
  // Escapar caracteres especiales según RFC 5545
  return text
    .replace(/\\/g, '\\\\')
    .replace(/;/g, '\\;')
    .replace(/,/g, '\\,')
    .replace(/\n/g, '\\n');
}

// ============================================
// CREAR ICS PARA RESERVA
// ============================================

export interface BookingIcsParams {
  customerName: string;
  customerEmail: string;
  restaurantName: string;
  restaurantEmail: string;
  restaurantAddress: string;
  restaurantPhone?: string;
  bookingDate: Date;
  bookingTime: string; // "20:30"
  guests: number;
  occasion?: string;
  specialRequests?: string;
  managementUrl?: string;
}

export function generateBookingIcs(params: BookingIcsParams): Buffer {
  // Parsear hora y crear fecha/hora de inicio
  const [hours, minutes] = params.bookingTime.split(':').map(Number);
  const startDate = new Date(params.bookingDate);
  startDate.setHours(hours, minutes, 0, 0);

  // Duración estimada: 2 horas
  const endDate = new Date(startDate);
  endDate.setHours(endDate.getHours() + 2);

  // Construir descripción
  let description = `Reserva en ${params.restaurantName}\n\n`;
  description += `Comensales: ${params.guests}\n`;
  if (params.occasion) {
    description += `Ocasión: ${params.occasion}\n`;
  }
  if (params.specialRequests) {
    description += `\nNotas: ${params.specialRequests}\n`;
  }
  if (params.restaurantPhone) {
    description += `\nTeléfono: ${params.restaurantPhone}\n`;
  }
  if (params.managementUrl) {
    description += `\nModificar/Cancelar: ${params.managementUrl}\n`;
  }

  return generateIcsFile({
    title: `Reserva en ${params.restaurantName}`,
    description,
    location: params.restaurantAddress,
    startDate,
    endDate,
    organizer: {
      name: params.restaurantName,
      email: params.restaurantEmail,
    },
    attendee: {
      name: params.customerName,
      email: params.customerEmail,
    },
    url: params.managementUrl,
    reminder: 60, // Recordatorio 1 hora antes
  });
}

// ============================================
// ENDPOINT HELPER
// ============================================

export function getIcsContentType(): string {
  return 'text/calendar; charset=utf-8';
}

export function getIcsFilename(restaurantName: string, date: Date): string {
  const dateStr = date.toISOString().split('T')[0];
  const safeName = restaurantName
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
  return `reserva-${safeName}-${dateStr}.ics`;
}
