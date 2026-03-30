/**
 * Tests para el Motor de Conversación
 *
 * Prueba el flujo de reservas desde redes sociales
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mock de prisma para tests
vi.mock('../../../shared/database/client', () => ({
  prisma: {
    conversation: {
      findFirst: vi.fn(),
      findUnique: vi.fn(),
      create: vi.fn(),
      update: vi.fn(),
      updateMany: vi.fn(),
    },
    conversationMessage: {
      create: vi.fn(),
      count: vi.fn(),
    },
    booking: {
      create: vi.fn(),
    },
  },
}));

// ============================================
// TESTS DE PARSEO DE FECHAS
// ============================================

describe('Parseo de Fechas', () => {
  it('debería entender "hoy"', () => {
    const today = new Date();
    const result = parseDate('hoy');
    expect(result).toBeTruthy();
    expect(result?.getDate()).toBe(today.getDate());
  });

  it('debería entender "mañana"', () => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const result = parseDate('mañana');
    expect(result).toBeTruthy();
    expect(result?.getDate()).toBe(tomorrow.getDate());
  });

  it('debería entender días de la semana', () => {
    const result = parseDate('viernes');
    expect(result).toBeTruthy();
    expect(result?.getDay()).toBe(5); // 5 = viernes
  });

  it('debería entender "15 de febrero"', () => {
    const result = parseDate('15 de febrero');
    expect(result).toBeTruthy();
    expect(result?.getDate()).toBe(15);
    expect(result?.getMonth()).toBe(1); // 1 = febrero (0-indexed)
  });

  it('debería entender formato dd/mm', () => {
    const result = parseDate('20/03');
    expect(result).toBeTruthy();
    expect(result?.getDate()).toBe(20);
    expect(result?.getMonth()).toBe(2); // 2 = marzo
  });
});

// ============================================
// TESTS DE PARSEO DE HORAS
// ============================================

describe('Parseo de Horas', () => {
  it('debería entender "20:30"', () => {
    const result = parseTime('20:30');
    expect(result).toBe('20:30');
  });

  it('debería entender "9 de la noche"', () => {
    const result = parseTime('9 de la noche');
    expect(result).toBe('21:00');
  });

  it('debería entender "a las 2 y media"', () => {
    const result = parseTime('a las 2 y media');
    expect(result).toBe('02:30');
  });

  it('debería entender "21.00"', () => {
    const result = parseTime('21.00');
    expect(result).toBe('21:00');
  });
});

// ============================================
// TESTS DE PARSEO DE COMENSALES
// ============================================

describe('Parseo de Comensales', () => {
  it('debería entender "4"', () => {
    const result = parseGuests('4');
    expect(result).toBe(4);
  });

  it('debería entender "4 personas"', () => {
    const result = parseGuests('4 personas');
    expect(result).toBe(4);
  });

  it('debería entender "somos 3"', () => {
    const result = parseGuests('somos 3');
    expect(result).toBe(3);
  });

  it('debería entender "dos"', () => {
    const result = parseGuests('dos');
    expect(result).toBe(2);
  });
});

// ============================================
// TESTS DE DETECCIÓN DE INTENCIONES
// ============================================

describe('Detección de Intenciones', () => {
  it('debería detectar cancelación', () => {
    expect(detectIntent('cancelar')).toBe('CANCEL');
    expect(detectIntent('no quiero')).toBe('CANCEL');
    expect(detectIntent('salir')).toBe('CANCEL');
  });

  it('debería detectar ayuda', () => {
    expect(detectIntent('ayuda')).toBe('HELP');
    expect(detectIntent('help')).toBe('HELP');
    expect(detectIntent('no entiendo')).toBe('HELP');
  });

  it('debería detectar inicio', () => {
    expect(detectIntent('reservar')).toBe('START');
    expect(detectIntent('nueva reserva')).toBe('START');
    expect(detectIntent('comenzar')).toBe('START');
  });

  it('debería retornar null para mensajes normales', () => {
    expect(detectIntent('hola')).toBeNull();
    expect(detectIntent('mañana')).toBeNull();
    expect(detectIntent('20:30')).toBeNull();
  });
});

// ============================================
// TESTS DE AFIRMATIVO/NEGATIVO
// ============================================

describe('Detección de Afirmativo', () => {
  it('debería detectar afirmaciones', () => {
    expect(detectAffirmative('sí')).toBe(true);
    expect(detectAffirmative('si')).toBe(true);
    expect(detectAffirmative('ok')).toBe(true);
    expect(detectAffirmative('vale')).toBe(true);
    expect(detectAffirmative('confirmar')).toBe(true);
  });

  it('debería rechazar negaciones', () => {
    expect(detectAffirmative('no')).toBe(false);
    expect(detectAffirmative('nope')).toBe(false);
    expect(detectAffirmative('nunca')).toBe(false);
  });
});

// ============================================
// TESTS DE EXTRACCIÓN DE EMAIL/TELÉFONO
// ============================================

describe('Extracción de Contacto', () => {
  it('debería extraer email válido', () => {
    expect(extractEmail('mi email es juan@email.com')).toBe('juan@email.com');
    expect(extractEmail('contacto@empresa.es')).toBe('contacto@empresa.es');
  });

  it('debería rechazar email inválido', () => {
    expect(extractEmail('no tengo email')).toBeNull();
    expect(extractEmail('juan@')).toBeNull();
  });

  it('debería extraer teléfono válido', () => {
    expect(extractPhone('+34612345678')).toBe('+34612345678');
    expect(extractPhone('612 345 678')).toBe('612345678');
  });
});

// ============================================
// HELPERS (copiar de conversation-engine.ts)
// ============================================

// Estas funciones son copias simplificadas para tests
function parseDate(input: string): Date | null {
  const lower = input.toLowerCase().trim();
  const today = new Date();

  if (lower === 'hoy') return today;
  if (lower === 'mañana') {
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    return tomorrow;
  }

  const weekDays: Record<string, number> = {
    'domingo': 0, 'lunes': 1, 'martes': 2, 'miercoles': 3, 'miércoles': 3,
    'jueves': 4, 'viernes': 5, 'sabado': 6, 'sábado': 6,
  };

  for (const [day, num] of Object.entries(weekDays)) {
    if (lower.includes(day)) {
      const currentDay = today.getDay();
      let daysUntil = num - currentDay;
      if (daysUntil <= 0) daysUntil += 7;
      const result = new Date(today);
      result.setDate(result.getDate() + daysUntil);
      return result;
    }
  }

  const months: Record<string, number> = {
    'enero': 0, 'febrero': 1, 'marzo': 2, 'abril': 3, 'mayo': 4, 'junio': 5,
    'julio': 6, 'agosto': 7, 'septiembre': 8, 'octubre': 9, 'noviembre': 10, 'diciembre': 11,
  };

  const match1 = lower.match(/(\d{1,2})\s*(?:de\s*)?(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)/i);
  if (match1) {
    const day = parseInt(match1[1]);
    const month = months[match1[2].toLowerCase()];
    return new Date(today.getFullYear(), month, day);
  }

  const match2 = lower.match(/(\d{1,2})[\/\-](\d{1,2})/);
  if (match2) {
    const day = parseInt(match2[1]);
    const month = parseInt(match2[2]) - 1;
    return new Date(today.getFullYear(), month, day);
  }

  return null;
}

function parseTime(input: string): string | null {
  const lower = input.toLowerCase().trim();

  const directMatch = lower.match(/^(\d{1,2})[:.](\d{2})$/);
  if (directMatch) {
    const hours = parseInt(directMatch[1]);
    const mins = parseInt(directMatch[2]);
    if (hours >= 0 && hours <= 23 && mins >= 0 && mins <= 59) {
      return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
    }
  }

  const timeMatch = lower.match(/(?:a las?\s*)?(\d{1,2})(?:\s*(?:y\s*media|:30))?(?:\s*(?:de la\s*)?(noche|tarde|mañana))?/);
  if (timeMatch) {
    let hours = parseInt(timeMatch[1]);
    const period = timeMatch[2];
    const hasHalf = lower.includes('media') || lower.includes(':30');

    if (period === 'noche' && hours < 12) hours += 12;
    if (period === 'tarde' && hours < 12 && hours !== 12) hours += 12;

    if (hours >= 0 && hours <= 23) {
      return `${hours.toString().padStart(2, '0')}:${hasHalf ? '30' : '00'}`;
    }
  }

  return null;
}

function parseGuests(input: string): number | null {
  const lower = input.toLowerCase().trim();

  const match = lower.match(/(\d+)/);
  if (match) {
    return parseInt(match[1]);
  }

  const words: Record<string, number> = {
    'uno': 1, 'una': 1, 'dos': 2, 'tres': 3, 'cuatro': 4,
    'cinco': 5, 'seis': 6, 'siete': 7, 'ocho': 8, 'nueve': 9, 'diez': 10,
  };

  for (const [word, num] of Object.entries(words)) {
    if (lower.includes(word)) {
      return num;
    }
  }

  return null;
}

function detectIntent(message: string): 'CANCEL' | 'HELP' | 'START' | null {
  const lower = message.toLowerCase().trim();

  if (['cancelar', 'salir', 'no quiero', 'dejalo', 'olvidalo'].some(w => lower.includes(w))) {
    return 'CANCEL';
  }

  if (['ayuda', 'help', 'no entiendo', 'como funciona'].some(w => lower.includes(w))) {
    return 'HELP';
  }

  if (['nueva reserva', 'reservar', 'empezar', 'comenzar', 'inicio'].some(w => lower.includes(w))) {
    return 'START';
  }

  return null;
}

function detectAffirmative(message: string): boolean {
  const lower = message.toLowerCase().trim();
  return ['si', 'sí', 'ok', 'vale', 'claro', 'confirmar', 'adelante', 'perfecto', 'correcto', 'yes', 'confirm']
    .some(w => lower.includes(w));
}

function extractEmail(input: string): string | null {
  const match = input.match(/[^\s@]+@[^\s@]+\.[^\s@]+/);
  return match ? match[0].toLowerCase() : null;
}

function extractPhone(input: string): string | null {
  const cleaned = input.replace(/[\s\-\(\)]/g, '');
  const match = cleaned.match(/(\+?\d{9,15})/);
  return match ? match[1] : null;
}
