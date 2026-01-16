# 📝 Guía Completa: Notas, Operarios y Auditoría - MakIA Reservas v4.2.0

## 🎯 Introducción

La versión 4.2.0 introduce tres sistemas fundamentales para la gestión profesional de reservas:

1. **📝 Notas Internas** - Comunicación privada del equipo
2. **👥 Operarios** - Gestión de personal con permisos específicos
3. **📊 Auditoría** - Trazabilidad completa de todas las acciones

---

## 📝 Sistema de Notas Internas

### ¿Qué son las Notas Internas?

Las notas internas son comentarios **privados** que el personal del restaurante puede añadir a cada reserva. **No son visibles para los clientes**.

### Características Principales

✅ **6 Tipos de Notas:**
- 📌 **General**: Notas generales sobre la reserva
- ⚠️ **Importante**: Información crítica que requiere atención
- 🔔 **Recordatorio**: Tareas o recordatorios pendientes
- 👤 **Info Cliente**: Datos adicionales del cliente
- 🍳 **Cocina**: Instrucciones para la cocina
- 🍽️ **Servicio**: Instrucciones para el servicio de sala

✅ **Funcionalidades:**
- Marcador de "Importante" con destacado visual
- Historial completo con autor y timestamp
- Eliminación permitida solo al autor o administrador
- Contador de notas por reserva
- Búsqueda y filtrado

### ¿Dónde se Ven las Notas?

**Ubicación:** Dentro del detalle de cada reserva en el panel de administración.

**Widget de Notas** incluye:
- Header con título "📝 Notas Internas" y contador
- Botón "➕ Añadir Nota"
- Lista de notas existentes
- Formulario inline para nueva nota

### Cómo Añadir una Nota

**Paso 1:** Abrir una reserva en el panel de administración

**Paso 2:** Buscar el widget "📝 Notas Internas"

**Paso 3:** Hacer clic en "➕ Añadir Nota"

**Paso 4:** Completar el formulario:
```
┌─────────────────────────────────────┐
│ Tipo de Nota:                       │
│ [📌 General ▼]                      │
│                                     │
│ Texto de la Nota:                   │
│ [________________________]          │
│ [________________________]          │
│                                     │
│ □ Marcar como importante            │
│                                     │
│ [💾 Guardar Nota] [✖️ Cancelar]    │
└─────────────────────────────────────┘
```

**Paso 5:** Hacer clic en "💾 Guardar Nota"

**Resultado:** La nota aparece inmediatamente en la lista con:
- Icono del tipo de nota
- Nombre del autor
- Badge "IMPORTANTE" si está marcada
- Fecha y hora de creación
- Botón 🗑️ para eliminar (solo autor/admin)

### Ejemplos de Uso

#### Ejemplo 1: Nota Importante sobre Alergias
```
Tipo: ⚠️ Importante
Texto: "Cliente tiene alergia SEVERA a frutos secos. 
       Avisar a cocina antes de preparar cualquier plato."
✓ Marcar como importante
```

#### Ejemplo 2: Recordatorio para el Equipo
```
Tipo: 🔔 Recordatorio
Texto: "Preparar mesa junto a la ventana. 
       El cliente lo solicitó en llamada telefónica."
```

#### Ejemplo 3: Instrucción para Cocina
```
Tipo: 🍳 Cocina
Texto: "Punto del cochinillo: extra crujiente.
       Cliente celebra aniversario, decorar plato especial."
```

### Buenas Prácticas

✅ **DO:**
- Ser específico y claro
- Marcar como importante solo lo crítico
- Añadir contexto relevante
- Usar el tipo de nota apropiado
- Mantener notas actualizadas

❌ **DON'T:**
- Poner información confidencial del cliente (DNI, tarjetas)
- Escribir en mayúsculas todo el tiempo
- Dejar notas ambiguas
- Marcar todo como importante
- Eliminar notas sin leer

---

## 👥 Sistema de Operarios

### ¿Qué es un Operario?

Un **Operario** es un miembro del personal con permisos específicos para:
- ✅ Ver todas las reservas
- ✅ Editar reservas
- ✅ Añadir y ver notas internas
- ✅ Ver el historial de auditoría
- ❌ NO puede cambiar configuración del plugin
- ❌ NO puede añadir/eliminar otros operarios

