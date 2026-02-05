# Sistema de Reservas desde Redes Sociales

## Resumen

Este módulo permite a los clientes hacer reservas a través de redes sociales usando un chatbot conversacional. Actualmente soporta **WhatsApp Business** y **Telegram**, con arquitectura preparada para añadir Messenger e Instagram en el futuro.

## Arquitectura

```
┌─────────────────────────────────────────────────────────┐
│                    Plataformas                          │
│     WhatsApp     │     Telegram     │    (Futuro)       │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                  Webhook Gateway                         │
│  POST /social/whatsapp/webhook/:orgId                   │
│  POST /social/telegram/webhook/:orgId                   │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│              Conversation Engine                         │
│         (Motor de conversación unificado)               │
│                                                          │
│  - Flujo guiado paso a paso                             │
│  - Parseo de lenguaje natural                           │
│  - Estado persistente en BD                             │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                   Booking API                            │
│              Crear reserva estándar                      │
└─────────────────────────────────────────────────────────┘
```

## Archivos del Módulo

```
platform/api/src/modules/social/
├── index.ts                 # Exportaciones y rutas principales
├── conversation-engine.ts   # Motor de conversación (chatbot)
├── whatsapp.ts             # Integración WhatsApp Business API
├── telegram.ts             # Integración Telegram Bot API
└── channels.ts             # Endpoints de configuración
```

## Modelos de Base de Datos

### SocialChannel
Configuración de cada canal por organización.

```prisma
model SocialChannel {
  id             String            @id @default(cuid())
  type           SocialChannelType // WHATSAPP, TELEGRAM, MESSENGER, INSTAGRAM
  isActive       Boolean           @default(false)
  config         Json              // Tokens, IDs específicos de cada plataforma
  welcomeMessage String?           // Mensaje de bienvenida personalizado
  organizationId String
  conversations  Conversation[]
}
```

### Conversation
Estado de cada conversación de reserva.

```prisma
model Conversation {
  id              String             @id @default(cuid())
  externalId      String             // ID del chat en la plataforma
  platform        SocialChannelType
  status          ConversationStatus // ACTIVE, COMPLETED, ABANDONED, CANCELLED, EXPIRED
  step            ConversationStep   // Paso actual del flujo

  // Datos recopilados
  customerName    String?
  customerEmail   String?
  customerPhone   String?
  bookingDate     DateTime?
  bookingTime     String?
  guests          Int?
  occasion        String?
  specialRequests String?

  // Relaciones
  organizationId  String
  socialChannelId String
  bookingId       String?            // Reserva creada al completar
  messages        ConversationMessage[]
}
```

### ConversationMessage
Historial de mensajes de cada conversación.

```prisma
model ConversationMessage {
  id             String           @id @default(cuid())
  direction      MessageDirection // INBOUND, OUTBOUND
  content        String
  messageType    MessageType      // TEXT, IMAGE, BUTTON, QUICK_REPLY, LOCATION
  externalId     String?          // ID del mensaje en la plataforma
  conversationId String
}
```

## Flujo de Conversación

```
┌──────────────┐
│   GREETING   │ ← "Hola, quiero reservar"
└──────┬───────┘
       ▼
┌──────────────┐
│   ASK_DATE   │ ← "Para mañana" / "Viernes" / "15 de febrero"
└──────┬───────┘
       ▼
┌──────────────┐
│   ASK_TIME   │ ← "20:30" / "9 de la noche"
└──────┬───────┘
       ▼
┌──────────────┐
│  ASK_GUESTS  │ ← "4 personas" / "somos 4"
└──────┬───────┘
       ▼
┌──────────────┐
│   ASK_NAME   │ ← "Juan García"
└──────┬───────┘
       ▼
┌──────────────┐
│ ASK_CONTACT  │ ← "juan@email.com" / "+34612345678"
└──────┬───────┘
       ▼
┌──────────────┐
│ ASK_OCCASION │ ← "Cumpleaños" / "Ninguna"
└──────┬───────┘
       ▼
┌──────────────┐
│  ASK_NOTES   │ ← "Alergia al gluten" / "Ninguna"
└──────┬───────┘
       ▼
┌──────────────┐
│   CONFIRM    │ ← "Sí, confirmar" / "No, cancelar"
└──────┬───────┘
       ▼
┌──────────────┐
│  COMPLETED   │ → Reserva creada en BD
└──────────────┘
```

