# MakIA - Sistema de Reservas para Restaurantes

**Versión**: 4.0.0  
**Requiere WordPress**: 5.0 o superior  
**Requiere PHP**: 7.4 o superior  
**Licencia**: GPL v2 o posterior

---

## 🎨 ¡NUEVO en v4.0.0! - Diseño Profesional

### ✨ Vista Previa en Vivo
- **Panel Interactivo**: Visualiza cambios en tiempo real mientras personalizas
- **Modo Responsive**: Alterna entre vista desktop y móvil
- **Actualización Instantánea**: Sin necesidad de guardar para ver cambios

### 🎭 6 Plantillas Profesionales
1. **✨ Moderno Minimalista** - Limpio, espacioso y contemporáneo
2. **👑 Elegante Clásico** - Refinado con detalles dorados
3. **🎨 Colorido Vibrante** - Gradientes modernos y dinámicos
4. **🌙 Oscuro Premium** - Experiencia exclusiva y elegante
5. **🌿 Rústico Acogedor** - Cálido y tradicional
6. **💼 Profesional Corporativo** - Confiable y estructurado

### 🎨 Personalización Avanzada
- **Logo Personalizado**: Sube el logo de tu restaurante
- **Colores a Medida**: Elige colores primarios, secundarios y de texto
- **Textos Personalizables**: Título, subtítulo y botón de envío
- **Vista Previa en Vivo**: Ve los cambios instantáneamente

### ✨ Animaciones Premium
- Transiciones suaves con cubic-bezier optimization
- Efectos hover elegantes en inputs y botones
- Animaciones de ondas expansivas
- Loading states con spinners animados
- Micro-interacciones pulidas en todos los elementos

### 📱 100% Responsive
- Adaptación perfecta a móviles, tablets y desktop
- Font-size optimizado para evitar zoom en iOS
- Layouts adaptativos por dispositivo
- Touch-friendly en dispositivos táctiles

---

## 🎉 Características Principales

### 🔒 Seguridad Robusta
- ✅ **Rate Limiting**: Protección contra spam (3 intentos/10min)
- ✅ **Validación de Capacidad**: Previene overbooking efectivamente
- ✅ **Sistema de Logging**: Auditoría completa de eventos
- ✅ **Validaciones Mejoradas**: Teléfonos, emails y nombres más robustos

### 📋 Gestión Completa
- ✅ **Sistema de Licencias**: Activación y verificación automática cada 24 horas
- ✅ **Gestión de Horarios**: Configuración semanal con múltiples franjas por día
- ✅ **Control de Capacidad**: Límite de personas totales y por reserva
- ✅ **Formulario Embebible**: Shortcode `[makia_booking_form]` profesional
- ✅ **Validación en Tiempo Real**: Verifica horarios y disponibilidad
- ✅ **Panel de Administración**: Interfaz moderna y fácil de usar

## Instalación

### Método 1: Desde el Panel de WordPress (Recomendado)

1. Descarga el archivo `makia-restaurante-v3.1.1.zip`
2. Ve a **Plugins → Añadir nuevo** en tu WordPress
3. Haz clic en **Subir plugin**
4. Selecciona el archivo ZIP descargado
5. Haz clic en **Instalar ahora**
6. Activa el plugin

### Método 2: Por FTP/SFTP

1. Descomprime el archivo `makia-restaurante-v3.1.1.zip`
2. Sube la carpeta `makia-plugin-v3.1.1` a `/wp-content/plugins/`
3. Ve a **Plugins** en WordPress y activa "MakIA - Sistema de Reservas"

## Configuración

### 1. Activar Licencia

1. Ve a **MakIA → Licencia** en el menú de WordPress
2. Ingresa tu clave de licencia (formato: `MAKIA-XXXX-XXXX-XXXX-XXXX`)
3. Haz clic en **Activar Licencia**
4. Verás el mensaje de confirmación si la activación fue exitosa

**Licencia de Prueba**: `MAKIA-CDC5-6808-C086-FAB3-A085`

### 2. Configurar Información del Restaurante

Ve a **MakIA → Configuración** y completa:

- **Nombre del Restaurante**: Nombre completo de tu establecimiento
- **Email**: Email de contacto (se usará para notificaciones)
- **Teléfono**: Teléfono de contacto
- **Dirección**: Dirección completa del restaurante