### ¿Por Qué Usar Operarios?

**Ventajas:**
1. **Seguridad**: Personal sin acceso a configuración sensible
2. **Responsabilidad**: Cada acción queda registrada con el autor
3. **Gestión**: Control de quién puede hacer qué
4. **Auditoría**: Trazabilidad de todas las operaciones
5. **Simplicidad**: Interface enfocada solo en reservas

**Casos de Uso:**
- Recepcionistas que gestionan llamadas
- Personal de sala que confirma reservas
- Managers que supervisan operaciones
- Staff temporal durante eventos especiales

### Acceder a Operarios

**Ruta:** `MakIA Reservas → Operarios`

**Panel incluye:**
- Botón "➕ Añadir Operario"
- Contador total de operarios
- Lista de operarios activos con:
  - Avatar (inicial del nombre)
  - Nombre completo
  - Email
  - Estadísticas (30 días):
    - Total de acciones
    - Reservas creadas
    - Notas añadidas
  - Badge "✓ ACTIVO"
  - Botón "🗑️ Eliminar"

### Cómo Crear un Operario

**Paso 1:** Ir a `MakIA Reservas → Operarios`

**Paso 2:** Hacer clic en "➕ Añadir Operario"

**Paso 3:** Completar el formulario:
```
┌─────────────────────────────────────────┐
│ Nuevo Operario                          │
│                                         │
│ Nombre *                                │
│ [____________]                          │
│                                         │
│ Apellidos *                             │
│ [____________]                          │
│                                         │
│ Email *                                 │
│ [________________________]              │
│                                         │
│ Se creará una cuenta con este email     │
│ y se enviará un correo con las          │
│ credenciales de acceso.                 │
│                                         │
│ [💾 Crear Operario] [✖️ Cancelar]      │
└─────────────────────────────────────────┘
```

**Paso 4:** Hacer clic en "💾 Crear Operario"

**Resultado:**
1. Se crea una cuenta de usuario en WordPress
2. El rol se asigna como "Operario MakIA"
3. Se genera una contraseña segura aleatoria
4. Se envía un email automático con:
   - Usuario de acceso
   - Contraseña
   - Link de acceso al panel
5. El operario aparece en la lista inmediatamente

### Qué Ve un Operario al Iniciar Sesión

Cuando un operario entra a WordPress ve:

**Barra lateral izquierda:**
```
🏠 Dashboard (limitado)
📅 MakIA Reservas
   └─ Ver Reservas
👤 Perfil (puede cambiar su contraseña)
```

**NO VE:**
- Plugins
- Temas
- Usuarios
- Configuración de WordPress
- Configuración de MakIA

**SÍ VE:**
- Todas las reservas
- Detalles completos de cada reserva
- Notas internas (puede añadir/ver/eliminar las suyas)
- Historial de auditoría

### Cómo Eliminar un Operario

**Paso 1:** Ir a `MakIA Reservas → Operarios`

**Paso 2:** Buscar el operario en la lista

**Paso 3:** Hacer clic en "🗑️ Eliminar"

**Paso 4:** Confirmar en el diálogo:
```
¿Estás seguro de eliminar este operario?

La cuenta de usuario permanecerá, pero 
perderá los permisos de operario.

[Cancelar] [Eliminar]
```

**Resultado:**
- El usuario se degrada a "Suscriptor"
- Ya no puede acceder a MakIA Reservas
- Su cuenta de WordPress permanece activa
- Puede iniciar sesión pero solo ve su perfil
- Todas sus acciones pasadas quedan en auditoría

### Estadísticas de Operarios

Cada operario muestra métricas de los últimos **30 días**:

**1. Total de Acciones**
```
Cuenta todas las operaciones registradas:
- Ver reservas
- Editar reservas
- Añadir notas
- Cambiar estados
- Etc.
```

**2. Reservas Creadas**
```
Número de nuevas reservas que el operario 
registró en el sistema en 30 días.
```

**3. Notas Añadidas**
```
Total de notas internas que el operario 
escribió en los últimos 30 días.
```