## Parseo de Lenguaje Natural

### Fechas
El motor entiende:
- "hoy", "mañana", "pasado mañana"
- Días de la semana: "viernes", "sábado", "este domingo"
- Fechas específicas: "15 de febrero", "20/03", "3-4-2024"

### Horas
- Formato directo: "20:30", "21.00"
- Lenguaje natural: "9 de la noche", "a las 2 y media"
- Coloquial: "a las 9", "sobre las 8"

### Comensales
- Números: "4", "6"
- Con texto: "4 personas", "somos 3", "para 5"
- Palabras: "dos", "cuatro"

## API Endpoints

### Configuración de Canales

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/v1/social/channels` | Listar canales configurados |
| GET | `/v1/social/channels/:type` | Obtener canal específico |
| POST | `/v1/social/channels/whatsapp` | Configurar WhatsApp |
| POST | `/v1/social/channels/telegram` | Configurar Telegram |
| PATCH | `/v1/social/channels/:type/toggle` | Activar/desactivar canal |
| DELETE | `/v1/social/channels/:type` | Eliminar configuración |

### Estadísticas y Conversaciones

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/v1/social/channels/stats/conversations` | Estadísticas de conversiones |
| GET | `/v1/social/channels/conversations` | Listar conversaciones |
| GET | `/v1/social/channels/conversations/:id` | Detalle de conversación |

### Webhooks (Públicos)

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/social/whatsapp/webhook/:orgId` | Verificación de WhatsApp |
| POST | `/social/whatsapp/webhook/:orgId` | Recibir mensajes de WhatsApp |
| POST | `/social/telegram/webhook/:orgId` | Recibir mensajes de Telegram |

## Configuración de Plataformas

### WhatsApp Business API

**Requisitos:**
- Cuenta de Meta Business Suite verificada
- Aplicación en Meta for Developers
- Número de teléfono dedicado para WhatsApp Business

**Configuración:**
```json
POST /v1/social/channels/whatsapp
{
  "phoneNumberId": "1234567890",
  "accessToken": "EAAxxxxx...",
  "verifyToken": "mi_token_secreto_123",
  "businessAccountId": "opcional",
  "welcomeMessage": "¡Bienvenido a nuestro restaurante!"
}
```

**En Meta for Developers:**
1. Crear App tipo Business
2. Agregar producto WhatsApp
3. Configurar webhook:
   - URL: `https://api.tudominio.com/social/whatsapp/webhook/{orgId}`
   - Token: el `verifyToken` configurado
   - Suscribir a: `messages`

**Costos:** ~$0.05-0.15 por conversación según país

### Telegram Bot

**Requisitos:**
- Solo necesitas hablar con @BotFather

**Pasos:**
1. Abrir Telegram y buscar @BotFather
2. Enviar `/newbot`
3. Elegir nombre y username
4. Copiar el token

**Configuración:**
```json
POST /v1/social/channels/telegram
{
  "botToken": "123456789:ABCdefGHIjklMNOpqrsTUVwxyz",
  "welcomeMessage": "¡Hola! Soy el bot de reservas."
}
```

La API configura automáticamente:
- Webhook de Telegram
- Comandos del bot (`/reservar`, `/ayuda`, `/cancelar`)

**Costos:** Gratis

## Variables de Entorno

```env
# URLs base
API_URL=https://api.contacpro.app
APP_URL=https://contacpro.app

# Base de datos
DATABASE_URL=postgresql://user:pass@localhost:5432/makia

# Redis (para colas)
REDIS_HOST=localhost
REDIS_PORT=6379
```

## Funciones Exportadas

