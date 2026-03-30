# Changelog - MakIA Restaurante

Todos los cambios notables de este proyecto serán documentados en este archivo.

---

## [4.2.0] - 2026-01-16

### 🎯 ACTUALIZACIÓN MAYOR - Sistema de Notas, Operarios y Auditoría

#### ✨ Añadido

**Sistema de Notas Internas**
- ✅ **Notas privadas** en cada reserva (no visibles para clientes)
- ✅ **6 tipos de notas**: General, Importante, Recordatorio, Info Cliente, Cocina, Servicio
- ✅ **Historial completo** de notas con timestamp y autor
- ✅ **Marcador de importancia** con destacado visual
- ✅ **Eliminación de notas** (solo autor o admin)
- ✅ **Contador de notas** por reserva
- ✅ **Widget interactivo** con animaciones
- ✅ **Formulario inline** para añadir notas rápidamente
- ✅ **Iconos personalizados** por tipo de nota (📌⚠️🔔👤🍳🍽️)
- ✅ **Diseño responsive** optimizado para móvil

**Sistema de Operarios**
- ✅ **Rol personalizado** "Operario MakIA" en WordPress
- ✅ **Permisos específicos**: gestionar reservas, añadir notas, ver auditoría
- ✅ **Crear operarios** desde email (auto-genera cuenta)
- ✅ **Promover usuarios** existentes a operarios
- ✅ **Degradar operarios** a suscriptor
- ✅ **Panel de gestión** de operarios con estadísticas
- ✅ **Estadísticas por operario** (30 días):
  - Total de acciones
  - Reservas creadas
  - Reservas actualizadas
  - Notas añadidas
- ✅ **Cards visuales** con avatar inicial
- ✅ **Badge "OPERARIO"** en lista de usuarios de WordPress
- ✅ **Email automático** con credenciales al crear operario

**Sistema de Auditoría Completa**
- ✅ **Log de todas las acciones** en el sistema
- ✅ **Registro automático** de:
  - Reservas creadas, actualizadas, eliminadas
  - Cambios de estado
  - Notas añadidas y eliminadas
  - Visualizaciones de reservas
  - Operarios creados y eliminados
- ✅ **Metadatos completos**:
  - Usuario que realizó la acción
  - Fecha y hora exacta
  - Dirección IP
  - User Agent (navegador)
  - Datos adicionales de la acción
- ✅ **Widget de auditoría** por reserva con timeline visual
- ✅ **Página de auditoría general** con estadísticas
- ✅ **Filtros avanzados**: por tipo, usuario, fecha
- ✅ **Estadísticas del sistema** (30 días):
  - Total de acciones
  - Acciones por tipo
  - Usuarios más activos
  - Actividad por día
- ✅ **Timeline visual** con líneas y puntos de color
- ✅ **Códigos de color** por tipo de acción
- ✅ **Gráficos y métricas** en dashboard de auditoría

#### 🎨 Nuevas Páginas en Admin

**MakIA Restaurante → Operarios**
- Panel completo de gestión de operarios
- Formulario para crear nuevos operarios
- Lista de operarios con estadísticas en tiempo real
- Acciones: Añadir, Eliminar, Ver detalles

**MakIA Restaurante → Auditoría**
- Dashboard con estadísticas generales
- Lista de acciones recientes (50 últimas)
- Tabla de usuarios más activos
- Métricas visuales con tarjetas de colores
- Filtros y búsqueda avanzada

#### 🔧 Mejoras Técnicas

**Base de Datos**
- Nueva tabla: `wp_makia_notes`
  - Campos: id, booking_id, user_id, note_text, note_type, is_important, created_at
  - Índices optimizados para búsquedas rápidas
  
- Nueva tabla: `wp_makia_audit_log`
  - Campos: id, action_type, entity_type, entity_id, user_id, user_name, user_email, ip_address, user_agent, action_data, created_at
  - Índices múltiples para filtros complejos

**Clases PHP**
- `class-makia-notes.php`: Gestión completa de notas
- `class-makia-audit.php`: Sistema de auditoría
- `class-makia-operators.php`: Gestión de operarios

**JavaScript**
- Sistema de notificaciones toast mejorado
- AJAX para todas las operaciones CRUD
- Actualización en tiempo real de contadores
- Animaciones de entrada/salida suaves
- Validación de formularios cliente-side