### 3. Configurar Horarios de Apertura

Ve a **MakIA → Horarios** y configura los horarios semanales:

**Ejemplo**:
- **Lunes**: 
  - ✅ Abierto
  - Franja 1: 12:00 - 16:00
  - Franja 2: 20:00 - 23:00
- **Martes**: Similar a Lunes
- **Miércoles**: ❌ Cerrado
- **Jueves a Domingo**: Configurar según necesidad

Puedes agregar múltiples franjas horarias por día (almuerzo, cena, etc.).

### 4. Configurar Capacidad

Ve a **MakIA → Capacidad** y establece:

- **Capacidad Máxima Total**: Número máximo de personas por franja horaria (ej: 50)
- **Máximo por Reserva**: Número máximo de personas por reserva individual (ej: 12)

### 5. 🎨 Personalizar Diseño del Formulario (NUEVO v4.0.0)

Ve a **MakIA → Diseño** para acceder al nuevo panel de personalización:

#### 🎭 Seleccionar Plantilla

1. **Visualiza las 6 Plantillas**: Cada una con su estilo único
2. **Revisa la Paleta de Colores**: Pasa el ratón sobre los colores para ver los códigos hex
3. **Haz Clic en "Seleccionar Plantilla"**: La plantilla se aplicará instantáneamente
4. **Ve la Vista Previa**: El formulario se actualiza en tiempo real

**Plantillas Disponibles**:
- **✨ Moderno Minimalista**: Perfecto para restaurantes contemporáneos
- **👑 Elegante Clásico**: Ideal para establecimientos de lujo
- **🎨 Colorido Vibrante**: Para restaurantes modernos y dinámicos
- **🌙 Oscuro Premium**: Experiencia exclusiva y sofisticada
- **🌿 Rústico Acogedor**: Perfecto para asadores y cocina tradicional
- **💼 Profesional Corporativo**: Para eventos corporativos y negocios

#### 🎨 Personalización Avanzada

**Subir Logo**:
1. Haz clic en el área de "Logo del Restaurante"
2. Selecciona tu logo desde la biblioteca de medios
3. El logo se mostrará centrado en el formulario
4. Tamaño recomendado: 120-140px de ancho

**Personalizar Textos**:
- **Título**: Por defecto "Reserva tu Mesa"
- **Subtítulo**: Mensaje descriptivo del formulario
- **Texto del Botón**: Por defecto "Confirmar Reserva"

**Colores Personalizados**:
- **Color Primario**: Afecta botones, enlaces y estados de focus
- **Color Secundario**: Afecta fondos y elementos de soporte
- **Color de Texto**: Color del título y textos principales

💡 **Tip**: Los cambios se reflejan instantáneamente en la vista previa

#### 👁️ Vista Previa en Vivo

**Características**:
- **Modo Desktop/Móvil**: Toggle para ver cómo se verá en diferentes dispositivos
- **Actualización Instantánea**: Sin necesidad de guardar
- **Datos de Ejemplo**: Muestra el formulario completo con datos placeholder
- **Interactiva**: Simula el comportamiento real del formulario

#### 💾 Guardar Cambios

1. Selecciona tu plantilla favorita
2. Personaliza colores, textos y logo
3. Verifica en la vista previa
4. Haz clic en **"💾 Guardar Personalización"**
5. ¡Listo! Tu formulario ya tiene el nuevo diseño

**Nota**: Las reservas existentes no se ven afectadas por cambios de diseño


### 5. Insertar Formulario de Reservas

#### Shortcode Básico
En cualquier página o entrada, inserta:
```
[makia_reservas]
```

#### Shortcode con Tema Oscuro
```
[makia_reservas theme="dark"]
```

#### En Código PHP
```php
<?php echo do_shortcode('[makia_reservas]'); ?>
```

## Uso del Formulario

El formulario incluye los siguientes campos:

**Datos Personales**:
- Nombre *
- Apellidos *
- Teléfono *
- Email *

**Detalles de la Reserva**:
- Número de comensales * (1-12)
- Fecha de reserva *
- Hora de reserva *
- Motivo de visita * (Cena romántica, Cumpleaños, Negocios, etc.)
- Zona preferida (Interior, Terraza, Barra)
- Comentarios especiales

