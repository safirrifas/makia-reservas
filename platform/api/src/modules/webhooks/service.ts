/**
 * MakIA Restaurante - Webhook Service
 *
 * Servicio para enviar webhooks a instalaciones de WordPress
 */

import { createHmac } from 'crypto';

// Tipos de eventos de webhook
export type WebhookEvent =
  | 'license.updated'
  | 'license.expired'
  | 'license.renewed'
  | 'plan.upgraded'
  | 'plan.downgraded'
  | 'payment.succeeded'
  | 'payment.failed'
  | 'notification.new'
  | 'plugin.update_available'
  | 'system.maintenance';

interface WebhookPayload {
  event: WebhookEvent;
  data: Record<string, unknown>;
  timestamp: number;
}

interface WebhookEndpoint {
  id: string;
  organization_id: string;
  url: string;
  secret: string;
  events: WebhookEvent[];
  active: boolean;
  created_at: Date;
}

interface WebhookDelivery {
  id: string;
  webhook_id: string;
  event: WebhookEvent;
  payload: WebhookPayload;
  response_status: number | null;
  response_body: string | null;
  delivered_at: Date | null;
  attempts: number;
  last_error: string | null;
}

/**
 * Servicio de webhooks
 */
export class WebhookService {
  private maxRetries = 3;
  private retryDelays = [1000, 5000, 30000]; // 1s, 5s, 30s

  /**
   * Registrar un nuevo endpoint de webhook
   */
  async registerEndpoint(
    organizationId: string,
    url: string,
    events: WebhookEvent[] = ['license.updated', 'notification.new']
  ): Promise<WebhookEndpoint> {
    // Generar secret para firmar webhooks
    const secret = this.generateSecret();

    // TODO: Guardar en base de datos
    const endpoint: WebhookEndpoint = {
      id: `wh_${Date.now()}`,
      organization_id: organizationId,
      url,
      secret,
      events,
      active: true,
      created_at: new Date(),
    };

    // Enviar webhook de prueba
    await this.sendWebhook(endpoint, 'license.updated', {
      test: true,
      message: 'Webhook endpoint configured successfully',
    });

    return endpoint;
  }

  /**
   * Enviar webhook a un endpoint específico
   */
  async sendWebhook(
    endpoint: WebhookEndpoint,
    event: WebhookEvent,
    data: Record<string, unknown>
  ): Promise<WebhookDelivery> {
    const timestamp = Math.floor(Date.now() / 1000);
    const payload: WebhookPayload = {
      event,
      data,
      timestamp,
    };

    const payloadString = JSON.stringify(payload);
    const signature = this.signPayload(timestamp, payloadString, endpoint.secret);

    const delivery: WebhookDelivery = {
      id: `whd_${Date.now()}`,
      webhook_id: endpoint.id,
      event,
      payload,
      response_status: null,
      response_body: null,
      delivered_at: null,
      attempts: 0,
      last_error: null,
    };

    // Intentar entregar el webhook con reintentos
    for (let attempt = 0; attempt < this.maxRetries; attempt++) {
      delivery.attempts = attempt + 1;

      try {
        const response = await fetch(endpoint.url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-MakIA-Signature': signature,
            'X-MakIA-Timestamp': timestamp.toString(),
            'X-MakIA-Event': event,
            'User-Agent': 'MakIA-Webhook/1.0',
          },
          body: payloadString,
        });

        delivery.response_status = response.status;
        delivery.response_body = await response.text();
        delivery.delivered_at = new Date();

        if (response.ok) {
          // Éxito
          break;
        } else {
          delivery.last_error = `HTTP ${response.status}: ${delivery.response_body}`;
        }
      } catch (error) {
        delivery.last_error = error instanceof Error ? error.message : 'Unknown error';
      }