```typescript
// Enviar notificación por WhatsApp
import { sendWhatsAppNotification } from './modules/social';
await sendWhatsAppNotification(orgId, '+34612345678', 'Tu reserva está confirmada');

// Enviar notificación por Telegram
import { sendTelegramNotification } from './modules/social';
await sendTelegramNotification(orgId, 'chatId123', 'Tu reserva está confirmada');

// Limpiar conversaciones expiradas (para cron)
import { cleanupExpiredConversations } from './modules/social';
const cleaned = await cleanupExpiredConversations(); // Expira después de 24h inactividad
```

## Ejemplo de Conversación

```
👤 Usuario: Hola
🤖 Bot: ¡Hola! 👋 Soy el asistente de reservas de *Restaurante La Plaza*.
        ¿Te gustaría hacer una reserva?
        [Sí, quiero reservar] [Ver disponibilidad]

👤 Usuario: Sí, quiero reservar
🤖 Bot: ¿Para qué día te gustaría reservar?
        [Hoy] [Mañana] [Este viernes] [Este sábado]

👤 Usuario: Para mañana
🤖 Bot: Perfecto, *viernes 7 de febrero* ✓
        ¿A qué hora te gustaría venir?
        [13:00] [14:00] [20:30] [21:00] [21:30]

👤 Usuario: A las 9 de la noche
🤖 Bot: *21:00* ✓
        ¿Para cuántas personas?
        [2 personas] [3 personas] [4 personas] [5 personas]

👤 Usuario: 4
🤖 Bot: *4 personas* ✓
        ¿A nombre de quién hago la reserva?

👤 Usuario: Juan García
🤖 Bot: Gracias *Juan* ✓
        ¿Me das tu email para enviarte la confirmación?

👤 Usuario: juan@email.com
🤖 Bot: ¿Es alguna ocasión especial?
        [Cumpleaños] [Aniversario] [Negocios] [Ninguna]

👤 Usuario: Cumpleaños
🤖 Bot: ¿Tienes alguna petición especial?
        [Ninguna] [Alergia gluten] [Silla de bebé] [Mesa exterior]

👤 Usuario: Mesa exterior si es posible
🤖 Bot: 📋 *Resumen de tu reserva:*

        📅 *Fecha:* viernes 7 de febrero
        🕐 *Hora:* 21:00
        👥 *Comensales:* 4
        👤 *Nombre:* Juan García
        🎉 *Ocasión:* Cumpleaños
        📝 *Notas:* Mesa exterior si es posible

        📍 *Restaurante La Plaza*
        Calle Mayor 123, Madrid

        ¿Confirmamos la reserva?
        [Sí, confirmar] [No, cancelar]

👤 Usuario: Sí
🤖 Bot: ✅ *¡Reserva confirmada!*

        Te hemos enviado un email con los detalles.

        Si necesitas modificar o cancelar tu reserva:
        https://contacpro.app/reserva/abc123xyz

        ¡Te esperamos! 🍽️
```

## Mantenimiento

### Limpieza de Conversaciones Expiradas

Añadir al cron job (cada hora):

```typescript
import { cleanupExpiredConversations } from './modules/social';

// En el cron handler
const expired = await cleanupExpiredConversations();
console.log(`Cleaned ${expired} expired conversations`);
```

### Monitoreo

Revisar periódicamente:
- Tasa de conversión (conversaciones completadas / totales)
- Conversaciones abandonadas (identificar puntos de fricción)
- Errores en webhooks

## Extensibilidad

### Añadir Nuevo Canal (ej: Messenger)

1. Crear archivo `messenger.ts` con webhook handler
2. Añadir tipo a `SocialChannelType` en schema
3. Implementar funciones de envío de mensajes
4. Registrar rutas en `index.ts`
5. Añadir endpoint de configuración en `channels.ts`

El motor de conversación (`conversation-engine.ts`) es agnóstico de la plataforma y se reutiliza.

## Commits Relacionados

- `a15639a` - feat: Add social media booking system (WhatsApp, Telegram)
- `d0b0184` - feat: Add notification queue, logs, scheduler and ICS generator
- `326530d` - feat: Add customizable notification templates
