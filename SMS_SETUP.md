# Configuración de SMS con Twilio

## Introducción

El plugin MakIA Reservas incluye integración con **Twilio** para enviar SMS de confirmación y recordatorios a los clientes. Esta guía te ayudará a configurar el servicio paso a paso.

## Requisitos

- Cuenta de Twilio (https://www.twilio.com)
- Número de teléfono de Twilio
- Account SID y Auth Token de Twilio
- PHP 7.4 o superior

## Paso 1: Crear una Cuenta en Twilio

1. Visita https://www.twilio.com/try-twilio
2. Regístrate con tu email y contraseña
3. Verifica tu número de teléfono
4. Completa el formulario de configuración inicial

## Paso 2: Obtener tus Credenciales

1. Accede a tu [Twilio Console](https://www.twilio.com/console)
2. En la página principal, encontrarás:
   - **Account SID**: Tu identificador único
   - **Auth Token**: Tu token de autenticación

3. Copia estos valores (los necesitarás en el siguiente paso)

## Paso 3: Obtener un Número de Teléfono de Twilio

1. En la Twilio Console, ve a **Phone Numbers** > **Manage Numbers**
2. Haz clic en **Get your first Twilio phone number**
3. Selecciona el país y tipo de número
4. Confirma el número (ej: +1234567890)

## Paso 4: Configurar en WordPress

1. Accede al panel de administración de WordPress
2. Ve a **MakIA Reservas** > **Configuración SMS**
3. Ingresa los siguientes datos:
   - **Account SID**: Tu Account SID de Twilio
   - **Auth Token**: Tu Auth Token de Twilio
   - **Número de Twilio**: Tu número de teléfono de Twilio (ej: +1234567890)

4. Haz clic en **Guardar cambios**

## Paso 5: Configurar Plantillas de SMS

### Plantilla de Confirmación

Edita la plantilla de confirmación de SMS con variables disponibles:

- `{customer_name}`: Nombre del cliente
- `{booking_date}`: Fecha de la reserva
- `{booking_time}`: Hora de la reserva
- `{guests}`: Número de comensales
- `{customer_phone}`: Teléfono del cliente

**Ejemplo:**
```
Hola {customer_name}, tu reserva para el {booking_date} a las {booking_time} ha sido confirmada. Gracias.
```

### Plantilla de Recordatorio

Configura un recordatorio automático para el día anterior:

**Ejemplo:**
```
Recordatorio: Tu reserva es mañana a las {booking_time}. ¿Necesitas cambiar algo? Responde SÍ o NO.
```

## Paso 6: Habilitar Envío Automático

En la configuración del plugin, puedes habilitar:

- **Envío automático de confirmaciones**: Se envía cuando se confirma una reserva
- **Recordatorios automáticos**: Se envía 24 horas antes de la reserva
- **Notificaciones de cancelación**: Se envía cuando se cancela una reserva

## Paso 7: Configurar Webhook para Recibir Respuestas

Para recibir notificaciones de estado de entrega:

1. En Twilio Console, ve a **Messaging** > **Settings**
2. En **Webhooks**, configura:
   - **Status Callback URL**: `https://tudominio.com/wp-json/makia/v1/sms/webhook`
   - **Incoming Message URL**: `https://tudominio.com/wp-json/makia/v1/sms/incoming`

3. Selecciona **POST** como método

## Estadísticas y Monitoreo

### Ver Estadísticas de SMS

1. Ve a **MakIA Reservas** > **Estadísticas SMS**
2. Selecciona el período (día, semana, mes)
3. Visualiza:
   - Total de SMS enviados
   - SMS entregados
   - SMS fallidos
   - Tasa de entrega

### Historial de SMS

1. Ve a **MakIA Reservas** > **Historial SMS**
2. Visualiza todos los SMS enviados con:
   - Teléfono del cliente
   - Mensaje
   - Tipo (confirmación, recordatorio, etc.)
   - Estado (enviado, entregado, fallido)
   - Fecha de envío

## Costos

Twilio cobra por SMS enviados. Los precios varían según el país:

- **SMS salientes (outbound)**: Desde $0.0075 USD por SMS
- **SMS entrantes (inbound)**: Desde $0.0075 USD por SMS

Consulta los [precios actuales de Twilio](https://www.twilio.com/sms/pricing).

## Solución de Problemas

### No se envían los SMS

1. Verifica que las credenciales de Twilio sean correctas
2. Comprueba que tu número de Twilio esté activo
3. Revisa el log de errores en WordPress
4. Verifica que el cliente tenga un número de teléfono válido

### SMS no entregados

1. Verifica el estado en el historial de SMS
2. Comprueba que el número de teléfono del cliente sea válido
3. Revisa los límites de velocidad de Twilio
4. Consulta el [estado de Twilio](https://status.twilio.com)

### Error "Twilio no está configurado"

1. Verifica que hayas ingresado el Account SID y Auth Token
2. Comprueba que hayas ingresado un número de Twilio válido
3. Guarda los cambios nuevamente

## Mejores Prácticas

1. **Personaliza los mensajes**: Incluye el nombre del cliente para mayor engagement
2. **Respeta los horarios**: No envíes SMS fuera de horarios comerciales
3. **Monitorea las estadísticas**: Revisa regularmente la tasa de entrega
4. **Cumple con regulaciones**: Asegúrate de tener consentimiento del cliente
5. **Usa plantillas profesionales**: Mantén un tono amable y profesional

## Recursos Adicionales

- [Documentación de Twilio SMS](https://www.twilio.com/docs/sms)
- [Precios de Twilio](https://www.twilio.com/sms/pricing)
- [Twilio Console](https://www.twilio.com/console)
- [Códigos de error de Twilio](https://www.twilio.com/docs/sms/api/message-resource#status-values)

## Soporte

Si tienes problemas con la configuración:

1. Revisa este documento nuevamente
2. Contacta al soporte de Twilio
3. Contacta al equipo de MakIA Reservas

---

**Última actualización**: Enero 2026  
**Versión del plugin**: 4.2.0+