      // Esperar antes del siguiente reintento
      if (attempt < this.maxRetries - 1) {
        await this.sleep(this.retryDelays[attempt]);
      }
    }

    // TODO: Guardar delivery en base de datos para auditoría

    return delivery;
  }

  /**
   * Enviar webhook a todas las instalaciones de una organización
   */
  async broadcastToOrganization(
    organizationId: string,
    event: WebhookEvent,
    data: Record<string, unknown>
  ): Promise<WebhookDelivery[]> {
    // TODO: Obtener todos los endpoints activos de la organización
    const endpoints = await this.getEndpointsByOrganization(organizationId);

    const deliveries: WebhookDelivery[] = [];

    for (const endpoint of endpoints) {
      if (endpoint.active && endpoint.events.includes(event)) {
        const delivery = await this.sendWebhook(endpoint, event, data);
        deliveries.push(delivery);
      }
    }

    return deliveries;
  }

  /**
   * Enviar webhook de actualización de licencia
   */
  async sendLicenseUpdate(
    organizationId: string,
    licenseData: Record<string, unknown>
  ): Promise<void> {
    await this.broadcastToOrganization(organizationId, 'license.updated', licenseData);
  }

  /**
   * Enviar webhook de expiración de licencia
   */
  async sendLicenseExpired(
    organizationId: string,
    licenseData: Record<string, unknown>
  ): Promise<void> {
    await this.broadcastToOrganization(organizationId, 'license.expired', licenseData);
  }

  /**
   * Enviar webhook de renovación de licencia
   */
  async sendLicenseRenewed(
    organizationId: string,
    licenseData: Record<string, unknown>
  ): Promise<void> {
    await this.broadcastToOrganization(organizationId, 'license.renewed', licenseData);
  }

  /**
   * Enviar webhook de cambio de plan
   */
  async sendPlanChanged(
    organizationId: string,
    direction: 'upgraded' | 'downgraded',
    planData: Record<string, unknown>
  ): Promise<void> {
    const event: WebhookEvent = direction === 'upgraded' ? 'plan.upgraded' : 'plan.downgraded';
    await this.broadcastToOrganization(organizationId, event, planData);
  }

  /**
   * Enviar webhook de evento de pago
   */
  async sendPaymentEvent(
    organizationId: string,
    status: 'succeeded' | 'failed',
    paymentData: Record<string, unknown>
  ): Promise<void> {
    const event: WebhookEvent = status === 'succeeded' ? 'payment.succeeded' : 'payment.failed';
    await this.broadcastToOrganization(organizationId, event, paymentData);
  }

  /**
   * Enviar webhook de nueva notificación
   */
  async sendNewNotification(
    organizationId: string,
    notificationData: Record<string, unknown>
  ): Promise<void> {
    await this.broadcastToOrganization(organizationId, 'notification.new', notificationData);
  }

  /**
   * Enviar webhook de actualización disponible del plugin
   */
  async sendPluginUpdateAvailable(updateInfo: Record<string, unknown>): Promise<void> {
    // TODO: Obtener todos los endpoints activos
    const allEndpoints = await this.getAllActiveEndpoints();

    for (const endpoint of allEndpoints) {
      if (endpoint.events.includes('plugin.update_available')) {
        await this.sendWebhook(endpoint, 'plugin.update_available', updateInfo);
      }
    }
  }

  /**
   * Enviar webhook de mantenimiento programado
   */
  async sendMaintenanceNotification(maintenanceData: Record<string, unknown>): Promise<void> {
    const allEndpoints = await this.getAllActiveEndpoints();

    for (const endpoint of allEndpoints) {
      if (endpoint.events.includes('system.maintenance')) {
        await this.sendWebhook(endpoint, 'system.maintenance', maintenanceData);
      }
    }
  }

  /**
   * Firmar payload con HMAC SHA256
   */
  private signPayload(timestamp: number, payload: string, secret: string): string {
    const signatureData = `${timestamp}.${payload}`;
    return createHmac('sha256', secret).update(signatureData).digest('hex');
  }

  /**
   * Generar secret aleatorio
   */
  private generateSecret(): string {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let secret = '';
    for (let i = 0; i < 32; i++) {
      secret += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return secret;
  }

  /**
   * Sleep helper
   */
  private sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  /**
   * Obtener endpoints de una organización
   * TODO: Implementar con base de datos
   */
  private async getEndpointsByOrganization(organizationId: string): Promise<WebhookEndpoint[]> {
    // Placeholder - implementar con Prisma
    return [];
  }

  /**
   * Obtener todos los endpoints activos
   * TODO: Implementar con base de datos
   */
  private async getAllActiveEndpoints(): Promise<WebhookEndpoint[]> {
    // Placeholder - implementar con Prisma
    return [];
  }
}

// Singleton instance
export const webhookService = new WebhookService();
