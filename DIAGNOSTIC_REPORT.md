# Informe de Diagnóstico - MakIA Reservas v4.2.0

**Fecha:** 2026-02-10
**Archivos analizados:** 26 PHP, 9 JavaScript, 2 Templates
**Total de problemas encontrados:** ~130

---

## Resumen Ejecutivo

| Severidad | PHP Backend | JavaScript Frontend | Total |
|-----------|-------------|---------------------|-------|
| **CRITICO** | 10 | 8 | **18** |
| **ALTO** | 22 | 7 | **29** |
| **MEDIO** | 35 | 12 | **47** |
| **BAJO** | 23 | 13 | **36** |
| **Total** | **90** | **40** | **~130** |

---

## 🔴 PROBLEMAS CRÍTICOS (Requieren corrección inmediata)

### 1. Autenticación JWT completamente rota (Falsificación de tokens)
**Archivo:** `includes/class-makia-api.php:552-596`
**Impacto:** Cualquier atacante puede suplantar a cualquier usuario

El "token JWT" no es un JWT real -- es un payload JSON codificado en base64 **sin firma criptográfica**. Un atacante solo necesita enviar:
```
Authorization: Bearer eyJ1c2VyX2lkIjoxfQ==
```
(que es simplemente `{"user_id":1}` en base64) para obtener acceso total como administrador.

**Problemas relacionados:**
- La expiración del token nunca se verifica (línea 588-595)
- El `permission_callback` no verifica el rol del usuario (línea 601-604)

**Corrección:** Usar una librería JWT real (ej: `firebase/php-jwt`) con firma HMAC, verificación de expiración y validación de roles.

---

### 2. URL de API de WhatsApp incorrecta (Instagram en vez de WhatsApp)
**Archivo:** `includes/class-makia-whatsapp.php:32`
```php
private $api_url = 'https://graph.instagram.com/v18.0'; // ← INCORRECTO
```
**Corrección:** Cambiar a `https://graph.facebook.com/v18.0`

---

### 3. Webhook de WhatsApp sin verificación de firma
**Archivo:** `includes/class-makia-whatsapp-webhook.php:75-109`
El endpoint del webhook es público (`permission_callback => '__return_true'`) y no verifica la cabecera `X-Hub-Signature-256`. Cualquiera que conozca la URL puede enviar eventos falsificados para cancelar reservas o inyectar datos.

**Corrección:** Verificar el HMAC-SHA256 del body usando el app secret de WhatsApp.

---

### 4. Race Condition en verificación de capacidad (TOCTOU)
**Archivo:** `includes/class-makia-bookings.php:427-481`
Entre el `SELECT` que verifica capacidad y el `INSERT` que crea la reserva, otra petición concurrente puede insertar una reserva, causando overbooking.

**Corrección:** Usar transacciones con `SELECT ... FOR UPDATE` o `LOCK TABLES`.

---

### 5. IP spoofing anula el rate limiting
**Archivo:** `includes/class-makia-bookings.php:93-94`
```php
$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
```
`HTTP_X_FORWARDED_FOR` es controlable por el cliente. Un atacante puede enviar un IP diferente en cada petición, saltándose completamente el rate limiting.

**Corrección:** Usar `$_SERVER['REMOTE_ADDR']` como fuente primaria.

---

### 6. Error de base de datos expuesto al cliente
**Archivo:** `includes/class-makia-bookings.php:524`
```php
wp_send_json_error(array('message' => '... (DB: ' . $db_error . ')'));
```
Expone nombres de tablas, columnas y sintaxis SQL al atacante.

**Corrección:** Loggear el error internamente y devolver un mensaje genérico.

---

### 7. SQL sin preparar ejecutado desde archivo externo
**Archivo:** `includes/class-makia-templates.php:310-316`
```php
$sql = file_get_contents($sql_file);
// ...
$wpdb->query($query); // Sin $wpdb->prepare()
```

**Corrección:** Usar queries parametrizados o insertar plantillas con código PHP.

---

### 8. Push notifications VAPID completamente no funcional
**Archivo:** `includes/class-makia-push-service.php:181-185`
El header de autorización VAPID es un placeholder hardcodeado. Todas las push notifications fallarán con 401.

**Corrección:** Implementar generación JWT real con la clave privada VAPID.

---

### 9. XSS múltiple en JavaScript via `innerHTML`/`.html()` con datos del servidor
**Archivos afectados:**
- `assets/js/makia-booking.js:115-135` - Mensajes de respuesta
- `assets/js/makia-admin.js:45,51,145` - Notificaciones admin
- `assets/js/makia-admin-design.js:184` - Preview de diseño
- `assets/js/makia-bulk-actions.js:172,190,233` - Acciones masivas
- `assets/js/makia-booking-notes.js:35,39,122,147` - Notas

