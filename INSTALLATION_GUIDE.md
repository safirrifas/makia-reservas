# 📦 Guía de Instalación y Actualización - MakIA Reservas v4.1.0

## 🎉 ¡Bienvenido a la versión 4.1.0!

Esta versión incluye mejoras masivas en el sistema de diseño del formulario de reservas.

---

## 🆕 Nueva Instalación

### Paso 1: Subir el Plugin
1. Descargar `makia-reservas-v4.1.0.zip`
2. Ir a **WordPress Admin → Plugins → Añadir nuevo**
3. Hacer clic en **"Subir plugin"**
4. Seleccionar el archivo ZIP
5. Hacer clic en **"Instalar ahora"**
6. Activar el plugin

### Paso 2: Configuración Inicial
1. Ir a **MakIA Reservas** en el menú lateral
2. Configurar horarios del restaurante
3. Configurar capacidad de mesas
4. Elegir plantilla de diseño en pestaña **Diseño**

---

## 🔄 Actualización desde v4.0.0 o anterior

### ⚠️ IMPORTANTE: Hacer Backup
Antes de actualizar, hacer backup de:
- Base de datos WordPress
- Carpeta `/wp-content/plugins/makia-reservas/`
- Todas las personalizaciones CSS custom (si existen)

### Método 1: Actualización Manual (Recomendado)

1. **Desactivar** el plugin actual (NO eliminar)
2. Descargar la nueva versión `makia-reservas-v4.1.0.zip`
3. Acceder por FTP/cPanel al servidor
4. Navegar a `/wp-content/plugins/`
5. **Eliminar** carpeta `makia-reservas/` antigua
6. Subir y descomprimir `makia-reservas-v4.1.0.zip`
7. Volver al admin de WordPress
8. **Activar** el plugin

### Método 2: Desde WordPress Admin

1. Ir a **Plugins → Plugins instalados**
2. **Desactivar** MakIA Reservas
3. Hacer clic en **"Eliminar"** (las opciones se conservan)
4. Ir a **Plugins → Añadir nuevo**
5. Hacer clic en **"Subir plugin"**
6. Seleccionar `makia-reservas-v4.1.0.zip`
7. **Instalar** y luego **Activar**

---

## ✨ Nuevas Funcionalidades v4.1.0

### 1. Panel de Diseño con 3 Pestañas

Ir a **MakIA Reservas → Diseño**

#### Pestaña: Plantillas
- 6 plantillas profesionales mejoradas
- Selector visual con miniaturas
- Indicador de plantilla activa
- Paletas de colores mostradas
- Activación con un solo clic

#### Pestaña: Personalización
- **Colores Personalizados:**
  - Color Principal (botones, enlaces)
  - Color Secundario (fondos)
  - Color de Texto
  - Pickers visuales + inputs hex
  
- **Logo del Restaurante:**
  - Campo para URL del logo
  - Vista previa automática
  - Se muestra en el formulario
  
- **Textos Personalizados:**
  - Título del formulario
  - Subtítulo descriptivo
  - Texto del botón de envío

#### Pestaña: Vista Previa
- Preview en tiempo real
- Actualización instantánea
- Todos los cambios reflejados
- Escala optimizada

### 2. Animaciones Premium
- ✅ Entrada suave del modal
- ✅ Efectos hover en inputs
- ✅ Ripple en botones
- ✅ Loading spinner animado
- ✅ Validación con shake
- ✅ 15+ micro-interacciones

### 3. Responsive Optimizado
- ✅ Tablet (<768px) optimizado
- ✅ Mobile (<480px) ultra-responsive
- ✅ Logo responsive automático
- ✅ Campos adaptables

---

## 🎨 Cómo Usar las Nuevas Funciones

### Cambiar Plantilla
1. Ir a **MakIA Reservas → Diseño**
2. Navegar a **Plantillas**
3. Hacer clic en **"Seleccionar"** en la plantilla deseada
4. ✅ ¡Listo! Se activa automáticamente

### Personalizar Colores
1. Ir a pestaña **Personalización**
2. Usar color-pickers o ingresar códigos hex
3. Ver cambios en **Vista Previa**
4. Hacer clic en **"💾 Guardar Personalizaciones"**

### Añadir Logo
1. Subir logo a **Media → Biblioteca**
2. Copiar URL de la imagen
3. Pegar en campo **"URL del Logo"**
4. Hacer clic en **"💾 Guardar Personalizaciones"**
5. Logo aparece automáticamente en formulario

### Personalizar Textos
1. Ir a **Personalización**
2. Cambiar:
   - Título del formulario
   - Subtítulo
   - Texto del botón
3. Ver cambios en tiempo real en **Vista Previa**
4. Guardar cambios

---

## 🔧 Solución de Problemas

### El diseño no se aplica
1. Limpiar caché del navegador (Ctrl+F5)
2. Limpiar caché de WordPress (si usa plugin de caché)
3. Verificar que el shortcode `[makia_booking_form]` esté en la página
4. Comprobar que el plugin esté activado

### Los colores personalizados no funcionan
1. Verificar que se hizo clic en **"Guardar Personalizaciones"**
2. Verificar códigos hex válidos (ej: #667eea)
3. Limpiar caché del navegador
4. Recargar página del frontend

### El logo no aparece
1. Verificar que la URL del logo sea correcta y accesible
2. Verificar que la imagen esté en formato JPG, PNG o SVG
3. Comprobar permisos de la imagen
4. Probar con URL completa (https://...)

### Las animaciones van lentas
1. Verificar conexión a internet
2. Desactivar otros plugins que afecten CSS/JS
3. Comprobar que el navegador sea moderno (Chrome, Firefox, Safari, Edge)

---

## 📝 Notas de Compatibilidad

### Requisitos del Sistema
- ✅ WordPress 5.8 o superior
- ✅ PHP 7.4 o superior
- ✅ MySQL 5.6 o superior
- ✅ Navegador moderno actualizado

### Plugins Compatibles
- ✅ WP Rocket (caché)
- ✅ WooCommerce
- ✅ WPML (multi-idioma)
- ✅ Contact Form 7
- ✅ Yoast SEO

### Temas Compatibles
- ✅ Astra
- ✅ GeneratePress
- ✅ OceanWP
- ✅ Kadence
- ✅ Cualquier tema bien codificado

---

## 🆘 Soporte

### ¿Necesitas ayuda?
- 📧 Email: soporte@contacpro.app
- 🌐 Web: https://contacpro.app/soporte
- 📚 Documentación: https://docs.contacpro.app

### Reportar Bugs
- 🐛 GitHub Issues: [github.com/makia/reservas/issues](https://github.com)
- 📝 Incluir: versión de WordPress, PHP, y pasos para reproducir

---

## 🎓 Recursos Adicionales

### Documentación
- `README.md` - Información general del plugin
- `DESIGN_IMPROVEMENTS.md` - Detalles técnicos de mejoras
- `CHANGELOG.md` - Historial completo de cambios

### Tutoriales en Video
- 🎥 YouTube: [Canal MakIA](https://youtube.com)
- 📺 Playlist: Configuración de MakIA Reservas

---

## 🎉 ¡Disfruta de MakIA Reservas v4.1.0!

Gracias por usar nuestro plugin. Si te gusta, por favor:
- ⭐ Deja una reseña en WordPress.org
- 📢 Comparte con otros restaurantes
- 💬 Cuéntanos tu experiencia

**¡Tu feedback nos ayuda a mejorar!**

---

**Versión:** 4.1.0  
**Fecha:** Enero 2026  
**Equipo:** MakIA Development Team  
**Licencia:** GPL v2 o posterior
