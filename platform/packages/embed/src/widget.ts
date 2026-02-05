/**
 * MakIA Booking Widget
 * Widget embebible para formularios de reserva
 *
 * Uso:
 * <div id="makia-booking"></div>
 * <script src="https://cdn.contacpro.app/widget.js" data-restaurant="mi-restaurante"></script>
 */

interface WidgetConfig {
  restaurant: string;
  apiUrl?: string;
  theme?: 'light' | 'dark' | 'auto';
  primaryColor?: string;
  locale?: string;
  onSuccess?: (booking: unknown) => void;
  onError?: (error: unknown) => void;
}

interface TimeSlot {
  time: string;
  available: boolean;
}

class MakiaBookingWidget {
  private config: Required<WidgetConfig>;
  private container: HTMLElement | null = null;
  private shadow: ShadowRoot | null = null;

  constructor(config: WidgetConfig) {
    this.config = {
      restaurant: config.restaurant,
      apiUrl: config.apiUrl || 'https://api.contacpro.app/v1',
      theme: config.theme || 'light',
      primaryColor: config.primaryColor || '#4f46e5',
      locale: config.locale || 'es',
      onSuccess: config.onSuccess || (() => {}),
      onError: config.onError || (() => {}),
    };
  }

  mount(selector: string | HTMLElement): void {
    this.container = typeof selector === 'string'
      ? document.querySelector(selector)
      : selector;

    if (!this.container) {
      console.error('[MakIA Widget] Container not found');
      return;
    }

    // Crear Shadow DOM para aislamiento de CSS
    this.shadow = this.container.attachShadow({ mode: 'open' });
    this.render();
  }

  private render(): void {
    if (!this.shadow) return;

    this.shadow.innerHTML = `
      <style>${this.getStyles()}</style>
      <div class="makia-widget" data-theme="${this.config.theme}">
        <div class="makia-header">
          <h3>Reservar Mesa</h3>
        </div>
        <form id="makia-form" class="makia-form">
          <div class="makia-row">
            <div class="makia-field">
              <label for="makia-date">Fecha</label>
              <input type="date" id="makia-date" required min="${this.getTodayDate()}">
            </div>
            <div class="makia-field">
              <label for="makia-guests">Comensales</label>
              <select id="makia-guests" required>
                ${Array.from({ length: 10 }, (_, i) => `<option value="${i + 1}">${i + 1} ${i === 0 ? 'persona' : 'personas'}</option>`).join('')}
              </select>
            </div>
          </div>

          <div class="makia-field">
            <label for="makia-time">Hora</label>
            <div id="makia-time-slots" class="makia-time-slots">
              <p class="makia-hint">Selecciona una fecha para ver horarios disponibles</p>
            </div>
            <input type="hidden" id="makia-time" required>
          </div>

          <div class="makia-field">
            <label for="makia-name">Nombre</label>
            <input type="text" id="makia-name" required minlength="2" placeholder="Tu nombre">
          </div>

          <div class="makia-row">
            <div class="makia-field">
              <label for="makia-email">Email</label>
              <input type="email" id="makia-email" required placeholder="tu@email.com">
            </div>
            <div class="makia-field">
              <label for="makia-phone">Teléfono</label>
              <input type="tel" id="makia-phone" placeholder="+34 600 000 000">
            </div>
          </div>

          <div class="makia-field">
            <label for="makia-notes">Notas (opcional)</label>
            <textarea id="makia-notes" rows="2" placeholder="Alergias, celebraciones..."></textarea>
          </div>

          <div id="makia-error" class="makia-error" style="display: none;"></div>
          <div id="makia-success" class="makia-success" style="display: none;"></div>

          <button type="submit" id="makia-submit" class="makia-button">
            Confirmar Reserva
          </button>
        </form>
      </div>
    `;

    this.attachEventListeners();
  }

  private attachEventListeners(): void {
    if (!this.shadow) return;

    const form = this.shadow.getElementById('makia-form') as HTMLFormElement;
    const dateInput = this.shadow.getElementById('makia-date') as HTMLInputElement;
    const guestsSelect = this.shadow.getElementById('makia-guests') as HTMLSelectElement;

    // Cargar horarios cuando cambia fecha o comensales
    dateInput?.addEventListener('change', () => this.loadTimeSlots());
    guestsSelect?.addEventListener('change', () => this.loadTimeSlots());

    // Submit
    form?.addEventListener('submit', (e) => this.handleSubmit(e));
  }

