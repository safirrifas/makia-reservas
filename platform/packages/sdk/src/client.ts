import type { MakiaClientConfig, ApiResponse, ApiError } from './types';
import { BookingsResource } from './resources/bookings';
import { AvailabilityResource } from './resources/availability';
import { OrganizationsResource } from './resources/organizations';
import { AuthResource } from './resources/auth';

const DEFAULT_BASE_URL = 'https://api.contacpro.app/v1';
const DEFAULT_TIMEOUT = 30000;

export class MakiaClient {
  private config: Required<Omit<MakiaClientConfig, 'apiKey' | 'accessToken' | 'organization'>> &
    Pick<MakiaClientConfig, 'apiKey' | 'accessToken' | 'organization'>;

  // Resources
  public readonly bookings: BookingsResource;
  public readonly availability: AvailabilityResource;
  public readonly organizations: OrganizationsResource;
  public readonly auth: AuthResource;

  constructor(config: MakiaClientConfig = {}) {
    this.config = {
      baseUrl: config.baseUrl || DEFAULT_BASE_URL,
      timeout: config.timeout || DEFAULT_TIMEOUT,
      headers: config.headers || {},
      apiKey: config.apiKey,
      accessToken: config.accessToken,
      organization: config.organization,
    };

    // Initialize resources
    this.bookings = new BookingsResource(this);
    this.availability = new AvailabilityResource(this);
    this.organizations = new OrganizationsResource(this);
    this.auth = new AuthResource(this);
  }

  /**
   * Actualizar token de acceso (útil después de login)
   */
  setAccessToken(token: string): void {
    this.config.accessToken = token;
  }

  /**
   * Establecer organización por defecto
   */
  setOrganization(slug: string): void {
    this.config.organization = slug;
  }

  /**
   * Obtener organización actual
   */
  getOrganization(): string | undefined {
    return this.config.organization;
  }

  /**
   * Realizar petición HTTP a la API
   */
  async request<T>(
    method: string,
    path: string,
    options: {
      body?: unknown;
      query?: Record<string, string | number | boolean | undefined>;
      headers?: Record<string, string>;
    } = {}
  ): Promise<T> {
    const url = new URL(path, this.config.baseUrl);

    // Query params
    if (options.query) {
      for (const [key, value] of Object.entries(options.query)) {
        if (value !== undefined) {
          url.searchParams.set(key, String(value));
        }
      }
    }

    // Headers
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...this.config.headers,
      ...options.headers,
    };

    // Auth
    if (this.config.apiKey) {
      headers['Authorization'] = `Bearer ${this.config.apiKey}`;
    } else if (this.config.accessToken) {
      headers['Authorization'] = `Bearer ${this.config.accessToken}`;
    }

    // Organization header
    if (this.config.organization) {
      headers['X-Organization'] = this.config.organization;
    }

    // Request
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.config.timeout);

    try {
      const response = await fetch(url.toString(), {
        method,
        headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      const data = await response.json() as ApiResponse<T>;

      if (!response.ok || !data.success) {
        throw new MakiaError(
          data.error?.message || `HTTP ${response.status}`,
          data.error?.code || 'UNKNOWN_ERROR',
          response.status,
          data.error?.details
        );
      }

      return data.data as T;
    } catch (error) {
      clearTimeout(timeoutId);

      if (error instanceof MakiaError) {
        throw error;
      }

      if (error instanceof Error) {
        if (error.name === 'AbortError') {
          throw new MakiaError('Request timeout', 'TIMEOUT', 408);
        }
        throw new MakiaError(error.message, 'NETWORK_ERROR', 0);
      }

      throw new MakiaError('Unknown error', 'UNKNOWN_ERROR', 0);
    }
  }

  /**
   * GET request helper
   */
  async get<T>(path: string, query?: Record<string, string | number | boolean | undefined>): Promise<T> {
    return this.request<T>('GET', path, { query });
  }

  /**
   * POST request helper
   */
  async post<T>(path: string, body?: unknown): Promise<T> {
    return this.request<T>('POST', path, { body });
  }

  /**
   * PATCH request helper
   */
  async patch<T>(path: string, body?: unknown): Promise<T> {
    return this.request<T>('PATCH', path, { body });
  }

  /**
   * PUT request helper
   */
  async put<T>(path: string, body?: unknown): Promise<T> {
    return this.request<T>('PUT', path, { body });
  }

  /**
   * DELETE request helper
   */
  async delete<T>(path: string): Promise<T> {
    return this.request<T>('DELETE', path);
  }
}

/**
 * Error personalizado de MakIA
 */
export class MakiaError extends Error {
  constructor(
    message: string,
    public readonly code: string,
    public readonly status: number,
    public readonly details?: unknown
  ) {
    super(message);
    this.name = 'MakiaError';
  }

  toJSON() {
    return {
      name: this.name,
      message: this.message,
      code: this.code,
      status: this.status,
      details: this.details,
    };
  }
}
