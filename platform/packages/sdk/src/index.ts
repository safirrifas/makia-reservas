/**
 * MakIA SDK
 * Cliente TypeScript para la API de MakIA Restaurante
 *
 * @example
 * ```ts
 * import { MakiaClient } from '@makia/sdk';
 *
 * const makia = new MakiaClient({
 *   apiKey: 'mk_live_xxx',
 *   // o baseUrl: 'https://api.contacpro.app/v1'
 * });
 *
 * // Crear reserva
 * const booking = await makia.bookings.create({
 *   customerName: 'Juan García',
 *   customerEmail: 'juan@example.com',
 *   date: '2024-02-15',
 *   time: '20:30',
 *   guests: 4,
 * });
 * ```
 */

export * from './client';
export * from './types';
export * from './resources/bookings';
export * from './resources/availability';
export * from './resources/organizations';
