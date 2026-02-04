# MakIA Platform

Plataforma multiplataforma de reservas para restaurantes.

## Estructura

```
platform/
├── api/                  # Backend API (Hono + TypeScript)
├── packages/
│   ├── sdk/              # SDK TypeScript (@makia/sdk)
│   ├── embed/            # Widget embebible
│   └── ui/               # Componentes React (futuro)
├── integrations/
│   └── wordpress-client/ # Plugin WordPress (futuro)
└── docker-compose.yml
```

## Inicio Rápido

### 1. Instalar dependencias

```bash
# Instalar pnpm si no lo tienes
npm install -g pnpm

# Instalar dependencias
pnpm install
```

### 2. Configurar entorno

```bash
cp .env.example .env
# Editar .env con tus valores
```

### 3. Iniciar base de datos

```bash
docker-compose up -d postgres redis
```

### 4. Ejecutar migraciones

```bash
pnpm db:migrate
```

### 5. Iniciar API en desarrollo

```bash
pnpm api:dev
```

La API estará disponible en http://localhost:3000

## SDK

### Instalación

```bash
npm install @makia/sdk
# o
pnpm add @makia/sdk
```

### Uso

```typescript
import { MakiaClient } from '@makia/sdk';

const makia = new MakiaClient({
  apiKey: 'mk_live_xxx',
});

// Crear reserva
const booking = await makia.bookings.create({
  customerName: 'Juan García',
  customerEmail: 'juan@example.com',
  date: '2024-02-15',
  time: '20:30',
  guests: 4,
});

// Obtener disponibilidad
makia.setOrganization('mi-restaurante');
const slots = await makia.availability.get({
  date: '2024-02-15',
  guests: 4,
});
```

## Widget Embebible

```html
<div id="makia-booking"></div>
<script
  src="https://cdn.makia.app/widget.js"
  data-restaurant="mi-restaurante"
  data-theme="light"
  data-primary-color="#4f46e5">
</script>
```

## API Endpoints

### Autenticación
- `POST /v1/auth/login` - Login con email/password
- `POST /v1/auth/register` - Registro
- `GET /v1/auth/me` - Usuario actual
- `POST /v1/auth/api-keys` - Generar API Key

### Reservas
- `GET /v1/bookings` - Listar reservas
- `POST /v1/bookings` - Crear reserva
- `GET /v1/bookings/:id` - Obtener reserva
- `PATCH /v1/bookings/:id` - Actualizar reserva
- `POST /v1/bookings/:id/confirm` - Confirmar reserva
- `POST /v1/bookings/:id/cancel` - Cancelar reserva

### Disponibilidad
- `GET /v1/availability` - Slots disponibles
- `POST /v1/availability/check` - Verificar disponibilidad

### Organizaciones
- `GET /v1/organizations/current` - Organización actual
- `PATCH /v1/organizations/current` - Actualizar
- `GET /v1/organizations/current/stats` - Estadísticas
- `GET /v1/organizations/current/business-hours` - Horarios
- `PUT /v1/organizations/current/business-hours` - Actualizar horarios

## Planes

| Plan | Reservas/mes | Precio |
|------|--------------|--------|
| Free | 20 | 0€ |
| Starter | 100 | 9€ |
| Pro | 500 | 29€ |
| Business | Ilimitadas | 79€ |

## Licencia

MIT
