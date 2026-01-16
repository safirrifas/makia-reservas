# Configuración de WhatsApp Business API

Esta guía te ayudará a configurar la integración de WhatsApp Business API con el plugin MakIA Reservas.

## Requisitos Previos

- Cuenta de Meta (Facebook/Instagram)
- Número de teléfono comercial verificado
- Acceso a Meta Business Suite
- WordPress con el plugin MakIA Reservas instalado

## Paso 1: Crear una Cuenta de Meta Business

1. Accede a [Meta Business Suite](https://business.facebook.com/)
2. Si no tienes cuenta, crea una nueva
3. Verifica tu identidad y número de teléfono

## Paso 2: Crear una Aplicación de Meta

1. Ve a [Meta Developers](https://developers.facebook.com/)
2. Haz clic en "Mis Aplicaciones" → "Crear Aplicación"
3. Selecciona "Empresarial" como tipo de aplicación
4. Completa los detalles:
   - **Nombre de la Aplicación:** MakIA Reservas WhatsApp
   - **Correo de Contacto:** tu@email.com
   - **Propósito:** Integración de WhatsApp para reservas

## Paso 3: Obtener Credenciales de WhatsApp

### 3.1 Obtener el Token de Acceso

1. En tu aplicación de Meta, ve a **Configuración** → **Básico**
2. Copia el **ID de Aplicación** y el **Token de Acceso**
3. Guarda estos valores en un lugar seguro

### 3.2 Obtener el ID del Número de Teléfono

1. Ve a **Herramientas** → **Explorador de API**
2. Selecciona tu aplicación en el menú desplegable
3. Ejecuta una consulta GET a `/me/phone_numbers`
4. Copia el **ID del número de teléfono** (phone_number_id)

### 3.3 Obtener el ID de la Cuenta de Negocio

1. Ve a **Configuración** → **Básico**
2. Busca el **ID de Cuenta de Negocio**
3. Guarda este valor

## Paso 4: Configurar en WordPress

1. Accede al panel de administración de WordPress
2. Ve a **MakIA Reservas** → **Configuración**
3. Busca la sección **WhatsApp Business API**
4. Completa los campos:
   - **Token de Acceso:** [Tu token de acceso]
   - **ID del Número de Teléfono:** [Tu phone_number_id]
   - **ID de la Cuenta de Negocio:** [Tu business_account_id]
5. Haz clic en **Guardar Configuración**

## Paso 5: Configurar Webhooks

Los webhooks permiten que WhatsApp envíe actualizaciones de estado a tu servidor.

### 5.1 Crear Webhook en Meta

1. Ve a tu aplicación de Meta
2. Selecciona **WhatsApp** → **Configuración**
3. En la sección **Webhooks**, haz clic en **Editar**
4. Completa los campos:
   - **URL de Callback:** `https://tu-sitio.com/wp-json/makia/v1/whatsapp/webhook`
   - **Token de Verificación:** Crea un token seguro (ej: `abc123xyz789`)
5. Haz clic en **Verificar y Guardar**

### 5.2 Guardar Token de Verificación en WordPress

1. Ve a **MakIA Reservas** → **Configuración**
2. Busca **Token de Verificación de Webhook**
3. Ingresa el token que creaste en el paso anterior
4. Haz clic en **Guardar**

## Paso 6: Suscribirse a Eventos

1. En la configuración de WhatsApp de Meta
2. Ve a **Webhooks** → **Suscribirse a eventos**
3. Selecciona los eventos:
   - `messages` (para recibir mensajes de clientes)
   - `message_status` (para actualizaciones de estado)
4. Haz clic en **Suscribirse**

## Paso 7: Configurar Plantillas de Mensajes

Meta requiere que apruebes plantillas de mensajes antes de enviarlos a clientes.

### 7.1 Crear Plantillas en Meta

1. Ve a **WhatsApp Manager** → **Plantillas de Mensajes**
2. Haz clic en **Crear Plantilla**
3. Crea las siguientes plantillas:

#### Plantilla 1: Confirmación de Reserva

```
Hola {{1}},

Tu reserva ha sido confirmada para el {{2}} a las {{3}}.

Detalles:
- Comensales: {{4}}
- Restaurante: {{5}}

¡Te esperamos! 🍽️

Gestionar reserva: {{6}}
```

**Variables:**
- {{1}} = Nombre del cliente
- {{2}} = Fecha (dd/mm/yyyy)
- {{3}} = Hora (HH:mm)
- {{4}} = Número de comensales
- {{5}} = Nombre del restaurante
- {{6}} = Enlace de gestión

#### Plantilla 2: Recordatorio de Reserva

```
Hola {{1}},

Recordatorio: Tu reserva es mañana a las {{2}} en {{3}}.

¿Necesitas cambiar algo? Responde SÍ o NO.

Gestionar: {{4}}
```

**Variables:**
- {{1}} = Nombre del cliente
- {{2}} = Hora (HH:mm)
- {{3}} = Nombre del restaurante
- {{4}} = Enlace de gestión

#### Plantilla 3: Cancelación de Reserva

```
Hola {{1}},

Tu reserva del {{2}} a las {{3}} en {{4}} ha sido cancelada.

Si deseas hacer una nueva reserva, accede aquí: {{5}}

Gracias.
```

**Variables:**
- {{1}} = Nombre del cliente
- {{2}} = Fecha (dd/mm/yyyy)
- {{3}} = Hora (HH:mm)
- {{4}} = Nombre del restaurante
- {{5}} = Enlace de reserva

### 7.2 Esperar Aprobación

Meta revisará tus plantillas en 24-48 horas. Recibirás una notificación cuando sean aprobadas.

## Paso 8: Probar la Integración

1. Ve a **MakIA Reservas** → **Operarios**
2. Crea una nueva reserva de prueba
3. Selecciona un cliente con número de WhatsApp
4. Haz clic en **Enviar WhatsApp**
5. Verifica que el mensaje se envíe correctamente

## Solución de Problemas

### Error: "Credenciales inválidas"

- Verifica que el token de acceso sea correcto
- Asegúrate de que el token no haya expirado
- Regenera el token en Meta si es necesario

### Error: "Número de teléfono no verificado"

- El número debe estar verificado en Meta Business Suite
- Completa el proceso de verificación de dos pasos

### Los mensajes no se envían

- Verifica que las plantillas estén aprobadas
- Comprueba que el número de cliente tenga formato internacional (+34...)
- Revisa los logs de error en WordPress

### No recibo respuestas de clientes

- Verifica que los webhooks estén configurados correctamente
- Comprueba que el token de verificación sea correcto
- Revisa que los eventos `messages` estén suscritos

## Costos

WhatsApp Business API tiene un modelo de precios basado en:

- **Mensajes entrantes:** Gratis (primeros 1000 por mes)
- **Mensajes salientes:** $0.0079 - $0.0099 por mensaje (según país)
- **Plantillas:** Gratis

Consulta los [precios actuales de WhatsApp](https://www.whatsapp.com/business/pricing/) para más información.

## Mejores Prácticas

1. **Usa plantillas aprobadas:** Solo envía mensajes con plantillas aprobadas por Meta
2. **Respeta horarios:** No envíes mensajes fuera de horarios comerciales
3. **Personaliza mensajes:** Usa variables para personalizar cada mensaje
4. **Monitorea entregas:** Revisa regularmente las estadísticas de entrega
5. **Responde a clientes:** Implementa respuestas automáticas para preguntas frecuentes

## Recursos Útiles

- [Documentación de WhatsApp Business API](https://developers.facebook.com/docs/whatsapp)
- [Meta Business Suite](https://business.facebook.com/)
- [Meta Developers](https://developers.facebook.com/)
- [WhatsApp Manager](https://www.whatsapp.com/business/manager/)

## Soporte

Si tienes problemas con la integración:

1. Revisa los logs en **MakIA Reservas** → **Logs**
2. Contacta al soporte de Meta en [Meta Help Center](https://www.facebook.com/help/)
3. Abre un ticket en [MakIA Support](https://contacpro.app/support/)

---

**Última actualización:** Enero 2026
**Versión:** 1.0