**Ejemplo Visual:**
```
┌────────────────────────────────────────┐
│  M  María García López                 │
│     📧 maria@restaurante.com           │
│                                        │
│  ────────────────────────────────────  │
│                                        │
│  Acciones (30 días)      📊 247        │
│  Reservas creadas        ✅ 45         │
│  Notas añadidas          📝 83         │
└────────────────────────────────────────┘
```

---

## 📊 Sistema de Auditoría

### ¿Qué es la Auditoría?

La auditoría es un **registro completo** de todas las acciones realizadas en el sistema de reservas.

**Cada acción registra:**
- ✅ Qué se hizo (tipo de acción)
- ✅ Quién lo hizo (usuario)
- ✅ Cuándo lo hizo (fecha y hora exacta)
- ✅ Desde dónde lo hizo (IP, navegador)
- ✅ Detalles adicionales (datos modificados)

### ¿Por Qué es Importante?

**Beneficios:**
1. **Trazabilidad**: Saber exactamente qué pasó con cada reserva
2. **Responsabilidad**: Identificar quién realizó cambios
3. **Resolución de problemas**: Encontrar errores y corregirlos
4. **Análisis**: Entender patrones de uso del sistema
5. **Seguridad**: Detectar accesos no autorizados
6. **Legal**: Evidencia de acciones en caso de disputas

### Acciones Registradas

**Reservas:**
- ✅ Reserva creada
- ✏️ Reserva actualizada
- 🗑️ Reserva eliminada
- 🔄 Estado cambiado
- 👁️ Reserva visualizada

**Notas:**
- 📝 Nota añadida
- 🗑️ Nota eliminada

**Operarios:**
- 👤 Operario creado
- 🗑️ Operario eliminado
- 🔄 Operario promovido

### Acceder a la Auditoría

**Existen 2 formas:**

#### 1. Auditoría General
**Ruta:** `MakIA Reservas → Auditoría`

Muestra:
- 📊 Dashboard con estadísticas generales
- 📋 Log de actividad reciente (50 últimas acciones)
- 👥 Usuarios más activos
- 📈 Métricas por tipo de acción

#### 2. Auditoría de una Reserva
**Ubicación:** Dentro del detalle de cada reserva

Muestra solo las acciones relacionadas con esa reserva específica.

### Panel de Auditoría General

**Sección 1: Estadísticas (30 días)**
```
┌──────────────────────────────────────────────────┐
│ Total de Acciones          Reservas Creadas      │
│     1,247                       156              │
│                                                  │
│ Notas Añadidas           Cambios de Estado      │
│     387                         89              │
└──────────────────────────────────────────────────┘
```

**Sección 2: Usuarios Más Activos**
```
┌─────────────────────────────────────────────────┐
│ Usuario              Email              Acciones │
│ María García         maria@...             247  │
│ Juan Pérez           juan@...              189  │
│ Admin                admin@...             156  │
└─────────────────────────────────────────────────┘
```

**Sección 3: Actividad Reciente**
```
┌─────────────────────────────────────────────────────────┐
│ Fecha/Hora         Acción              Usuario    IP     │
│ 16/01/26 15:30    ✅ Reserva creada     María    192... │
│ 16/01/26 15:25    📝 Nota añadida      Juan     192... │
│ 16/01/26 15:20    🔄 Estado cambiado    María    192... │
└─────────────────────────────────────────────────────────┘
```

### Widget de Auditoría por Reserva

**Ubicación:** Dentro del detalle de una reserva

**Formato:** Timeline vertical con:
- Línea continua vertical
- Puntos de color por cada acción
- Información completa de cada evento

**Ejemplo Visual:**
```
📊 Historial de Auditoría [3]

│
● ✅ Reserva creada
│ 👤 María García
│ 📅 16/01/2026 14:30
│ 🌐 192.168.1.100
│
● 📝 Nota añadida
│ 👤 Juan Pérez
│ 📅 16/01/2026 14:45
│ 🌐 192.168.1.105
│ 📋 Tipo: Importante
│
● 🔄 Estado cambiado
│ 👤 María García
│ 📅 16/01/2026 15:00
│ 🌐 192.168.1.100
│ Estado: Pendiente → Confirmada
```

