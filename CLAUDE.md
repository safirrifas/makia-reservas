# MakIA Reservas - Memoria de Trabajo

## Descripcion del Proyecto
Plugin WordPress (v4.3.1) para gestion de reservas de restaurantes.
Nombre: **MakIA - Sistema de Reservas**
API externa: `https://contacpro.app/api`
Requiere: WordPress 5.0+, PHP 7.4+

## Arquitectura

### Archivos principales
- `makia-reservas.php` - Entry point del plugin, carga todos los modulos
- `includes/class-makia-admin.php` - Panel admin con sidebar hamburger (13 tabs)
- `includes/class-makia-bookings.php` - CRUD de reservas, emails, AJAX, bulk actions
- `includes/class-makia-api.php` - REST API para operarios (JWT basico)
- `includes/class-makia-shortcodes.php` - 3 shortcodes: `[makia_reservas]`, `[makia_gestionar_reserva]`, `[makia_boton_reserva]`

### Tabs del panel admin
1. Principal (dashboard stats)
2. Reservas (gestion completa con bulk actions)
3. Configuracion (nombre, email, logo, legal)
4. Horarios (schedule semanal con slots)
5. Dias Especiales (cerrados/apertura excepcional)
6. Capacidad (slots por turno)
7. Diseno (6 plantillas + colores custom + preview)
8. Boton Reservas (floating button config)
9. Plantillas (email/SMS/WhatsApp templates)
10. WhatsApp (Business API config)
11. Lista Negra (ban por email/phone)
12. Licencia (planes: Chupito/Cana/MasCana)
13. Submenu: Operarios + Auditoria

### Base de datos (tablas custom)
- `wp_makia_bookings` - Reservas (status: pending/approved/rejected/cancelled)
- `wp_makia_booking_notes` - Notas internas
- `wp_makia_notification_templates` - Templates email/SMS/WA
- `wp_makia_blacklist` - Usuarios baneados
- `wp_makia_audit` - Log de auditoria
- `wp_makia_operators` - Operarios
- `makia_pwa_subscriptions` - Push notifications

### JavaScript (assets/js/)
- `makia-booking.js` - Frontend: modal, formulario AJAX, horarios (PRINCIPAL)
- `makia-booking-enhanced.js` - Complementario: validaciones, micro-interacciones, accesibilidad
- `makia-admin.js` - Admin: aprobar/rechazar reservas AJAX
- `makia-admin-design.js` - Admin: panel de diseno
- `makia-admin-tabs.js` - Admin: navegacion tabs
- `makia-hamburger-menu.js` - Admin: sidebar menu
- `makia-bulk-actions.js` - Admin: acciones masivas
- `makia-booking-notes.js` - Admin: notas internas
- `makia-floating-button.js` - Frontend: boton flotante

### CSS (assets/css/) - 12 archivos
Principales: `makia-styles.css`, `makia-admin-improved.css`, `makia-booking-templates.css`

## Convenciones de codigo

### PHP
- Guard ABSPATH obligatorio: `if (!defined('ABSPATH')) { exit; }`
- Sanitizar inputs: `sanitize_text_field()`, `sanitize_email()`, `intval()`
- Escapar outputs: `esc_html()`, `esc_attr()`, `esc_url()`
- AJAX: siempre `check_ajax_referer()` + `current_user_can()`
- Colores: validar con `sanitize_hex_color()`
- Opciones WP: prefijo `makia_`

### JavaScript
- Usar `escapeHtml()` para datos del servidor mostrados en DOM
- Usar `.text()` en vez de `.html()` para mensajes de notificacion
- Modal open/close centralizado en `makia-booking.js` (CSS class toggle con `.active`)
- `makia-booking-enhanced.js` solo agrega mejoras complementarias, NO duplica handlers

### Emails
- Content-Type: `text/plain; charset=UTF-8` (body usa `\n`, no HTML)
- Variables del restaurante siempre via `get_option()`, nunca hardcodeadas

## Sistema de licencias
- 3 planes: Chupito (5/mes gratis), Cana (15/mes 5EUR), MasCana (100/mes 15EUR)
- Reset mensual dia 1 a las 00:00
- Verificacion via API externa en `contacpro.app`

## Problemas conocidos resueltos (v4.3.1+)
- Webhook WhatsApp: firma obligatoria cuando app_secret configurado
- Tab Apariencia eliminado (era placeholder, Diseno cubre su funcion)
- Conflicto modal JS entre booking.js y booking-enhanced.js resuelto
- Email Content-Type corregido de text/html a text/plain
- Nombre hardcodeado "Restaurante Brote" eliminado
- Special days: start_time/end_time anadidos para compatibilidad frontend
- Null check en $makia_license_manager en shortcodes
- ABSPATH guard anadido a 6 archivos que faltaban
- Console.log innecesarios limpiados de admin JS
- Admin responsive mejorado: Diseno (grid 1fr), Licencia (stats/planes 1col), Boton (flex-wrap), filtros, form-tables, inputs fijos, breakpoint 480px

## Problemas pendientes (no criticos)
- JWT de API REST es base64 sin firma criptografica (considerar firebase/php-jwt)
- `create_table()` en notes/audit se ejecuta en cada page load (mover a activation hook)
- Audit logging no cubre bulk actions
- Assets admin solo cargan en pagina principal (no en submenu operarios/auditoria si acceso directo)
- Email del cliente incluye `<a href>` tags que no se renderizan en text/plain (considerar HTML email)