**Validaciones Automáticas**:
- ✅ Verifica que la fecha/hora esté dentro de los horarios de negocio
- ✅ Comprueba que no se exceda la capacidad máxima
- ✅ Valida que el número de comensales no supere el límite por reserva

## Panel de Administración

### MakIA → Panel Principal
Dashboard con resumen de reservas y acceso rápido a todas las funciones.

### MakIA → Licencia
- Activar/desactivar licencia
- Ver estado de la licencia
- Ver fecha de expiración
- Ver número de activaciones

### MakIA → Configuración
- Información del restaurante
- Email de contacto
- Teléfono y dirección

### MakIA → Horarios
- Configuración semanal de horarios
- Múltiples franjas por día
- Días cerrados

### MakIA → Capacidad
- Capacidad máxima total
- Máximo por reserva individual

## API y Endpoints

El plugin se comunica con la API de MakIA en:
```
https://3000-ih2m3n296g8yezjfgxxvr-010ef421.us2.manus.computer/api
```

**Endpoints utilizados**:
- `/trpc/licenses.activate` - Activar licencia
- `/trpc/licenses.verify` - Verificar licencia
- `/trpc/licenses.deactivate` - Desactivar licencia
- `/trpc/reservations.create` - Crear reserva

## Diseño

El formulario utiliza un diseño **brutalista monocromático** con:

- Bordes gruesos negros (4px)
- Sombras offset para profundidad
- Tipografía pesada y mayúsculas
- Contraste alto blanco/negro
- Responsive para móviles
- Tema claro/oscuro

## Solución de Problemas

### Error: "Licencia inválida"
- Verifica que copiaste la clave completa sin espacios
- Comprueba que tu servidor tenga conexión a internet
- Revisa que la API esté accesible

### Error: "No se puede crear reserva"
- Verifica que los horarios estén configurados
- Comprueba que la capacidad máxima esté configurada
- Revisa que el email del restaurante esté configurado

### El formulario no aparece
- Verifica que el shortcode esté escrito correctamente: `[makia_reservas]`
- Comprueba que el plugin esté activado
- Revisa la consola del navegador para errores JavaScript

### Error de conexión a la API
- Verifica que tu servidor pueda hacer peticiones HTTP externas
- Comprueba que no haya firewall bloqueando la conexión
- Revisa los logs de PHP para más detalles

## Requisitos del Servidor

- **WordPress**: 5.0 o superior
- **PHP**: 7.4 o superior
- **MySQL**: 5.6 o superior
- **Conexión a Internet**: Requerida para activación de licencia
- **cURL**: Habilitado en PHP
- **allow_url_fopen**: Habilitado (opcional)

## Soporte

Para soporte técnico o consultas:
- Email: soporte@makia.io
- Web: https://makia.io/soporte
- Documentación: https://docs.makia.io

## Changelog

### 3.3.5 (2026-01-15) ⭐ ACTUALIZACIÓN DE SEGURIDAD
**🔒 Seguridad:**
- Implementado rate limiting (3 reservas cada 10 minutos)
- Validación mejorada de inputs (teléfono, email, nombres)
- Verificación de horarios de negocio en tiempo real
- Validación de capacidad total para evitar overbooking
- Sistema de logging para auditoría

**✨ Nuevas Funcionalidades:**
- Configuración de horas mínimas de antelación
- Configuración de meses máximos de antelación
- Verificación de emails desechables
- Mensajes de error mejorados con UX

**🐛 Correcciones:**
- Corregida discrepancia de versión
- Mejorada validación de capacidad
- Corregidos escapes de seguridad en templates

Ver [CHANGELOG.md](CHANGELOG.md) para detalles completos.

### 3.3.4 (2026-01-08)
- Actualizada URL de API a servidor actual
- Corregidos endpoints de tRPC
- Mejorada gestión de errores de conexión

### 3.1.0 (2026-01-08)
- Sistema de licencias completo
- Configuración de horarios semanales
- Control de capacidad máxima
- Shortcode de formulario de reservas
- Diseño brutalista monocromático
- Validación en tiempo real

## Licencia

Este plugin está licenciado bajo GPL v2 o posterior.

Copyright (C) 2026 MakIA Team

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