### Códigos de Color

Cada tipo de acción tiene su color identificativo:

- **Verde** (#46b450): Creaciones (reservas, notas, operarios)
- **Azul** (#2271b1): Actualizaciones y ediciones
- **Rojo** (#d63638): Eliminaciones
- **Amarillo** (#dba617): Cambios de estado
- **Púrpura** (#667eea): Notas y comentarios
- **Gris** (#999): Visualizaciones

### Interpretar la Auditoría

#### Ejemplo 1: Detectar Cambio No Autorizado
```
Problema: Una reserva cambió de 4 a 2 personas

Solución:
1. Abrir detalle de la reserva
2. Ver widget "Historial de Auditoría"
3. Buscar acción "✏️ Reserva actualizada"
4. Ver quién lo hizo y cuándo
5. Verificar si fue autorizado
```

#### Ejemplo 2: Ver Quién Añadió una Nota
```
Situación: Hay una nota importante sobre alergias

Solución:
1. Ver la nota en el widget
2. Aparece el autor: "Juan Pérez"
3. Fecha y hora de creación
4. Ver en auditoría si hubo más cambios
```

#### Ejemplo 3: Analizar Productividad
```
Objetivo: Ver rendimiento de operarios

Solución:
1. Ir a MakIA Reservas → Auditoría
2. Ver sección "Usuarios Más Activos"
3. Comparar números de acciones
4. Identificar quién está más involucrado
```

---

## 🔐 Permisos y Seguridad

### Matriz de Permisos

| Acción                    | Admin | Operario | Otros |
|---------------------------|-------|----------|-------|
| Ver reservas              | ✅    | ✅       | ❌    |
| Crear reservas            | ✅    | ✅       | ❌    |
| Editar reservas           | ✅    | ✅       | ❌    |
| Eliminar reservas         | ✅    | ✅       | ❌    |
| Añadir notas              | ✅    | ✅       | ❌    |
| Ver notas                 | ✅    | ✅       | ❌    |
| Eliminar notas propias    | ✅    | ✅       | ❌    |
| Eliminar notas de otros   | ✅    | ❌       | ❌    |
| Ver auditoría             | ✅    | ✅       | ❌    |
| Gestionar operarios       | ✅    | ❌       | ❌    |
| Configurar plugin         | ✅    | ❌       | ❌    |

### Capacidades de WordPress

El sistema usa el sistema de capacidades nativo de WordPress:

```php
// Nuevas capacidades personalizadas
'makia_manage_bookings'  // Gestionar reservas
'makia_view_bookings'    // Ver reservas
'makia_edit_bookings'    // Editar reservas
'makia_add_notes'        // Añadir notas
'makia_view_notes'       // Ver notas
'makia_view_audit'       // Ver auditoría
```

### Verificaciones de Seguridad

**Todas las peticiones AJAX incluyen:**
1. ✅ Verificación de nonce (protección CSRF)
2. ✅ Verificación de capacidades del usuario
3. ✅ Sanitización de inputs
4. ✅ Escape de outputs
5. ✅ Prepared statements (prevención SQL injection)

**Ejemplo en código:**
```php
// Verificar nonce
check_ajax_referer('makia_admin_nonce', 'nonce');

// Verificar permisos
if (!current_user_can('makia_manage_bookings')) {
    wp_send_json_error('No tienes permisos');
}

// Sanitizar inputs
$note_text = sanitize_textarea_field($_POST['note_text']);

// Escape outputs
echo esc_html($note_text);
```

---

## 📱 Interfaz y Experiencia de Usuario

### Diseño Responsive

**Todos los componentes se adaptan a:**
- 💻 Desktop (1920px+)
- 💻 Laptop (1366px)
- 📱 Tablet (768px)
- 📱 Mobile (375px-480px)

**Optimizaciones móviles:**
- Cards se apilan verticalmente
- Formularios de ancho completo
- Botones más grandes (mínimo 40px)
- Fuentes escalables
- Inputs táctil-friendly

### Animaciones

**Transiciones suaves (0.3s) en:**
- Hover de botones
- Aparición de formularios (slideDown/slideUp)
- Eliminación de elementos (fadeOut)
- Notificaciones toast (slideInRight/fadeOut)
- Hover de cards (translateY)

**Sin animaciones molestas:**
- No hay spinners innecesarios
- No hay rebotes exagerados
- Respeta `prefers-reduced-motion`

### Accesibilidad

✅ **Cumple con WCAG 2.1 Nivel AA:**
- Contraste suficiente en textos (4.5:1)
- Tamaños mínimos de tap (40px)
- Navegación por teclado funcional
- Focus visible en todos los elementos
- Aria labels apropiados
- Feedback visual claro

---

## 🚀 Casos de Uso Reales

### Caso 1: Restaurante Pequeño (1-2 personas)

**Situación:** 
Propietario gestiona todo, necesita recordar detalles de clientes VIP.

**Solución:**
- Crear notas tipo "👤 Info Cliente" para cada regular
- Usar notas tipo "🔔 Recordatorio" para preparativos especiales
- No necesita operarios adicionales
- Revisar auditoría ocasionalmente

### Caso 2: Restaurante Mediano (3-5 personas)

**Situación:**
Manager + 2-3 recepcionistas atienden llamadas y gestionan reservas.

**Solución:**
- Manager es admin, controla todo
- Crear 2-3 operarios para recepcionistas
- Usar notas tipo "🍳 Cocina" para instrucciones especiales
- Usar notas tipo "⚠️ Importante" para alergias
- Revisar auditoría semanalmente
- Ver estadísticas de operarios mensualmente

### Caso 3: Restaurante Grande (6+ personas)

**Situación:**
Equipo grande con roles específicos: recepción, sala, cocina, manager.

**Solución:**
- Manager es admin principal
- Crear 5-6 operarios:
  - 2 recepcionistas (turnos mañana/tarde)
  - 2 jefes de sala
  - 1 jefe de cocina
  - 1 manager asistente
- Sistema extensivo de notas:
  - Recepción usa "📌 General" y "👤 Info Cliente"
  - Sala usa "🍽️ Servicio" y "🔔 Recordatorio"
  - Cocina usa "🍳 Cocina" y "⚠️ Importante"
- Auditoría diaria para detectar problemas
- Análisis mensual de productividad por operario

### Caso 4: Evento Especial

**Situación:**
Banquete de empresa con 100 personas, muchos detalles especiales.

**Solución:**
- Crear operario temporal para el coordinador del evento
- Notas extensivas:
  - "⚠️ Importante": Restricciones dietéticas
  - "🍳 Cocina": Timing de cada plato
  - "🍽️ Servicio": Distribución de mesas
  - "🔔 Recordatorio": Preparativos previos
- Auditoría post-evento para análisis
- Eliminar operario temporal después

---

## 🔧 Solución de Problemas

### Problema: No veo el widget de Notas

**Causas posibles:**
- No tienes permisos de operario o admin
- El widget no está integrado en tu versión
- Error de JavaScript

**Solución:**
1. Verificar que eres admin u operario
2. Recargar página con Ctrl+F5
3. Revisar consola del navegador (F12)
4. Contactar soporte

### Problema: No puedo crear Operarios

**Causas posibles:**
- No eres administrador
- Email ya existe en el sistema
- Campos obligatorios vacíos

**Solución:**
1. Verificar que eres admin (no operario)
2. Usar email único no registrado
3. Completar todos los campos marcados con *
4. Revisar que el email sea válido

### Problema: El Operario no recibió el email

**Causas posibles:**
- Email en spam
- Servidor SMTP mal configurado
- Email incorrecto

**Solución:**
1. Revisar carpeta de spam
2. Verificar configuración SMTP de WordPress
3. Usar plugin como WP Mail SMTP
4. Enviar credenciales manualmente:
   - Usuario: aparece en lista de operarios
   - Resetear contraseña desde WordPress

### Problema: La auditoría no registra acciones

**Causas posibles:**
- Tabla de auditoría no creada
- Error en base de datos
- Permisos de DB insuficientes

**Solución:**
1. Desactivar y reactivar el plugin
2. Verificar que la tabla `wp_makia_audit_log` existe
3. Revisar permisos de MySQL
4. Contactar soporte con logs

### Problema: Las notas no se guardan

**Causas posibles:**
- Campo de texto vacío
- Tabla de notas no creada
- Error de AJAX

**Solución:**
1. Escribir texto en la nota (no dejar vacío)
2. Verificar que la tabla `wp_makia_notes` existe
3. Revisar consola del navegador (F12)
4. Probar en otro navegador
5. Desactivar otros plugins temporalmente

---

## 📚 Referencias Rápidas

### Atajos de Teclado

(Cuando estén implementados)

- `Alt + N`: Añadir nueva nota
- `Alt + O`: Abrir panel de operarios
- `Alt + A`: Abrir auditoría
- `Esc`: Cerrar modales

### Iconos y Significados

**Notas:**
- 📌 = General
- ⚠️ = Importante
- 🔔 = Recordatorio
- 👤 = Info Cliente
- 🍳 = Cocina
- 🍽️ = Servicio

**Auditoría:**
- ✅ = Creado
- ✏️ = Editado
- 🗑️ = Eliminado
- 🔄 = Estado cambiado
- 📝 = Nota añadida
- 👁️ = Visualizado

**Estados:**
- ✓ ACTIVO = Operario activo
- ✓ IMPORTANTE = Nota marcada como importante
- PENDIENTE = Reserva pendiente de confirmar
- CONFIRMADA = Reserva confirmada
- CANCELADA = Reserva cancelada

---

## 💡 Consejos y Mejores Prácticas

### Para Administradores

✅ **Hacer:**
- Revisar auditoría semanalmente
- Crear operarios solo para personal de confianza
- Establecer protocolo de uso de notas
- Capacitar al equipo en el sistema
- Hacer backup regular de la base de datos

❌ **Evitar:**
- Compartir credenciales de admin
- Dejar operarios inactivos sin eliminar
- Ignorar la auditoría
- No capacitar al personal
- No establecer reglas claras

### Para Operarios

✅ **Hacer:**
- Usar el tipo de nota correcto
- Ser específico en las notas
- Marcar como importante solo lo crítico
- Leer notas antes de atender reserva
- Mantener notas actualizadas

❌ **Evitar:**
- Poner información personal sensible
- Escribir notas ambiguas
- Marcar todo como importante
- Eliminar notas sin leer
- No comunicar con el equipo

### Para el Equipo Completo

✅ **Protocolo recomendado:**

**1. Al recibir una reserva:**
- Crear la reserva en el sistema
- Añadir nota tipo "📌 General" con contexto
- Si hay alergias: nota "⚠️ Importante"
- Si hay peticiones: nota correspondiente

**2. Al confirmar una reserva:**
- Cambiar estado a "Confirmada"
- Añadir nota con detalles de la confirmación
- Si hay cambios: añadir nota con los cambios

**3. Antes del servicio:**
- Revisar todas las notas de la reserva
- Preparar lo necesario según notas de cocina
- Comunicar al equipo notas de servicio

**4. Después del servicio:**
- Añadir nota con feedback o incidencias
- Actualizar info del cliente si es VIP
- Marcar reserva como completada

---

## 📞 Soporte y Recursos

### Documentación Adicional

- `README.md` - Información general del plugin
- `CHANGELOG.md` - Historial de cambios
- `DESIGN_IMPROVEMENTS.md` - Detalles técnicos de diseño
- `INSTALLATION_GUIDE.md` - Guía de instalación

### Contacto

- 📧 Email: soporte@contacpro.app
- 🌐 Web: https://contacpro.app
- 📚 Docs: https://docs.contacpro.app
- 💬 Chat: Disponible en el panel de admin

### Reportar Problemas

Al reportar un problema, incluir:
1. Versión de WordPress
2. Versión de PHP
3. Versión del plugin MakIA
4. Descripción del problema
5. Pasos para reproducirlo
6. Screenshots si aplica
7. Mensajes de error

---

**¡Disfruta de las nuevas funcionalidades de MakIA Reservas v4.2.0!** 🎉

**Equipo MakIA Development**  
**Enero 2026**