**Corrección:** Usar `.text()` o `textContent` en vez de `.html()` / `innerHTML`.

---

### 10. Inyección CSS/JS via valores de color sin validar
**Archivo:** `assets/js/makia-admin-design.js:221-238`
Colores interpolados directamente en `<style>` sin validación. Un valor como `</style><script>alert(1)</script>` permite ejecutar código.

**Corrección:** Validar que los colores coincidan con `/^#[0-9a-fA-F]{6}$/`.

---

## 🟠 PROBLEMAS ALTOS

### Seguridad - AJAX sin nonce (CSRF)

| Archivo | Método | Línea |
|---------|--------|-------|
| `class-makia-bookings.php` | `send_reminder_ajax()` | 1794 |
| `class-makia-blacklist.php` | `ban_user_ajax()` | 150 |
| `class-makia-blacklist.php` | `unban_user_ajax()` | 181 |
| `class-makia-blacklist.php` | `increment_noshow_ajax()` | 205 |
| `makia-booking-notes.js` | Reminder AJAX call | 61-67 |

### Seguridad - Falta verificación de permisos

| Archivo | Acción | Línea |
|---------|--------|-------|
| `class-makia-admin.php` | Guardar configuración | 356 |
| `class-makia-admin.php` | Guardar config botón | 744 |
| `class-makia-templates.php` | Guardar/restaurar plantillas | 87-96 |
| `class-makia-capacity-ui.php` | Guardar config capacidad | 16-27 |

### Seguridad - IDOR en API REST

| Archivo | Endpoint | Línea | Problema |
|---------|----------|-------|----------|
| `class-makia-api.php` | `get_operator_bookings` | 297 | No filtra por operador |
| `class-makia-api.php` | `update_booking_status` | 327 | Sin verificación de propiedad |
| `class-makia-api.php` | `add_booking_note` | 385 | Sin verificación de propiedad |
| `class-makia-api.php` | `get_booking_notes` | 432 | Sin verificación de propiedad |

### Seguridad - XSS en PHP

| Archivo | Problema | Línea(s) |
|---------|----------|----------|
| `class-makia-admin.php` | Datos de API remota sin escapar | 577-638 |
| `class-makia-admin.php` | Contadores DB sin escapar | 289-313 |
| `class-makia-bookings.php` | Atributos HTML sin `esc_attr()` | 1117-1270 |
| `class-makia-bookings.php` | XSS en modal JS admin | 1304-1327 |
| `class-makia-notes.php` | `note.author_name` sin escapar en JS | 476 |

### Seguridad - Otros problemas altos

| Archivo | Problema | Línea |
|---------|----------|-------|
| `class-makia-admin.php` | Upload sin validación MIME | 369-374 |
| `class-makia-api.php` | Endpoint de licencia expone datos sensibles sin auth | 192-200 |
| `class-makia-api.php` | Sin validación de valores de status | 96-100 |
| `class-makia-audit.php` | IP spoofing en audit logs | 98-110 |
| `class-makia-push-service.php` | SQL sin `$wpdb->prepare()` | 87-92 |
| `class-makia-logger.php` | Log accesible via web | 19 |
| `class-makia-pwa-link.php` | Email enviado a servicio QR externo | 182-186 |
| `class-makia-bookings.php` | Modificación pública sin validación | 2036-2110 |

---

## 🟡 PROBLEMAS MEDIOS

### Lógica y Funcionalidad

| # | Archivo | Problema | Línea |
|---|---------|----------|-------|
| 1 | `class-makia-bookings.php` | Verificación de capacidad duplicada (código muerto) | 455-460 |
| 2 | `class-makia-bookings.php` | Nonce verificado después del rate limit | 86-155 |
| 3 | `class-makia-bookings.php` | Sin validación estricta de formato fecha/hora | 267-268 |
| 4 | `class-makia-bookings.php` | Nombre hardcodeado "Restaurante Brote" | 1818 |
| 5 | `class-makia-bookings.php` | Sin audit log en acciones masivas | 1874-2030 |
| 6 | `class-makia-bookings.php` | Email Content-Type text/html con body texto plano | 626-628 |
| 7 | `class-makia-admin.php` | `wp_localize_script` duplicado sobreescribe config | 101-111 |
| 8 | `class-makia-admin.php` | Assets solo cargan en página principal, no subpáginas | 53-55 |
| 9 | `class-makia-admin.php` | Sin null check en `$makia_license_manager` | 123 |
| 10 | `class-makia-api.php` | `$wpdb->update` retorno 0 tratado como error | 333-349 |
| 11 | `class-makia-api.php` | `CREATE TABLE` dentro del handler de request | 472-486 |
| 12 | `class-makia-api.php` | Sin paginación en queries de reservas | 297-309 |
| 13 | `class-makia-notes.php` | `create_table()` ejecutado en cada page load | 19 |
| 14 | `class-makia-audit.php` | `create_table()` ejecutado en cada page load | 19 |
| 15 | `class-makia-audit.php` | `array_filter` no retorna índice esperado | 393-420 |
| 16 | `class-makia-design.php` | Shortcode incorrecto `makia_booking_form` → CSS nunca carga | 93 |
| 17 | `class-makia-shortcodes.php` | `get_post()` puede retornar null → Fatal Error | 20 |
| 18 | `class-makia-design.php` | `get_post()` puede retornar null → Fatal Error | 93 |
| 19 | `class-special-days.php` | Eliminación por índice de array (race condition) | 244-252 |
| 20 | `class-special-days.php` | Horas de `exceptional_opening` no se guardan | 196-199 |
| 21 | `class-makia-whatsapp-webhook.php` | Datos entrantes sin sanitizar almacenados en BD | 132-141 |
| 22 | `class-makia-whatsapp-webhook.php` | LIKE query con teléfono sin escapar wildcards | 200-207 |

