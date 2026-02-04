import type { MakiaClient } from '../client';
import type {
  Booking,
  CreateBookingInput,
  UpdateBookingInput,
  BookingListParams,
  PaginationMeta,
} from '../types';

export class BookingsResource {
  constructor(private client: MakiaClient) {}

  /**
   * Listar reservas
   *
   * @example
   * ```ts
   * const { bookings, meta } = await makia.bookings.list({
   *   status: 'PENDING',
   *   dateFrom: '2024-02-01',
   *   limit: 50,
   * });
   * ```
   */
  async list(params: BookingListParams = {}): Promise<{ bookings: Booking[]; meta: PaginationMeta }> {
    const query: Record<string, string | number | undefined> = {
      page: params.page,
      limit: params.limit,
      status: params.status,
      dateFrom: params.dateFrom,
      dateTo: params.dateTo,
      search: params.search,
      sortBy: params.sortBy,
      sortOrder: params.sortOrder,
    };

    const response = await this.client.request<Booking[]>('GET', '/bookings', { query });

    // El meta viene en la respuesta completa
    return {
      bookings: response,
      meta: {
        page: params.page || 1,
        limit: params.limit || 20,
        total: 0, // Viene del servidor
        totalPages: 0,
        hasMore: false,
      },
    };
  }

  /**
   * Obtener una reserva por ID
   *
   * @example
   * ```ts
   * const booking = await makia.bookings.get('booking_id');
   * ```
   */
  async get(id: string): Promise<Booking> {
    return this.client.get<Booking>(`/bookings/${id}`);
  }

  /**
   * Crear una nueva reserva
   *
   * @example
   * ```ts
   * const booking = await makia.bookings.create({
   *   customerName: 'Juan García',
   *   customerEmail: 'juan@example.com',
   *   date: '2024-02-15',
   *   time: '20:30',
   *   guests: 4,
   * });
   * ```
   */
  async create(input: CreateBookingInput): Promise<Booking> {
    const body = {
      customerName: input.customerName,
      customerEmail: input.customerEmail,
      customerPhone: input.customerPhone,
      bookingDate: input.date,
      bookingTime: input.time,
      guests: input.guests,
      occasion: input.occasion,
      specialRequests: input.specialRequests,
      source: input.source || 'api',
    };

    return this.client.post<Booking>('/bookings', body);
  }

  /**
   * Actualizar una reserva
   *
   * @example
   * ```ts
   * const updated = await makia.bookings.update('booking_id', {
   *   guests: 6,
   *   status: 'CONFIRMED',
   * });
   * ```
   */
  async update(id: string, input: UpdateBookingInput): Promise<Booking> {
    const body: Record<string, unknown> = {};

    if (input.customerName !== undefined) body.customerName = input.customerName;
    if (input.customerEmail !== undefined) body.customerEmail = input.customerEmail;
    if (input.customerPhone !== undefined) body.customerPhone = input.customerPhone;
    if (input.date !== undefined) body.bookingDate = input.date;
    if (input.time !== undefined) body.bookingTime = input.time;
    if (input.guests !== undefined) body.guests = input.guests;
    if (input.occasion !== undefined) body.occasion = input.occasion;
    if (input.specialRequests !== undefined) body.specialRequests = input.specialRequests;
    if (input.status !== undefined) body.status = input.status;

    return this.client.patch<Booking>(`/bookings/${id}`, body);
  }

  /**
   * Confirmar una reserva
   *
   * @example
   * ```ts
   * await makia.bookings.confirm('booking_id');
   * ```
   */
  async confirm(id: string): Promise<void> {
    await this.client.post(`/bookings/${id}/confirm`);
  }

  /**
   * Cancelar una reserva
   *
   * @example
   * ```ts
   * await makia.bookings.cancel('booking_id');
   * ```
   */
  async cancel(id: string): Promise<void> {
    await this.client.post(`/bookings/${id}/cancel`);
  }

  /**
   * Cancelar reserva usando el token de edición (público)
   *
   * @example
   * ```ts
   * await makia.bookings.cancelByToken('edit_token_xxx');
   * ```
   */
  async cancelByToken(token: string): Promise<void> {
    await this.client.post('/bookings/cancel-by-token', { token });
  }

  /**
   * Agregar nota a una reserva
   *
   * @example
   * ```ts
   * const note = await makia.bookings.addNote('booking_id', 'Cliente VIP');
   * ```
   */
  async addNote(id: string, content: string): Promise<{ id: string; content: string; createdAt: string }> {
    return this.client.post(`/bookings/${id}/notes`, { content });
  }
}