**Seguridad**
- ✅ Verificación de nonce en todas las peticiones AJAX
- ✅ Verificación de capacidades (capabilities)
- ✅ Sanitización de todos los inputs
- ✅ Escape de todos los outputs
- ✅ Registro de IP y User Agent en auditoría
- ✅ Prevención de SQL injection con prepared statements
- ✅ Prevención de XSS con esc_html/esc_attr

#### 🎯 Capacidades de WordPress

**Nuevas Capacidades Personalizadas**
- `makia_manage_bookings`: Gestionar reservas
- `makia_view_bookings`: Ver reservas
- `makia_edit_bookings`: Editar reservas
- `makia_add_notes`: Añadir notas
- `makia_view_notes`: Ver notas
- `makia_view_audit`: Ver auditoría

**Asignación de Capacidades**
- **Administrador**: Todas las capacidades + configuración
- **Operario MakIA**: Solo capacidades de gestión (sin configuración)
- **Otros roles**: Sin acceso al sistema MakIA

#### 📊 Interfaz de Usuario

**Widget de Notas** (en detalle de reserva)
- Header con título y contador
- Botón "Añadir Nota" destacado
- Formulario inline expandible
- Selector de tipo de nota con iconos
- Textarea con placeholder
- Checkbox "Marcar como importante"
- Botones Guardar/Cancelar
- Lista de notas con:
  - Icono por tipo
  - Autor y badge "IMPORTANTE" si aplica
  - Texto de la nota
  - Fecha y hora
  - Botón eliminar (solo autor/admin)
- Animaciones de hover y transiciones
- Mensaje "No hay notas" cuando está vacía

**Widget de Auditoría** (en detalle de reserva)
- Timeline vertical con líneas
- Puntos de color por tipo de acción
- Iconos descriptivos (✅✏️🗑️🔄📝👁️)
- Información completa de cada acción
- Usuario, fecha, hora, IP
- Detalles adicionales en panel expandible
- Codificación por colores
- Responsive design

**Página de Operarios**
- Cards con gradientes de color
- Avatar con inicial del nombre
- Estadísticas visuales
- Badge "ACTIVO"
- Botón "Eliminar" con confirmación
- Formulario modal para nuevo operario
- Grid responsive (se adapta a pantalla)
- Efectos hover elegantes

**Página de Auditoría**
- 4 tarjetas de métricas principales
- Tabla de usuarios más activos
- Log de actividad reciente
- Colores diferentes por tipo de acción
- Layout responsive en grid
- Iconos grandes en cards vacías

#### 🔔 Notificaciones

**Sistema Toast Mejorado**
- Notificaciones flotantes en esquina superior derecha
- Colores según tipo (success/error)
- Auto-cierre a los 3 segundos (notas) o 5 segundos (operarios)
- Animación de entrada/salida
- Soporte para textos largos
- Z-index alto (999999) para estar siempre visible

#### 📚 Documentación

- Documentación inline completa en todas las clases
- PHPDoc en todos los métodos públicos
- Comentarios explicativos en lógica compleja
- Ejemplos de uso en docblocks

---

## [4.1.0] - 2026-01-16

### 🎨 ACTUALIZACIÓN COMPLETA - Sistema de Diseño Avanzado

#### ✨ Añadido

**Panel de Administración Rediseñado (3 Pestañas)**
- ✅ **Pestaña Plantillas**: Selector visual de 6 plantillas profesionales
  - Tarjetas mejoradas con miniaturas visuales de cada diseño
  - Badge animado "ACTIVA" en plantilla seleccionada
  - Indicadores de paleta de colores para cada plantilla
  - Hover effects con elevación y cambio de borde
  - Descripciones detalladas y estilo de cada plantilla
  
- ✅ **Pestaña Personalización**: Opciones avanzadas de diseño
  - 🎨 Color Principal (botones, enlaces, elementos destacados)
  - 🎨 Color Secundario (fondos y elementos secundarios)
  - 🎨 Color de Texto (texto principal)
  - 🖼️ Logo del restaurante (con URL y vista previa)
  - ✏️ Título del formulario personalizable
  - ✏️ Subtítulo personalizable
  - 🔘 Texto del botón de envío personalizable
  - Sincronización color-picker ↔ input hexadecimal
  - Botón de guardado con gradiente animado
  