  private async loadTimeSlots(): Promise<void> {
    if (!this.shadow) return;

    const dateInput = this.shadow.getElementById('makia-date') as HTMLInputElement;
    const guestsSelect = this.shadow.getElementById('makia-guests') as HTMLSelectElement;
    const slotsContainer = this.shadow.getElementById('makia-time-slots');
    const timeInput = this.shadow.getElementById('makia-time') as HTMLInputElement;

    if (!dateInput.value || !slotsContainer) return;

    slotsContainer.innerHTML = '<p class="makia-loading">Cargando horarios...</p>';
    timeInput.value = '';

    try {
      const response = await fetch(
        `${this.config.apiUrl}/availability?org=${this.config.restaurant}&date=${dateInput.value}&guests=${guestsSelect.value}`
      );
      const data = await response.json();

      if (!data.success || !data.data.isOpen) {
        slotsContainer.innerHTML = `<p class="makia-hint">${data.data?.reason || 'Cerrado este día'}</p>`;
        return;
      }

      const slots: TimeSlot[] = data.data.slots;
      const availableSlots = slots.filter(s => s.available);

      if (availableSlots.length === 0) {
        slotsContainer.innerHTML = '<p class="makia-hint">No hay horarios disponibles</p>';
        return;
      }

      slotsContainer.innerHTML = availableSlots.map(slot => `
        <button type="button" class="makia-time-slot" data-time="${slot.time}">
          ${slot.time}
        </button>
      `).join('');

      // Agregar listeners a los slots
      slotsContainer.querySelectorAll('.makia-time-slot').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const target = e.target as HTMLButtonElement;
          slotsContainer.querySelectorAll('.makia-time-slot').forEach(b => b.classList.remove('selected'));
          target.classList.add('selected');
          timeInput.value = target.dataset.time || '';
        });
      });
    } catch (error) {
      slotsContainer.innerHTML = '<p class="makia-error-text">Error al cargar horarios</p>';
    }
  }

  private async handleSubmit(e: Event): Promise<void> {
    e.preventDefault();
    if (!this.shadow) return;

    const submitBtn = this.shadow.getElementById('makia-submit') as HTMLButtonElement;
    const errorDiv = this.shadow.getElementById('makia-error');
    const successDiv = this.shadow.getElementById('makia-success');

    // Ocultar mensajes previos
    if (errorDiv) errorDiv.style.display = 'none';
    if (successDiv) successDiv.style.display = 'none';

    // Obtener valores
    const formData = {
      customerName: (this.shadow.getElementById('makia-name') as HTMLInputElement).value,
      customerEmail: (this.shadow.getElementById('makia-email') as HTMLInputElement).value,
      customerPhone: (this.shadow.getElementById('makia-phone') as HTMLInputElement).value,
      bookingDate: (this.shadow.getElementById('makia-date') as HTMLInputElement).value,
      bookingTime: (this.shadow.getElementById('makia-time') as HTMLInputElement).value,
      guests: parseInt((this.shadow.getElementById('makia-guests') as HTMLSelectElement).value),
      specialRequests: (this.shadow.getElementById('makia-notes') as HTMLTextAreaElement).value,
      source: 'widget',
    };

    if (!formData.bookingTime) {
      if (errorDiv) {
        errorDiv.textContent = 'Por favor, selecciona una hora';
        errorDiv.style.display = 'block';
      }
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Procesando...';

    try {
      const response = await fetch(`${this.config.apiUrl}/bookings?org=${this.config.restaurant}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error?.message || 'Error al crear reserva');
      }

      // Éxito
      if (successDiv) {
        successDiv.innerHTML = `
          <strong>¡Reserva recibida!</strong><br>
          Te enviaremos un email de confirmación a ${formData.customerEmail}
        `;
        successDiv.style.display = 'block';
      }

      // Reset form
      (this.shadow.getElementById('makia-form') as HTMLFormElement).reset();
      this.shadow.getElementById('makia-time-slots')!.innerHTML = '<p class="makia-hint">Selecciona una fecha para ver horarios disponibles</p>';

      this.config.onSuccess(data.data);
    } catch (error) {
      if (errorDiv) {
        errorDiv.textContent = error instanceof Error ? error.message : 'Error desconocido';
        errorDiv.style.display = 'block';
      }
      this.config.onError(error);
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Confirmar Reserva';
    }
  }

  private getTodayDate(): string {
    return new Date().toISOString().split('T')[0];
  }

  private getStyles(): string {
    const primary = this.config.primaryColor;

    return `
      :host {
        display: block;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      }

      .makia-widget {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        padding: 24px;
        max-width: 480px;
        margin: 0 auto;
      }

      .makia-widget[data-theme="dark"] {
        background: #1f2937;
        color: #f9fafb;
      }

      .makia-header {
        text-align: center;
        margin-bottom: 24px;
      }

      .makia-header h3 {
        margin: 0;
        font-size: 1.5rem;
        color: ${primary};
      }

      .makia-form {
        display: flex;
        flex-direction: column;
        gap: 16px;
      }

      .makia-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
      }

      @media (max-width: 480px) {
        .makia-row {
          grid-template-columns: 1fr;
        }
      }

      .makia-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
      }

      .makia-field label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
      }

      .makia-widget[data-theme="dark"] .makia-field label {
        color: #d1d5db;
      }

      .makia-field input,
      .makia-field select,
      .makia-field textarea {
        padding: 10px 12px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.2s;
      }

      .makia-widget[data-theme="dark"] .makia-field input,
      .makia-widget[data-theme="dark"] .makia-field select,
      .makia-widget[data-theme="dark"] .makia-field textarea {
        background: #374151;
        border-color: #4b5563;
        color: #f9fafb;
      }

      .makia-field input:focus,
      .makia-field select:focus,
      .makia-field textarea:focus {
        outline: none;
        border-color: ${primary};
      }

      .makia-time-slots {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }

      .makia-time-slot {
        padding: 8px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        cursor: pointer;
        font-size: 0.875rem;
        transition: all 0.2s;
      }

      .makia-widget[data-theme="dark"] .makia-time-slot {
        background: #374151;
        border-color: #4b5563;
        color: #f9fafb;
      }

      .makia-time-slot:hover {
        border-color: ${primary};
      }

      .makia-time-slot.selected {
        background: ${primary};
        border-color: ${primary};
        color: #fff;
      }

      .makia-hint {
        color: #6b7280;
        font-size: 0.875rem;
        margin: 0;
      }

      .makia-loading {
        color: ${primary};
        font-size: 0.875rem;
      }

      .makia-button {
        padding: 14px 24px;
        background: ${primary};
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.2s;
      }

      .makia-button:hover {
        opacity: 0.9;
      }

      .makia-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }

      .makia-error {
        background: #fef2f2;
        color: #dc2626;
        padding: 12px;
        border-radius: 8px;
        font-size: 0.875rem;
      }

      .makia-success {
        background: #f0fdf4;
        color: #16a34a;
        padding: 12px;
        border-radius: 8px;
        font-size: 0.875rem;
      }

      .makia-error-text {
        color: #dc2626;
        font-size: 0.875rem;
        margin: 0;
      }
    `;
  }
}

// Auto-inicialización desde atributos data-*
(function autoInit() {
  const script = document.currentScript as HTMLScriptElement;
  if (!script) return;

  const restaurant = script.dataset.restaurant;
  if (!restaurant) {
    console.error('[MakIA Widget] data-restaurant attribute required');
    return;
  }

  // Buscar contenedor
  const containerId = script.dataset.container || 'makia-booking';
  const container = document.getElementById(containerId);

  if (!container) {
    console.error(`[MakIA Widget] Container #${containerId} not found`);
    return;
  }

  // Crear widget
  const widget = new MakiaBookingWidget({
    restaurant,
    apiUrl: script.dataset.apiUrl,
    theme: script.dataset.theme as 'light' | 'dark' | 'auto',
    primaryColor: script.dataset.primaryColor,
    locale: script.dataset.locale,
  });

  widget.mount(container);
})();

// Exportar para uso programático
if (typeof window !== 'undefined') {
  (window as unknown as { MakiaBookingWidget: typeof MakiaBookingWidget }).MakiaBookingWidget = MakiaBookingWidget;
}

export { MakiaBookingWidget };
