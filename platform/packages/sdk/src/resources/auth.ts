import type { MakiaClient } from '../client';
import type { LoginInput, RegisterInput, AuthResponse, ApiKey } from '../types';

export class AuthResource {
  constructor(private client: MakiaClient) {}

  /**
   * Iniciar sesión con email y contraseña
   *
   * @example
   * ```ts
   * const { token, user } = await makia.auth.login({
   *   email: 'admin@example.com',
   *   password: 'secret123',
   * });
   *
   * // El token se guarda automáticamente en el cliente
   * console.log(`Bienvenido, ${user.name}!`);
   * ```
   */
  async login(credentials: LoginInput): Promise<AuthResponse> {
    const response = await this.client.post<AuthResponse>('/auth/login', credentials);

    // Guardar token automáticamente
    this.client.setAccessToken(response.token);

    // Guardar organización si existe
    if (response.user.organization?.slug) {
      this.client.setOrganization(response.user.organization.slug);
    }

    return response;
  }

  /**
   * Registrar nuevo usuario y organización
   *
   * @example
   * ```ts
   * const { token, user } = await makia.auth.register({
   *   email: 'nuevo@example.com',
   *   password: 'secreto123',
   *   name: 'Juan García',
   *   organizationName: 'Mi Restaurante',
   * });
   * ```
   */
  async register(data: RegisterInput): Promise<AuthResponse> {
    const response = await this.client.post<AuthResponse>('/auth/register', data);

    // Guardar token automáticamente
    this.client.setAccessToken(response.token);

    // Guardar organización si existe
    if (response.user.organization?.slug) {
      this.client.setOrganization(response.user.organization.slug);
    }

    return response;
  }

  /**
   * Obtener información del usuario actual
   *
   * @example
   * ```ts
   * const user = await makia.auth.me();
   * console.log(`Conectado como: ${user.email}`);
   * ```
   */
  async me(): Promise<AuthResponse['user']> {
    return this.client.get<AuthResponse['user']>('/auth/me');
  }

  /**
   * Generar una nueva API Key
   *
   * @example
   * ```ts
   * const apiKey = await makia.auth.createApiKey({
   *   name: 'Widget de reservas',
   *   scopes: ['bookings:read', 'bookings:write'],
   * });
   *
   * // IMPORTANTE: Guardar la key, solo se muestra una vez
   * console.log('API Key:', apiKey.key);
   * ```
   */
  async createApiKey(data: {
    name: string;
    scopes?: string[];
    expiresInDays?: number;
  }): Promise<ApiKey> {
    return this.client.post<ApiKey>('/auth/api-keys', data);
  }

  /**
   * Listar API Keys de la organización
   *
   * @example
   * ```ts
   * const apiKeys = await makia.auth.listApiKeys();
   * ```
   */
  async listApiKeys(): Promise<ApiKey[]> {
    return this.client.get<ApiKey[]>('/auth/api-keys');
  }

  /**
   * Revocar una API Key
   *
   * @example
   * ```ts
   * await makia.auth.revokeApiKey('api_key_id');
   * ```
   */
  async revokeApiKey(id: string): Promise<void> {
    await this.client.delete(`/auth/api-keys/${id}`);
  }
}
