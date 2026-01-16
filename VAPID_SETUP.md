# Configuración de VAPID Keys para Web Push Notifications

## ¿Qué son las VAPID Keys?

VAPID (Voluntary Application Server Identification) son claves criptográficas que permiten que tu servidor de aplicaciones se identifique ante el servicio de push del navegador. Son esenciales para enviar notificaciones push web.

## Pasos para Generar VAPID Keys

### Opción 1: Usar Web Push Codelab (Recomendado)

1. Abre https://web-push-codelab.glitch.me/ en tu navegador
2. Haz clic en el botón "Generate Keys"
3. Verás dos claves generadas:
   - **Public Key**: Clave pública (se comparte con los clientes)
   - **Private Key**: Clave privada (se mantiene segura en el servidor)

### Opción 2: Generar con Node.js

Si prefieres generar localmente:

```bash
npm install -g web-push
web-push generate-vapid-keys
```

Esto generará algo como:

```
Public Key: BEn...
Private Key: xyz...
```

## Configurar en WordPress

### 1. Agregar las claves a wp-config.php

Abre tu archivo `wp-config.php` y agrega estas líneas (reemplaza con tus claves reales):

```php
// VAPID Keys para Web Push Notifications
define('MAKIA_VAPID_PUBLIC_KEY', 'tu_clave_publica_aqui');
define('MAKIA_VAPID_PRIVATE_KEY', 'tu_clave_privada_aqui');
```

**⚠️ IMPORTANTE:** 
- Nunca compartas tu clave privada
- No la incluyas en repositorios públicos
- Usa variables de entorno en producción

### 2. Usar Variables de Entorno (Recomendado para Producción)

En lugar de wp-config.php, usa variables de entorno:

```bash
export MAKIA_VAPID_PUBLIC_KEY="tu_clave_publica"
export MAKIA_VAPID_PRIVATE_KEY="tu_clave_privada"
```

Luego en wp-config.php:

```php
define('MAKIA_VAPID_PUBLIC_KEY', getenv('MAKIA_VAPID_PUBLIC_KEY'));
define('MAKIA_VAPID_PRIVATE_KEY', getenv('MAKIA_VAPID_PRIVATE_KEY'));
```

## Verificar la Configuración

### 1. Desde el Panel de Administración

1. Ve a **MakIA Reservas > Configuración**
2. Busca la sección "Web Push Notifications"
3. Verifica que las claves estén configuradas correctamente

### 2. Desde el Código

Agrega este código en functions.php para verificar:

```php
if (defined('MAKIA_VAPID_PUBLIC_KEY') && defined('MAKIA_VAPID_PRIVATE_KEY')) {
    error_log('[MakIA] VAPID Keys configuradas correctamente');
} else {
    error_log('[MakIA] ADVERTENCIA: VAPID Keys no configuradas');
}
```

## Probar Notificaciones Push

### 1. Desde la App PWA

1. Abre la app en tu navegador: https://tu-dominio.com/makia-operators
2. Ve a la página de login
3. Haz clic en "Usar Biometría" (si está disponible)
4. Registra tu dispositivo biométrico
5. Debería aparecer un prompt pidiendo permiso para notificaciones

### 2. Enviar Notificación de Prueba

Desde el panel de administración de WordPress:

```php
// En functions.php o en una página de administración
$push_service = new MakIA_Push_Service();
$push_service->send_to_all_operators(
    'Notificación de Prueba',
    'Esta es una notificación de prueba del sistema'
);
```

## Solución de Problemas

### "VAPID Keys no configuradas"

**Solución:** Verifica que hayas agregado las constantes en wp-config.php antes de `require_once(ABSPATH . 'wp-settings.php');`

### "Error al enviar notificación push"

**Posibles causas:**
- VAPID keys incorrectas o expiradas
- El dispositivo no tiene permisos para notificaciones
- El navegador no soporta Web Push API
- El endpoint de push ha expirado

**Solución:** 
1. Regenera nuevas VAPID keys
2. Solicita nuevamente permisos al usuario
3. Verifica los logs de WordPress en `wp-content/debug.log`

### "Notificaciones no llegan"

**Posibles causas:**
- El usuario denegó permisos para notificaciones
- La app no está registrada como PWA
- El Service Worker no está activo

**Solución:**
1. Verifica los permisos del navegador
2. Abre DevTools > Application > Service Workers
3. Asegúrate que el Service Worker esté "activated and running"

## Rotación de VAPID Keys

Es recomendable rotar las VAPID keys cada 6-12 meses:

1. Genera nuevas claves
2. Actualiza wp-config.php
3. Los usuarios deberán re-registrar sus dispositivos
4. Las suscripciones antiguas dejarán de funcionar

## Seguridad

### Mejores Prácticas

1. **Nunca expongas la clave privada:**
   - No la incluyas en repositorios públicos
   - No la compartas por email o chat
   - Usa variables de entorno en producción

2. **Usa HTTPS:**
   - Web Push requiere conexión segura
   - Los navegadores rechazarán notificaciones sin HTTPS

3. **Valida suscripciones:**
   - Verifica que el endpoint sea válido
   - Elimina suscripciones expiradas
   - Implementa rate limiting

4. **Monitorea errores:**
   - Revisa los logs regularmente
   - Implementa alertas para fallos de envío
   - Mantén estadísticas de entrega

## Referencias

- [Web Push Protocol (RFC 8030)](https://tools.ietf.org/html/rfc8030)
- [VAPID Specification](https://datatracker.ietf.org/doc/html/draft-thomson-webpush-vapid)
- [MDN Web Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API)
- [Web Push Codelab](https://web-push-codelab.glitch.me/)

## Soporte

Si tienes problemas con la configuración de VAPID keys:

1. Revisa los logs de WordPress: `wp-content/debug.log`
2. Verifica la consola del navegador: F12 > Console
3. Abre un issue en el repositorio del plugin
4. Contacta al equipo de soporte de MakIA

---

**Última actualización:** Enero 2026
**Versión del Plugin:** 4.2.0+
