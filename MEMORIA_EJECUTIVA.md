# MakIA Restaurante - Memoria Ejecutiva

**Sistema Integral de Reservas para Restaurantes**

---

## Resumen del Proyecto

| Atributo | Valor |
|----------|-------|
| **Nombre** | MakIA Restaurante |
| **Versión Actual** | 4.4.1 |
| **Licencia** | GPL v2 or later |
| **Última Actualización** | 2026-02-07 |
| **Repositorio** | github.com/safirrifas/makia-reservas |

### Propósito

MakIA Restaurante es un sistema completo de gestión de reservas diseñado para restaurantes. Combina un plugin de WordPress con una plataforma API moderna, permitiendo:

- Gestión centralizada de reservas
- Comunicación multicanal (email, SMS, WhatsApp, push)
- Reservas via chatbots (WhatsApp, Telegram)
- Panel de administración para operarios
- Sistema de licencias y facturación

---

## Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENTES                                  │
├─────────────┬─────────────┬─────────────┬─────────────┬─────────┤
│   Widget    │  WordPress  │   WhatsApp  │  Telegram   │   PWA   │
│   Embebido  │   Plugin    │   Chatbot   │    Bot      │ Móvil   │
└──────┬──────┴──────┬──────┴──────┬──────┴──────┬──────┴────┬────┘
       │             │             │             │           │
       └─────────────┴──────┬──────┴─────────────┴───────────┘
                            │
                   ┌────────▼────────┐
                   │    API REST     │
                   │   (Hono.js)     │
                   │  contacpro.app  │
                   └────────┬────────┘
                            │
       ┌────────────────────┼────────────────────┐
       │                    │                    │
┌──────▼──────┐     ┌───────▼───────┐    ┌──────▼──────┐
│ PostgreSQL  │     │     Redis     │    │   Twilio    │
│  Database   │     │  Cache/Queue  │    │  WhatsApp   │
└─────────────┘     └───────────────┘    └─────────────┘
```

---

## Estructura del Repositorio

```
makia-reservas/
│
├── makia-reservas.php              # Plugin principal WordPress
├── includes/                       # Clases PHP del plugin
│   ├── class-makia-bookings.php    # Gestión de reservas (2,468 líneas)
│   ├── class-makia-admin.php       # Panel de administración
│   ├── class-makia-api.php         # API REST WordPress
│   ├── class-makia-operators.php   # Gestión de operarios
│   ├── class-makia-notes.php       # Sistema de notas
│   ├── class-makia-audit.php       # Auditoría
│   ├── class-makia-sms.php         # Integración Twilio
│   ├── class-makia-whatsapp.php    # WhatsApp Business API
│   ├── class-license-manager.php   # Sistema de licencias
│   └── [+10 clases más]
│
├── platform/                       # Plataforma moderna
│   ├── api/                        # Backend API (TypeScript)
│   │   ├── src/modules/
│   │   │   ├── auth/               # Autenticación
│   │   │   ├── bookings/           # Reservas
│   │   │   ├── notifications/      # Notificaciones
│   │   │   ├── social/             # Chatbots
│   │   │   ├── license/            # Licencias
│   │   │   └── webhooks/           # Webhooks
│   │   └── prisma/                 # Esquema BD
│   │
│   ├── apps/
│   │   ├── operator-pwa/           # PWA para operarios
│   │   └── dashboard/              # Dashboard admin
│   │
│   └── packages/
│       ├── sdk/                    # SDK TypeScript
│       └── embed/                  # Widget embebible
│
├── assets/                         # CSS, JS, imágenes
├── deployment/                     # Guías de despliegue
└── docs/                          # Documentación
```

---

## Stack Tecnológico

### Plugin WordPress
| Componente | Tecnología |
|------------|------------|
| Lenguaje | PHP 7.4+ |
| Framework | WordPress 5.0+ |
| Base de datos | MySQL/MariaDB |
| Frontend | JavaScript + jQuery |
| Estilos | CSS3 responsive |

### Plataforma API
| Componente | Tecnología |
|------------|------------|
| Runtime | Node.js 20+ |
| Framework | Hono.js 4.0 |
| Lenguaje | TypeScript |
| ORM | Prisma 5.9 |
| Base de datos | PostgreSQL 16 |
| Cache/Colas | Redis 7 + BullMQ |
| Autenticación | JWT (Jose) |
| Validación | Zod |

### Servicios Externos
| Servicio | Proveedor |
|----------|-----------|
| SMS | Twilio |
| WhatsApp | Meta Business API |
| Pagos | Stripe (framework) |
| Email | SMTP / SendGrid |

---

## Funcionalidades Principales

### Gestión de Reservas
- Formulario responsive con 6 plantillas profesionales
- Verificación de disponibilidad en tiempo real
- Control de capacidad (total y por reserva)
- Horarios especiales y días festivos
- Estados: Pendiente, Confirmada, Cancelada, Completada, No-Show
- Token de edición para autogestión del cliente
- Lista negra de clientes problemáticos

### Comunicaciones
- Email (confirmación, recordatorio, cancelación)
- SMS via Twilio
- WhatsApp Business API
- Push notifications (VAPID)
- Plantillas personalizables con variables
- Chatbots conversacionales (WhatsApp, Telegram)

### Panel de Administración
- Dashboard con estadísticas
- Gestión de reservas con filtros
- Sistema de notas internas (6 tipos)
- Gestión de operarios con roles
- Auditoría completa de acciones
- Configuración de horarios
- Personalización de diseño
- Gestión de licencias

### Seguridad
- Rate limiting (IP + email)
- Validación de entrada (Zod)
- Protección XSS y SQL injection
- Autenticación JWT
- Control de acceso por capabilities
- Detección de emails desechables

---

## Sistema de Licencias

### Planes Disponibles

| Plan | Reservas/Mes | Precio | Características |
|------|-------------|--------|-----------------|
| **Chupito** | 5 | Gratis | Básico |
| **Caña** | 15 | 5€ | SMS + Recordatorios |
| **MasCaña** | 100 | 15€ | WhatsApp + Analytics |

### Gestión
- Activación por clave de licencia
- Verificación diaria automática
- Reset mensual de contador
- Asociación por dominio

---

## Endpoints API Principales

### Reservas
```
GET    /v1/bookings              Lista reservas
POST   /v1/bookings              Crear reserva
GET    /v1/bookings/:id          Detalle reserva
PATCH  /v1/bookings/:id          Actualizar
POST   /v1/bookings/:id/confirm  Confirmar
POST   /v1/bookings/:id/cancel   Cancelar
```

### Disponibilidad
```
GET    /v1/availability          Slots disponibles
POST   /v1/availability/check    Verificar hora
```

### Licencias
```
GET    /v1/license               Estado licencia
POST   /v1/license/activate      Activar
```

### Webhooks Sociales
```
POST   /social/whatsapp          Webhook WhatsApp
POST   /social/telegram          Webhook Telegram
```

---

## Configuración Requerida

### wp-config.php (WordPress)
```php
// JWT (Recomendado)
define('JWT_AUTH_SECRET_KEY', 'clave-secreta');

