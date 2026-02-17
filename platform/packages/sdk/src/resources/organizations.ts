import type { MakiaClient } from '../client';
import type {
  Organization,
  OrganizationStats,
  BusinessHours,
  SpecialDay,
} from '../types';

export class OrganizationsResource {
  constructor(private client: MakiaClient) {}

  /**
   * Obtener la organización actual
   *
   * @example
   * ```ts
   * const org = await makia.organizations.getCurrent();
   * console.log(`Plan: ${org.plan}`);
   * ```
   */
  async getCurrent(): Promise<Organization> {
    return this.client.get<Organization>('/organizations/current');
  }

  /**
   * Actualizar la organización actual
   *
   * @example
   * ```ts
   * const updated = await makia.organizations.update({
   *   name: 'Mi Restaurante',
   *   timezone: 'Europe/Madrid',
   * });
   * ```
   */
  async update(data: Partial<Pick<Organization, 'name' | 'email' | 'phone' | 'address' | 'timezone' | 'currency' | 'settings'>>): Promise<Organization> {
    return this.client.patch<Organization>('/organizations/current', data);
  }

  /**
   * Obtener estadísticas de la organización
   *
   * @example
   * ```ts
   * const stats = await makia.organizations.getStats();
   * console.log(`Reservas este mes: ${stats.monthlyBookings}/${stats.plan.limit}`);
   * ```
   */
  async getStats(): Promise<OrganizationStats> {
    return this.client.get<OrganizationStats>('/organizations/current/stats');
  }

  /**
   * Obtener horarios de apertura
   *
   * @example
   * ```ts
   * const hours = await makia.organizations.getBusinessHours();
   * const monday = hours.find(h => h.dayOfWeek === 1);
   * ```
   */
  async getBusinessHours(): Promise<BusinessHours[]> {
    return this.client.get<BusinessHours[]>('/organizations/current/business-hours');
  }

  /**
   * Actualizar horarios de apertura
   *
   * @example
   * ```ts
   * await makia.organizations.updateBusinessHours([
   *   { dayOfWeek: 1, isOpen: true, slots: [{ open: '12:00', close: '16:00' }, { open: '20:00', close: '23:00' }] },
   *   { dayOfWeek: 2, isOpen: true, slots: [{ open: '12:00', close: '16:00' }, { open: '20:00', close: '23:00' }] },
   *   // ... resto de días
   * ]);
   * ```
   */
  async updateBusinessHours(hours: BusinessHours[]): Promise<BusinessHours[]> {
    return this.client.put<BusinessHours[]>('/organizations/current/business-hours', hours);
  }

  /**
   * Obtener días especiales (festivos, cierres)
   *
   * @example
   * ```ts
   * const specialDays = await makia.organizations.getSpecialDays();
   * ```
   */
  async getSpecialDays(): Promise<SpecialDay[]> {
    return this.client.get<SpecialDay[]>('/organizations/current/special-days');
  }

  /**
   * Crear día especial
   *
   * @example
   * ```ts
   * // Marcar como cerrado
   * await makia.organizations.createSpecialDay({
   *   date: '2024-12-25',
   *   type: 'CLOSED',
   *   name: 'Navidad',
   * });
   *
   * // Horario especial
   * await makia.organizations.createSpecialDay({
   *   date: '2024-12-31',
   *   type: 'SPECIAL_HOURS',
   *   name: 'Nochevieja',
   *   slots: [{ open: '20:00', close: '02:00' }],
   * });
   * ```
   */
  async createSpecialDay(data: {
    date: string;
    type: 'CLOSED' | 'SPECIAL_HOURS' | 'HOLIDAY';
    name?: string;
    slots?: { open: string; close: string }[];
  }): Promise<SpecialDay> {
    return this.client.post<SpecialDay>('/organizations/current/special-days', data);
  }

  /**
   * Eliminar día especial
   *
   * @example
   * ```ts
   * await makia.organizations.deleteSpecialDay('special_day_id');
   * ```
   */
  async deleteSpecialDay(id: string): Promise<void> {
    await this.client.delete(`/organizations/current/special-days/${id}`);
  }
}
