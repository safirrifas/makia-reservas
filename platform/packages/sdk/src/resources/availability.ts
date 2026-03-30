import type { MakiaClient } from '../client';
import type {
  AvailabilityResponse,
  CheckAvailabilityInput,
  CheckAvailabilityResponse,
} from '../types';

export class AvailabilityResource {
  constructor(private client: MakiaClient) {}

  /**
   * Obtener slots disponibles para una fecha
   *
   * @example
   * ```ts
   * const availability = await makia.availability.get({
   *   date: '2024-02-15',
   *   guests: 4,
   * });
   *
   * if (availability.isOpen) {
   *   const availableSlots = availability.slots.filter(s => s.available);
   *   console.log('Horarios disponibles:', availableSlots);
   * }
   * ```
   */
  async get(params: { date: string; guests?: number }): Promise<AvailabilityResponse> {
    const org = this.client.getOrganization();

    if (!org) {
      throw new Error('Organization slug required. Use client.setOrganization() or pass ?org= query param');
    }

    return this.client.get<AvailabilityResponse>('/availability', {
      org,
      date: params.date,
      guests: params.guests || 2,
    });
  }

  /**
   * Verificar disponibilidad para una fecha, hora y número de comensales específicos
   *
   * @example
   * ```ts
   * const check = await makia.availability.check({
   *   date: '2024-02-15',
   *   time: '20:30',
   *   guests: 4,
   * });
   *
   * if (check.available) {
   *   console.log('¡Disponible!');
   * } else {
   *   console.log('No disponible:', check.reason);
   * }
   * ```
   */
  async check(params: CheckAvailabilityInput): Promise<CheckAvailabilityResponse> {
    const org = this.client.getOrganization();

    if (!org) {
      throw new Error('Organization slug required. Use client.setOrganization() or pass org in params');
    }

    return this.client.post<CheckAvailabilityResponse>('/availability/check', {
      org,
      ...params,
    });
  }

  /**
   * Obtener disponibilidad para múltiples fechas
   *
   * @example
   * ```ts
   * const dates = ['2024-02-15', '2024-02-16', '2024-02-17'];
   * const availability = await makia.availability.getMultiple(dates, 4);
   * ```
   */
  async getMultiple(
    dates: string[],
    guests: number = 2
  ): Promise<Record<string, AvailabilityResponse>> {
    const results: Record<string, AvailabilityResponse> = {};

    // Hacer peticiones en paralelo
    const promises = dates.map(async (date) => {
      const result = await this.get({ date, guests });
      results[date] = result;
    });

    await Promise.all(promises);
    return results;
  }

  /**
   * Obtener el próximo slot disponible a partir de una fecha
   *
   * @example
   * ```ts
   * const nextSlot = await makia.availability.findNext({
   *   fromDate: '2024-02-15',
   *   guests: 4,
   *   maxDaysAhead: 14,
   * });
   *
   * if (nextSlot) {
   *   console.log(`Próximo disponible: ${nextSlot.date} a las ${nextSlot.time}`);
   * }
   * ```
   */
  async findNext(params: {
    fromDate: string;
    guests: number;
    maxDaysAhead?: number;
    preferredTime?: string;
  }): Promise<{ date: string; time: string; remainingCapacity: number } | null> {
    const maxDays = params.maxDaysAhead || 30;
    const startDate = new Date(params.fromDate);

    for (let i = 0; i < maxDays; i++) {
      const date = new Date(startDate);
      date.setDate(date.getDate() + i);
      const dateStr = date.toISOString().split('T')[0];

      try {
        const availability = await this.get({ date: dateStr, guests: params.guests });

        if (!availability.isOpen) continue;

        // Filtrar slots disponibles
        const availableSlots = availability.slots.filter((s) => s.available);

        if (availableSlots.length === 0) continue;

        // Si hay preferencia de hora, buscar la más cercana
        if (params.preferredTime) {
          const preferred = availableSlots.find((s) => s.time >= params.preferredTime!);
          if (preferred) {
            return {
              date: dateStr,
              time: preferred.time,
              remainingCapacity: preferred.remainingCapacity,
            };
          }
        }

        // Devolver el primer slot disponible
        return {
          date: dateStr,
          time: availableSlots[0].time,
          remainingCapacity: availableSlots[0].remainingCapacity,
        };
      } catch {
        // Ignorar errores y continuar
        continue;
      }
    }

    return null;
  }
}
