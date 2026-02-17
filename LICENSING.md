# MakIA Restaurante - Sistema de Licencias

## Información General

**MakIA Restaurante** es un sistema de reservas bajo licencia desarrollado por **Contacpro**.

- **Sitio web principal:** https://contacpro.app
- **API de licencias:** https://contacpro.app/api
- **Dashboard:** https://contacpro.app/dashboard
- **Soporte:** https://contacpro.app/support
- **Documentación:** https://docs.contacpro.app

## Arquitectura de Dominios

```
┌─────────────────────────────────────────────────────────────┐
│                     CONTACPRO.APP                            │
│              (Backend principal - Licencias)                 │
├─────────────────────────────────────────────────────────────┤
│  • Gestión de licencias y planes                            │
│  • Dashboard de clientes                                     │
│  • Facturación y pagos                                       │
│  • Soporte técnico                                           │
│  • API de validación de licencias                           │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   PLUGIN WORDPRESS                           │
│              (makia-reservas.php)                            │
├─────────────────────────────────────────────────────────────┤
│  • Validación de licencia con contacpro.app                 │
│  • Formulario de reservas                                    │
│  • Panel de administración                                   │
│  • Notificaciones (Email/SMS/WhatsApp)                      │
└─────────────────────────────────────────────────────────────┘
```

## Planes de Licencia

| Plan | Reservas/mes | Precio | Características |
|------|--------------|--------|-----------------|
| **Chupito** (Free) | 20 | Gratis | Básico |
| **Caña** (Starter) | 100 | €9.99/mes | + Soporte email |
| **Jarra** (Pro) | 500 | €19.99/mes | + SMS/WhatsApp |
| **Barril** (Business) | Ilimitado | €49.99/mes | + Prioritario |

## Configuración de API

### En el Plugin WordPress

El plugin está configurado para conectar con contacpro.app:

```php
// makia-reservas.php línea 60
define('MAKIA_API_URL', 'https://contacpro.app/api');
```

### Endpoints de Licencia

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/license/validate` | POST | Validar licencia |
| `/api/license/activate` | POST | Activar licencia |
| `/api/license/usage` | GET | Obtener uso actual |
| `/api/license/plans` | GET | Listar planes disponibles |

### Ejemplo de Validación

```php
$response = wp_remote_post(MAKIA_API_URL . '/license/validate', array(
    'body' => array(
        'license_key' => $license_key,
        'domain' => home_url(),
    ),
));
```

## Límites por Plan

El sistema de licencias controla:

1. **Número de reservas por mes** - Se reinicia el día 1 de cada mes
2. **Funcionalidades disponibles** - SMS, WhatsApp, estadísticas avanzadas
3. **Número de operarios** - Según el plan
4. **Soporte** - Nivel de prioridad

## Flujo de Activación

1. Cliente compra licencia en **contacpro.app**
2. Recibe **clave de licencia** por email
3. Instala plugin en WordPress
4. Introduce clave en **MakIA Restaurante > Licencia**
5. Plugin valida con API de contacpro.app
6. ✅ Plugin activado

## Variables de Entorno

Para desarrollo o personalización:

```php
// wp-config.php

// API de licencias (producción)
define('MAKIA_API_URL', 'https://contacpro.app/api');

// API de licencias (staging/desarrollo)
// define('MAKIA_API_URL', 'https://staging.contacpro.app/api');
```

## Contacto y Soporte

- **Email:** soporte@contacpro.app
- **Web:** https://contacpro.app/support
- **Documentación:** https://docs.contacpro.app
- **Estado del servicio:** https://status.contacpro.app

## Plataforma Multiplataforma (Futuro)

La nueva plataforma multiplataforma (en desarrollo) utilizará:

- **API:** https://api.contacpro.app/v1
- **Dashboard:** https://dashboard.contacpro.app
- **Widget CDN:** https://cdn.contacpro.app/widget.js

Esta plataforma permitirá usar MakIA Restaurante fuera de WordPress (Shopify, Wix, custom sites, etc.)