- ✅ **Pestaña Vista Previa**: Preview en tiempo real
  - Vista previa interactiva del formulario completo
  - Aplicación automática de plantilla activa
  - Actualización instantánea de colores personalizados
  - Muestra logo si está configurado
  - Textos personalizados reflejados en tiempo real
  - Escala optimizada (85%) para mejor visualización
  - Fondo neutral (#f5f5f5) para resaltar diseño

**Integración Completa de Plantillas en Frontend**
- ✅ Modal del formulario usa clase de plantilla activa
- ✅ CSS dinámico inyectado basado en personalizaciones
- ✅ Logo mostrado automáticamente si está configurado
- ✅ Textos personalizados aplicados dinámicamente
- ✅ Colores custom sobrescriben estilos de plantilla
- ✅ Soporte para `!important` en personalizaciones

**Animaciones y Micro-interacciones Mejoradas**
- ✅ **Modal**: fadeIn (0.3s) con ease timing
- ✅ **Contenedor**: slideInUp (0.4s) con cubic-bezier bounce
- ✅ **Campos**: aparición escalonada con delays (0.1s-0.35s)
- ✅ **Inputs focus**: elevación sutil (-2px) con transition
- ✅ **Botón submit**: 
  - Efecto ripple circular con pseudo-elemento ::before
  - Elevación en hover (-3px) con sombra ampliada
  - Animación pulse al hacer clic
  - Loading spinner integrado con rotación infinita
- ✅ **Botón cerrar**: 
  - Rotación 90° en hover
  - Escala 1.1 en hover
  - Background rgba con transición
- ✅ **Placeholders**: transición de opacidad al enfocar
- ✅ **Validación**: 
  - Bordes verdes para campos válidos
  - Animación shake para errores
  - Bordes rojos para inválidos

**Sistema de Notificaciones Toast**
- ✅ Notificaciones flotantes en esquina superior derecha
- ✅ Colores según tipo (success: verde, error: rojo)
- ✅ Animación slideInRight de entrada
- ✅ Auto-cierre después de 3 segundos
- ✅ FadeOut suave al cerrar

**Mejoras Responsive Ultra-Optimizadas**
- ✅ **Tablet (< 768px)**:
  - Modal con márgenes 10px
  - Altura máxima calc(100vh - 20px)
  - Padding reducido a 25px → 20px
  - Logo reducido: 150px x 60px máximo
- ✅ **Mobile (< 480px)**:
  - Filas flex-direction: column
  - Campos width: 100%
  - Padding ultra-reducido: 20px → 15px
  - Espaciado entre campos optimizado
  - Logo responsive automático

**Efectos Especiales por Plantilla**
- ✅ **Modern**: Glassmorphism con backdrop-filter blur
- ✅ **Elegant**: Línea dorada superior con gradiente
- ✅ **Vibrant**: Animación de gradiente shifting (15s loop)
- ✅ **Dark**: Efecto neón en focus y hover con glow
- ✅ **Rustic**: Textura de fondo con SVG pattern
- ✅ **Corporate**: Línea izquierda con gradiente vertical

**Accesibilidad y UX**
- ✅ Outlines visibles (2px) en todos los focus states
- ✅ Contraste mejorado en todos los textos
- ✅ Tamaños táctiles adecuados (40px mínimo)
- ✅ Navegación por teclado completamente funcional
- ✅ Aria labels apropiados
- ✅ Soporte para `prefers-reduced-motion`
- ✅ Tooltips accesibles con data-tooltip

**Optimizaciones Técnicas**
- ✅ CSS solo carga en páginas con shortcode
- ✅ GPU acceleration con transform
- ✅ Sanitización completa de inputs
- ✅ Verificación de nonce en AJAX
- ✅ Escape de outputs (prevención XSS)
- ✅ Options API de WordPress
- ✅ Error handling robusto

#### 🔧 Modificado
- 📝 `class-makia-design.php`: Completamente rediseñado
  - Nuevos métodos: `get_customizations()`, `save_customizations()`
  - Handler AJAX: `save_customizations_ajax()`
  - Método `render_design_tab()` con 3 secciones
  - JavaScript mejorado con gestión de estado
  
- 📝 `booking-form.php`: Integración total de templates
  - Carga dinámica de plantilla activa
  - Obtención de customizaciones
  - Inyección de CSS personalizado inline
  - Aplicación de logo, títulos y botón custom
  - Animaciones CSS integradas
  
- 📝 `makia-booking-templates.css`: Mejoras extensivas
  - +200 líneas de CSS adicional
  - Keyframes para 10+ animaciones
  - Efectos especiales por plantilla
  - Responsive mejorado
  - Estados de validación
  - Tooltips y skeletons

#### 📚 Documentación
- ✅ Nuevo archivo: `DESIGN_IMPROVEMENTS.md`
  - Documentación completa de todas las mejoras
  - Guías de uso paso a paso
  - Listado de animaciones
  - Paletas de color por plantilla
  - Mejoras de accesibilidad
  - Optimizaciones de rendimiento
  - Compatibilidad y próximos pasos

#### 🎯 Resultado
El usuario ahora puede:
1. ✅ Elegir entre 6 plantillas profesionales con preview
2. ✅ Personalizar 3 colores principales del formulario
3. ✅ Añadir logo de su restaurante con preview
4. ✅ Personalizar todos los textos del formulario
5. ✅ Ver preview en tiempo real de todos los cambios
6. ✅ Disfrutar de 15+ animaciones suaves y profesionales
7. ✅ Experiencia mobile completamente optimizada
8. ✅ Formularios ultra-responsive automáticamente

---

## [4.0.0] - 2026-01-16

### 🎨 ACTUALIZACIÓN MAYOR - Diseño y UX

#### ✨ Añadido
- **Vista Previa en Vivo**: Panel interactivo en el admin que muestra el formulario en tiempo real
  - Modo Desktop/Móvil con toggle visual
  - Actualización instantánea al cambiar opciones
  - Preview responsive adaptativo
  
- **6 Plantillas Profesionales Mejoradas**:
  - ✨ Moderno Minimalista - Diseño limpio y espacioso
  - 👑 Elegante Clásico - Estilo refinado con detalles dorados
  - 🎨 Colorido Vibrante - Gradientes modernos y dinámicos
  - 🌙 Oscuro Premium - Experiencia premium exclusiva
  - 🌿 Rústico Acogedor - Tonos cálidos y naturales
  - 💼 Profesional Corporativo - Confiable y estructurado
  
- **Personalización Avanzada**:
  - 📸 Subida de logo con integración de Media Library de WordPress
  - ✏️ Título y subtítulo personalizables
  - 🎨 Selector de color primario (botones, enlaces, focus)
  - 🖌️ Selector de color secundario (fondos)
  - 📝 Selector de color de texto
  - 🔘 Texto personalizable del botón de envío
  - 👁️ Control de visibilidad del logo
  
- **Animaciones y Micro-interacciones Premium**:
  - Animación de entrada suave del modal (cubic-bezier easing)
  - Efectos hover elegantes con elevación en inputs
  - Animación de ondas expansivas en botones
  - Efectos de brillo y deslizamiento en hover
  - Transiciones suaves y fluidas (300ms optimizado)
  - Loading spinner con animación circular
  - Shake animation para validación de errores
  - Slide-in animations para mensajes de éxito/error
  - Pulse animation para badge de plantilla activa
  
- **Logo Dinámico**: Soporte completo para logo del restaurante
  - Posicionamiento centrado en todas las plantillas
  - Tamaños optimizados por plantilla (120px-140px)
  - Filtros de imagen según estilo (sepia, brightness)
  - Responsive en dispositivos móviles

#### 🚀 Mejorado
- **CSS de Plantillas Optimizado**:
  - Animaciones GPU-accelerated para máxima suavidad
  - Hover states en inputs con transform
  - Focus states más prominentes con box-shadow multi-capa
  - Efectos ::before y ::after para animaciones complejas
  - Margins optimizados para uso en modal (0 auto)
  - Bordes adaptativos (lateral → superior en móvil)
  
- **Responsive Design Profesional**:
  - 4 breakpoints optimizados (1200px, 768px, 600px, 400px)
  - Títulos que escalan según dispositivo
  - Font-size 16px en móvil (previene auto-zoom iOS)
  - Padding adaptativo por tamaño de pantalla
  - Grid template automático con minmax()
  - Border-radius eliminado en móvil para aprovechar espacio
  
- **Accesibilidad (WCAG 2.1)**:
  - Focus-visible mejorado para navegación por teclado
  - Outline offset de 2px para claridad
  - Soporte completo para `prefers-reduced-motion`
  - Contraste AAA en todos los estados
  - ARIA labels en botones y controles
  
- **Panel de Administración Rediseñado**:
  - Interfaz moderna con card-based layout
  - Animaciones staggered para cards (delay incremental)
  - Hover effects con transform y box-shadow
  - Grid responsivo (auto-fit, minmax 320px)
  - Badge animado para plantilla activa
  - Paleta de colores interactiva con data attributes
  - Tooltips hover en swatches de color
  - Notificaciones toast con slide-in
  - Loading overlay con backdrop-filter blur
  - Gradientes sutiles en backgrounds

#### 📦 Archivos Nuevos
```
assets/css/makia-admin-design.css (650 líneas)
assets/js/makia-admin-design.js (550 líneas)
includes/class-makia-design.php (completamente reescrito, 450 líneas)
```

#### 📝 Archivos Modificados
```
assets/css/makia-booking-templates.css (+400 líneas de mejoras)
templates/booking-form.php (integración dinámica de plantillas)
makia-reservas.php (versión 4.0.0)
```

### 🔧 Técnico

#### Añadido
- AJAX endpoint `makia_save_customization` para guardar personalización
- AJAX endpoint `makia_upload_logo` con validación de tipos
- AJAX endpoint `makia_get_preview` para vista previa en tiempo real
- WordPress Media Uploader integration (wp.media API)
- Inline styles dinámicos inyectados vía `wp_add_inline_style()`
- Script localization con `wp_localize_script()` para datos del admin
- Debounce function (lodash-style) para optimizar updates
- Custom CSS injection para colores personalizados en template activo

#### Optimizado
- Carga condicional de estilos solo en páginas con shortcode
- Enqueue de assets del admin solo en páginas relevantes
- Verificación de hook name en admin_enqueue_scripts
- Menor uso de JavaScript inline (migrado a archivos externos)
- Nonces de seguridad en todas las llamadas AJAX
- Sanitización y validación de inputs (sanitize_hex_color, esc_url, etc.)

### ⚡ Performance

#### Optimizado
- Animaciones con `transform` y `opacity` (GPU-accelerated)
- `will-change` implícito vía transforms
- Cubic-bezier timing functions optimizadas (0.4, 0, 0.2, 1)
- CSS animations en lugar de JavaScript para suavidad
- Debounced input handlers (500ms) para reducir AJAX calls
- Transiciones hardware-accelerated en todos los elementos críticos
- Minimal repaints con transform en lugar de top/left
- Uso de `::before`/`::after` para efectos sin elementos DOM extra

### 📱 UX/UI

#### Mejorado
- Feedback visual inmediato en todas las interacciones (<100ms)
- Estados de loading claramente comunicados
- Mensajes contextuales de error y éxito
- Transiciones entre estados fluidas y naturales
- Jerarquía visual clara con tamaños y colores
- Icons emoji para rápida identificación
- Color branding consistente (#667eea principal, #764ba2 secundario)
- Toast notifications en esquina superior derecha
- Cards con elevation en hover (Material Design inspired)
- Smooth scrolling en selección de plantilla

### 🐛 Fixes
- ✅ Corregido: Duplicación de estilos en plantilla corporate
- ✅ Corregido: Márgenes en plantillas para modal (40px auto → 0 auto)
- ✅ Corregido: Z-index conflicts en modal overlay (999999)
- ✅ Corregido: Template wrapper closing tags para vibrant
- ✅ Corregido: Box-shadow aplicación inconsistente
- ✅ Mejorado: Estados activos en cards del admin
- ✅ Corregido: Border-radius en móvil (0 para maximizar espacio)

### 🌐 Compatibilidad
- ✅ WordPress 5.0+ (tested up to 6.4)
- ✅ PHP 7.4+ (optimizado para PHP 8.x)
- ✅ Chrome/Edge (últimas 3 versiones)
- ✅ Firefox (últimas 3 versiones)
- ✅ Safari 13+ / iOS Safari 13+
- ⚠️ IE11 (soporte básico, sin animaciones avanzadas)
- ✅ Mobile: iOS 13+, Android 8+

### 📚 Notas de Actualización
- Las plantillas anteriores se mantienen, pero con mejoras visuales
- La plantilla "modern" sigue siendo la predeterminada
- Todas las reservas existentes funcionan sin cambios
- Personalización opcional (valores por defecto si no se configura)
- Compatible con versiones anteriores del shortcode

---

## [3.3.6] - 2026-01-15

### ✨ Sistema de Planes y Límites

- **NUEVO:** Sistema completo de gestión de planes con límites mensuales
  - Plan **Chupito**: 5 reservas/mes (Gratis)
  - Plan **Caña**: 15 reservas/mes (5€/mes sin IVA)
  - Plan **MasCaña**: 100 reservas/mes (15€/mes sin IVA)

- **NUEVO:** Dashboard visual de uso del plan
  - Barra de progreso de uso
  - Estadísticas en tiempo real (usadas/disponibles/días hasta reset)
  - Alertas inteligentes según nivel de uso
  - Comparación visual de todos los planes

- **NUEVO:** Control automático de límites
  - Verificación antes de crear reserva
  - Bloqueo automático al alcanzar el límite
  - Mensajes informativos con planes de upgrade
  - Contador mensual con reset automático

- **NUEVO:** Logging de eventos del plan
  - Incremento de contador de reservas
  - Advertencias al acercarse al límite (80%)
  - Límite alcanzado
  - Reset mensual del contador

### 🔧 Mejoras Técnicas

- Método `can_create_booking()` - Verifica límite antes de aceptar reserva
- Método `increment_booking_count()` - Incrementa contador automáticamente
- Método `get_usage_stats()` - Estadísticas detalladas de uso
- Método `get_available_plans()` - Lista todos los planes disponibles
- Método `reset_monthly_counter()` - Reset automático el 1º de cada mes
- Cron job mensual `makia_monthly_reset` programado automáticamente

### 💎 Interfaz de Usuario

- **Página de Licencias Rediseñada:**
  - Diseño moderno con grid responsive
  - Iconos y emojis para mejor UX
  - Colores contextuales (verde/amarillo/rojo) según uso
  - Cards comparativas de planes con precio y características
  - Botones de upgrade con links a Contacpro
  - Panel lateral con información y recomendaciones

### 📊 Opciones de BD

Nuevas opciones en `wp_options`:
- `makia_license_plan` - Plan actual del usuario (chupito/cana/mascana)
- `makia_monthly_bookings_count` - Contador de reservas del mes actual
- `makia_monthly_reset_date` - Fecha del último reset mensual

### 🔄 Integración con API

- La API de Contacpro ahora devuelve el plan en la licencia
- Guardado automático del plan al activar licencia
- Sincronización de plan en cada verificación

### ⚡ Rendimiento

- Los límites se verifican en memoria (rápido)
- Contador almacenado en opciones de WordPress
- Sin consultas adicionales a la API para verificar uso

---

## [3.3.5] - 2026-01-15

### 🔒 Seguridad

- **CRÍTICO:** Implementado rate limiting para prevenir spam (3 intentos cada 10 minutos por IP)
- **CRÍTICO:** Validación de capacidad total mejorada para prevenir overbooking
- **IMPORTANTE:** Verificación de usuarios en lista negra antes de aceptar reservas
- **IMPORTANTE:** Sistema de logging de eventos de seguridad y errores críticos
- **IMPORTANTE:** Mejorada validación de nonces con identificador único
- **IMPORTANTE:** Añadido escape de outputs en templates (preparado para futura implementación)

### ✨ Nuevas Funcionalidades

- **Sistema de Logging Centralizado:** Nueva clase `MakIA_Logger` que registra:
  - Intentos de reserva con rate limit excedido
  - Usuarios en lista negra que intentan reservar
  - Errores de conexión con API de licencias
  - Reservas creadas exitosamente
  - Logs rotan automáticamente al superar 10MB
  
- **Validación de Capacidad Total:** 
  - Suma correcta de comensales por fecha/hora
  - Mensajes informativos de plazas disponibles
  - Previene overbooking efectivamente

- **Validación de Horarios de Negocio:**
  - Verifica que la fecha/hora esté dentro de horarios configurados
  - Detecta días cerrados automáticamente
  - Muestra franjas horarias disponibles en mensajes de error

- **Validación de Fechas Mejorada:**
  - Configuración de horas mínimas de antelación (nueva opción)
  - Configuración de meses máximos de antelación (nueva opción)
  - Previene reservas muy cercanas o muy lejanas

### 🔧 Mejoras

- **Validación de Teléfonos:**
  - Acepta formatos internacionales (+34666777888)
  - Valida longitud (9-15 dígitos)
  - Normaliza formato para almacenamiento
  - Mensajes de error específicos con ejemplos

- **Validación de Nombres:**
  - Longitud mínima 2 caracteres, máxima 100
  - Acepta caracteres internacionales (á, é, í, ó, ú, ñ, ç, etc.)
  - Acepta nombres compuestos (Jean-Pierre, O'Connor)
  - Detecta spam (caracteres repetidos 5+ veces)

- **Validación de Emails:**
  - Verificación de registros MX del dominio
  - Bloqueo de emails desechables conocidos
  - Mensajes de error específicos

- **Mensajes de Error Mejorados:**
  - Cada error incluye campo específico afectado
  - Mensajes claros y orientados a la solución
  - Información contextual (ej: plazas disponibles, horarios)

### 🐛 Correcciones

- **CRÍTICO:** Corregida discrepancia de versión (3.3.3 → 3.3.4 → 3.3.5)
- **CRÍTICO:** Corregido bug de overbooking por validación de capacidad incompleta
- **MEDIO:** Eliminada validación duplicada de email
- **BAJO:** Mejorado manejo de errores en inserción de BD

### 📝 Código y Estructura

- Nuevo archivo: `includes/class-makia-logger.php` (sistema de logging)
- Código más limpio y comentado
- Separación clara de responsabilidades
- Logging en puntos críticos para debugging

### ⚙️ Configuración

Nuevas opciones disponibles en `wp_options`:
- `makia_min_hours_advance` (default: 2) - Horas mínimas de antelación
- `makia_max_months_advance` (default: 3) - Meses máximos de antelación

### 🔄 Compatibilidad

- Compatible con WordPress 5.0+
- Compatible con PHP 7.4 - 8.x
- Retrocompatible con versión 3.3.4
- No requiere migración de base de datos

### 📊 Rendimiento

- Rate limiting reduce carga del servidor ante ataques
- Validaciones optimizadas (menos consultas redundantes)
- Sistema de logs con rotación automática

### ⚠️ Cambios que Requieren Atención

1. **Nuevo archivo de logs:** Se creará automáticamente en `wp-content/makia-logs.txt`
   - Asegurar permisos de escritura en `wp-content/`
   - Monitorear tamaño (rota automáticamente a 10MB)
   
2. **Rate Limiting:** 
   - Usuarios legítimos con problemas pueden exceder límite
   - Se puede ajustar modificando `$max_attempts` en `class-makia-bookings.php` línea 105

3. **Validación de Email:**
   - Verificación de MX puede fallar en algunos servidores sin DNS
   - Se puede desactivar comentando líneas 201-207 en `class-makia-bookings.php`

### 🚀 Próximas Mejoras Planificadas

- [ ] Página de administración para visualizar logs
- [ ] Configuración de rate limiting desde admin
- [ ] Lista blanca de IPs para bypass de rate limiting
- [ ] Notificaciones de eventos de seguridad por email
- [ ] Exportación de logs a CSV

---

## [3.3.4] - 2026-01-08

### Características Originales

- Sistema de licencias con Contacpro.app
- Gestión completa de reservas
- Formulario embebible con shortcode
- Panel de administración
- Sistema de notificaciones (email, SMS, WhatsApp)
- Control de capacidad y horarios
- Lista negra de usuarios
- Plantillas personalizables

### Problemas Conocidos (Corregidos en 3.3.5)

- Discrepancia de versión en constante
- Validación de capacidad incompleta
- Falta de rate limiting
- Validaciones básicas de inputs
- Sin sistema de logging

---

## Formato del Changelog

Este changelog sigue el formato de [Keep a Changelog](https://keepachangelog.com/es/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

### Tipos de Cambios

- **Seguridad** - Para vulnerabilidades corregidas
- **Nuevas Funcionalidades** - Para nuevas características
- **Mejoras** - Para cambios en funcionalidades existentes
- **Correcciones** - Para bugs corregidos
- **Deprecado** - Para funcionalidades que serán eliminadas
- **Eliminado** - Para funcionalidades eliminadas

### Niveles de Prioridad

- **CRÍTICO** - Requiere actualización inmediata
- **IMPORTANTE** - Recomendado actualizar pronto
- **MEDIO** - Actualizar cuando sea conveniente
- **BAJO** - Opcional

---

**Última actualización:** 15 de enero de 2026  
**Versión actual:** 3.3.5  
**Desarrollado por:** MakIA Team  
**Licencia:** GPL v2 or later