// Push Notifications
define('MAKIA_VAPID_PUBLIC_KEY', 'BEl62i...');
define('MAKIA_VAPID_PRIVATE_KEY', 'UUxI4o...');

// SMS (Opcional)
define('TWILIO_ACCOUNT_SID', 'ACxxxxxx');
define('TWILIO_AUTH_TOKEN', 'xxxxxxxx');
define('TWILIO_PHONE_NUMBER', '+34600000000');

// WhatsApp (Opcional)
define('WHATSAPP_API_TOKEN', 'EAAxxxxx');
define('WHATSAPP_PHONE_NUMBER_ID', '123456789');
```

### Variables de Entorno (API)
```bash
DATABASE_URL=postgresql://user:pass@host:5432/db
REDIS_URL=redis://localhost:6379
JWT_SECRET=clave-super-secreta
NODE_ENV=production
PORT=3000
```

---

## Despliegue

### Opción 1: WordPress Plugin (Simple)
1. Descargar ZIP del plugin
2. Subir via WordPress Admin > Plugins > Añadir nuevo
3. Activar plugin
4. Configurar en MakIA Restaurante > Ajustes
5. Añadir shortcode: `[makia_reservas]`

### Opción 2: Docker Compose (Completo)
```bash
cd platform
cp .env.example .env
docker-compose up -d
```

### Opción 3: Servidor Tradicional
```bash
# Instalar dependencias
pnpm install

# Migrar base de datos
pnpm db:migrate

# Iniciar con PM2
pm2 start ecosystem.config.js
```

---

## URLs de Producción

| Servicio | URL |
|----------|-----|
| Web Principal | https://contacpro.app |
| API | https://api.contacpro.app |
| Dashboard | https://dashboard.contacpro.app |
| Widget | https://contacpro.app/widget.js |
| Operarios PWA | https://contacpro.app/operarios |

---

## Historial de Versiones Recientes

| Versión | Fecha | Cambios Principales |
|---------|-------|---------------------|
| 4.4.1 | 2026-02-06 | Filtros JavaScript funcionando |
| 4.4.0 | 2026-02-05 | UX admin mejorada, emails HTML |
| 4.3.0 | 2026-02-04 | Plugin completo empaquetado |
| 4.2.0 | 2026-01-16 | Notas, operarios, auditoría |
| 4.1.0 | 2026-01-16 | Sistema de diseño avanzado |
| 3.4.0 | 2026-01-08 | Panel admin WordPress |

---

## Contacto y Soporte

- **Web:** https://contacpro.app
- **Email:** soporte@contacpro.app
- **Repositorio:** github.com/safirrifas/makia-reservas

---

*Documento generado: 2026-02-07*
*MakIA Restaurante v4.4.1*