### JavaScript - Problemas medios

| # | Archivo | Problema | Línea |
|---|---------|----------|-------|
| 1 | `makia-hamburger-menu.js` | Scroll boundary compara valor consigo mismo (dead code) | 264-265 |
| 2 | `makia-hamburger-menu.js` | Double-fire en dispositivos táctiles | 109-119, 196 |
| 3 | `makia-booking.js` | Race condition: doble envío de formulario | 69-150 |
| 4 | `makia-booking.js` + `enhanced.js` | Conflicto entre handlers de submit | múltiples |
| 5 | `makia-floating-button.js` | Memory leak: `setInterval` nunca limpiado | 84-88 |
| 6 | `makia-floating-button.js` | Cleanup incompleto en `destroy()` | 236-244 |

---

## 🟢 PROBLEMAS BAJOS

### Falta de guard ABSPATH (afecta a TODOS los archivos PHP)

Ninguno de los 23 archivos PHP en `includes/` tiene el guard `if (!defined('ABSPATH')) exit;`. Solo el archivo principal `makia-reservas.php` lo tiene.

### Otros problemas bajos destacables

- `strlen()` usado en vez de `mb_strlen()` para validación UTF-8 (`class-makia-bookings.php:180`)
- Sin validación mínima de comensales (permite 0 o negativos) (`class-makia-bookings.php:269`)
- Falta `return` después de `wp_send_json_error()` en múltiples handlers
- Licencia almacenada sin encriptar en `wp_options` (`class-license-manager.php:246`)
- `date()` usado en vez de `wp_date()` (ignora timezone WordPress)
- Variables `$` jQuery con valores string (convención engañosa)
- Namespace global contaminado (`window.loadBookingNotes`, `window._`)
- Datos sensibles en `console.log` en producción (`makia-booking.js:100`)

---

## Priorización de Correcciones

### Fase 1 - Inmediato (Seguridad crítica)
1. Implementar JWT real con firma criptográfica en la API
2. Corregir URL de WhatsApp API (`instagram` → `facebook`)
3. Verificar firma `X-Hub-Signature-256` en webhook
4. Resolver race condition de capacidad con transacciones DB
5. Eliminar exposición de errores de DB al cliente
6. Corregir IP spoofing en rate limiting

### Fase 2 - Urgente (Seguridad alta)
1. Añadir nonce verification a todos los AJAX handlers
2. Añadir `current_user_can()` a todos los form handlers
3. Corregir IDOR en endpoints API (filtrar por operador)
4. Escapar todas las salidas HTML con `esc_html()`/`esc_attr()`
5. Sanitizar datos en JavaScript (usar `.text()` en vez de `.html()`)
6. Proteger archivo de logs contra acceso web

### Fase 3 - Importante (Funcionalidad)
1. Corregir shortcode `makia_booking_form` → `makia_reservas` en design
2. Mover `create_table()` a hook de activación
3. Implementar VAPID JWT real para push notifications
4. Añadir guard ABSPATH a todos los archivos PHP
5. Corregir lógica de scroll boundary en hamburger menu
6. Resolver conflicto entre `makia-booking.js` y `makia-booking-enhanced.js`

### Fase 4 - Mejoras (Calidad de código)
1. Validación estricta de formatos de fecha/hora
2. Eliminar código duplicado de verificación de capacidad
3. Añadir audit logging a acciones masivas
4. Reemplazar nombre hardcodeado "Restaurante Brote"
5. Corregir Content-Type de emails (text/html vs text/plain)
6. Generar QR codes localmente en vez de servicio externo
