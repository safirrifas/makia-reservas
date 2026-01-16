<?php
/**
 * Vista de la pestaña de Diseño - Enhanced Version
 * Incluye: Selección de plantillas, Personalización avanzada, Vista previa en vivo
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="makia-design-tab-enhanced">
    
    <!-- Header -->
    <div class="makia-design-header" style="margin-bottom: 30px;">
        <h2 style="color: #667eea; margin-bottom: 10px; font-size: 28px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 32px;">🎨</span>
            Diseño del Formulario de Reservas
        </h2>
        <p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0;">
            Personaliza el aspecto de tu formulario de reservas. Elige una plantilla profesional y ajusta colores, textos y más.
        </p>
    </div>
    
    <!-- Navegación por pestañas -->
    <div class="makia-design-tabs" style="margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; display: flex; gap: 5px;">
        <button class="makia-design-tab-btn active" data-tab="templates" 
                style="padding: 14px 28px; border: none; background: none; border-bottom: 3px solid #667eea; color: #667eea; font-weight: 600; cursor: pointer; font-size: 15px; transition: all 0.3s ease;">
            <span style="margin-right: 8px;">📐</span> Plantillas
        </button>
        <button class="makia-design-tab-btn" data-tab="customize" 
                style="padding: 14px 28px; border: none; background: none; border-bottom: 3px solid transparent; color: #666; font-weight: 600; cursor: pointer; font-size: 15px; transition: all 0.3s ease;">
            <span style="margin-right: 8px;">🎨</span> Personalizar
        </button>
        <button class="makia-design-tab-btn" data-tab="preview" 
                style="padding: 14px 28px; border: none; background: none; border-bottom: 3px solid transparent; color: #666; font-weight: 600; cursor: pointer; font-size: 15px; transition: all 0.3s ease;">
            <span style="margin-right: 8px;">👁️</span> Vista Previa
        </button>
    </div>
    
    <!-- Contenido de las pestañas -->
    
    <!-- PESTAÑA 1: PLANTILLAS -->
    <div class="makia-design-section" id="makia-section-templates">
        <div class="makia-templates-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; margin-bottom: 30px;">
            
            <?php foreach ($templates as $template_id => $template): ?>
            <div class="makia-template-card <?php echo ($active_template === $template_id) ? 'active' : ''; ?>" 
                 data-template="<?php echo esc_attr($template_id); ?>"
                 style="background: #fff; border: 2px solid <?php echo ($active_template === $template_id) ? '#667eea' : '#e0e0e0'; ?>; border-radius: 12px; padding: 25px; cursor: pointer; transition: all 0.3s ease; position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                
                <?php if ($active_template === $template_id): ?>
                <div class="template-badge-active" style="position: absolute; top: 15px; right: 15px; background: #667eea; color: #fff; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; box-shadow: 0 2px 8px rgba(102,126,234,0.3);">
                    ✓ ACTIVA
                </div>
                <?php endif; ?>
                
                <h3 style="margin: 0 0 12px 0; color: #333; font-size: 20px; font-weight: 600;">
                    <?php echo esc_html($template['name']); ?>
                </h3>
                
                <p style="color: #666; font-size: 14px; line-height: 1.6; margin-bottom: 18px; min-height: 60px;">
                    <?php echo esc_html($template['description']); ?>
                </p>
                
                <div style="margin-bottom: 15px;">
                    <strong style="font-size: 13px; color: #555;">Estilo:</strong>
                    <span style="font-size: 13px; color: #777;"><?php echo esc_html($template['style']); ?></span>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <strong style="font-size: 13px; color: #555; display: block; margin-bottom: 8px;">Paleta de colores:</strong>
                    <div style="display: flex; gap: 8px;">
                        <?php foreach ($template['colors'] as $color): ?>
                        <div style="width: 44px; height: 44px; background: <?php echo esc_attr($color); ?>; border-radius: 8px; border: 2px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="button" class="button button-primary makia-select-template" 
                        data-template="<?php echo esc_attr($template_id); ?>"
                        style="width: 100%; padding: 12px; background: <?php echo ($active_template === $template_id) ? '#46b450' : '#667eea'; ?>; border: none; border-radius: 8px; color: #fff; font-weight: 600; cursor: pointer; font-size: 15px; transition: all 0.3s ease;">
                    <?php echo ($active_template === $template_id) ? '✓ Plantilla Activa' : 'Seleccionar Plantilla'; ?>
                </button>
            </div>
            <?php endforeach; ?>
            
        </div>
        
        <div class="makia-design-info" style="background: linear-gradient(135deg, #f9f9f9 0%, #fefefe 100%); padding: 30px; border-radius: 12px; border-left: 5px solid #667eea; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <h3 style="margin: 0 0 15px 0; color: #667eea; font-size: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">💡</span>
                Información Importante
            </h3>
            <ul style="margin: 0; padding-left: 20px; color: #666; line-height: 1.8; font-size: 15px;">
                <li>La plantilla se aplica automáticamente al formulario de reservas en el frontend</li>
                <li>Los cambios son instantáneos, sin necesidad de recargar la página</li>
                <li>Todas las plantillas son 100% responsive y optimizadas para móviles</li>
                <li>Puedes personalizar colores, textos y animaciones en la pestaña "Personalizar"</li>
                <li>Usa la pestaña "Vista Previa" para ver cómo quedará tu formulario</li>
            </ul>
        </div>
    </div>
    
    <!-- PESTAÑA 2: PERSONALIZACIÓN -->
    <div class="makia-design-section" id="makia-section-customize" style="display: none;">
        <form id="makia-customization-form" style="max-width: 900px;">
            
            <!-- Sección: Identidad Visual -->
            <div class="makia-custom-section" style="background: #fff; padding: 35px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 25px;">
                <h3 style="margin: 0 0 25px 0; color: #333; font-size: 22px; display: flex; align-items: center; gap: 10px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                    <span style="font-size: 26px;">🎨</span>
                    Identidad Visual
                </h3>
                
                <div style="margin-bottom: 30px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #333; font-size: 15px;">
                        Logo del Restaurante
                    </label>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <input type="url" name="logo_url" id="makia-logo-url" value="<?php echo esc_attr($customization['logo_url']); ?>" 
                               placeholder="https://turestaurante.com/logo.png"
                               style="flex: 1; padding: 12px 16px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; transition: all 0.3s ease;">
                        <button type="button" class="button makia-upload-logo" style="padding: 12px 20px; border-radius: 8px; background: #667eea; color: #fff; border: none; font-weight: 600;">
                            📁 Subir Logo
                        </button>
                    </div>
                    <small style="color: #666; display: block; margin-top: 8px; font-size: 13px;">
                        Opcional: Sube o ingresa la URL de tu logo. Se mostrará en la parte superior del formulario.
                    </small>
                    <div id="logo-preview" style="margin-top: 15px; display: none;">
                        <img id="logo-preview-img" src="" alt="Logo Preview" style="max-width: 200px; border: 2px solid #e0e0e0; border-radius: 8px; padding: 10px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #333; font-size: 15px;">
                            Color Primario
                        </label>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <input type="color" name="primary_color" value="<?php echo esc_attr($customization['primary_color']); ?>" 
                                   style="width: 70px; height: 50px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer;">
                            <input type="text" value="<?php echo esc_attr($customization['primary_color']); ?>" 
                                   class="makia-color-text" data-for="primary_color"
                                   style="flex: 1; padding: 12px 16px; border: 2px solid #ddd; border-radius: 8px; font-family: monospace; font-size: 14px;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #333; font-size: 15px;">
                            Color Secundario
                        </label>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <input type="color" name="secondary_color" value="<?php echo esc_attr($customization['secondary_color']); ?>" 
                                   style="width: 70px; height: 50px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer;">
                            <input type="text" value="<?php echo esc_attr($customization['secondary_color']); ?>" 
                                   class="makia-color-text" data-for="secondary_color"
                                   style="flex: 1; padding: 12px 16px; border: 2px solid #ddd; border-radius: 8px; font-family: monospace; font-size: 14px;">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sección: Textos del Formulario -->
            <div class="makia-custom-section" style="background: #fff; padding: 35px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 25px;">
                <h3 style="margin: 0 0 25px 0; color: #333; font-size: 22px; display: flex; align-items: center; gap: 10px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                    <span style="font-size: 26px;">✍️</span>
                    Textos del Formulario
                </h3>
                
                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #333; font-size: 15px;">
                        Título del Formulario
                    </label>
                    <input type="text" name="form_title" value="<?php echo esc_attr($customization['form_title']); ?>" 
                           placeholder="Reserva tu Mesa"
                           style="width: 100%; padding: 12px 16px; border: 2px solid #ddd; border-radius: 8px; font-size: 15px; transition: all 0.3s ease;">
                </div>
                
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #333; font-size: 15px;">
                        Subtítulo del Formulario
                    </label>
                    <input type="text" name="form_subtitle" value="<?php echo esc_attr($customization['form_subtitle']); ?>" 
                           placeholder="Completa el formulario y confirmaremos tu reserva"
                           style="width: 100%; padding: 12px 16px; border: 2px solid #ddd; border-radius: 8px; font-size: 15px; transition: all 0.3s ease;">
                </div>
            </div>
            
            <!-- Sección: Opciones de Experiencia -->
            <div class="makia-custom-section" style="background: #fff; padding: 35px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 25px;">
                <h3 style="margin: 0 0 25px 0; color: #333; font-size: 22px; display: flex; align-items: center; gap: 10px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                    <span style="font-size: 26px;">⚡</span>
                    Opciones de Experiencia
                </h3>
                
                <div style="margin-bottom: 25px;">
                    <label style="display: flex; align-items: center; cursor: pointer; padding: 15px; background: #f9f9f9; border-radius: 8px; transition: all 0.3s ease;">
                        <input type="checkbox" name="enable_animations" value="1" 
                               <?php checked($customization['enable_animations'], '1'); ?>
                               style="margin-right: 12px; width: 20px; height: 20px; cursor: pointer;">
                        <div>
                            <span style="font-weight: 600; color: #333; font-size: 15px; display: block;">Activar Animaciones y Transiciones</span>
                            <span style="color: #666; font-size: 13px;">Efectos visuales suaves para mejorar la experiencia del usuario</span>
                        </div>
                    </label>
                </div>
                
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #333; font-size: 15px;">
                        Posición del Modal
                    </label>
                    <select name="modal_position" style="width: 100%; padding: 12px 16px; border: 2px solid #ddd; border-radius: 8px; font-size: 15px; cursor: pointer;">
                        <option value="center" <?php selected($customization['modal_position'], 'center'); ?>>Centro de la pantalla (Recomendado)</option>
                        <option value="top" <?php selected($customization['modal_position'], 'top'); ?>>Parte superior</option>
                        <option value="right" <?php selected($customization['modal_position'], 'right'); ?>>Lateral derecho (Estilo Drawer)</option>
                    </select>
                    <small style="color: #666; display: block; margin-top: 8px; font-size: 13px;">
                        Elige dónde aparecerá el formulario de reserva al hacer clic en el botón
                    </small>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div style="display: flex; gap: 15px; justify-content: flex-end; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                <button type="button" id="makia-reset-customization" class="button" style="padding: 12px 24px; border-radius: 8px; font-weight: 600;">
                    🔄 Restaurar por Defecto
                </button>
                <button type="submit" class="button button-primary" style="background: #667eea; border-color: #667eea; padding: 12px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
                    💾 Guardar Personalización
                </button>
            </div>
        </form>
    </div>
    
    <!-- PESTAÑA 3: VISTA PREVIA -->
    <div class="makia-design-section" id="makia-section-preview" style="display: none;">
        <div style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 50px 40px; border-radius: 12px; text-align: center; min-height: 600px;">
            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 26px; font-weight: 600;">
                Vista Previa en Vivo
            </h3>
            <p style="color: #666; margin-bottom: 40px; font-size: 16px;">
                Esta es una simulación de cómo se verá tu formulario en el frontend
            </p>
            
            <div id="makia-preview-container" class="makia-template-<?php echo esc_attr($active_template); ?>" style="max-width: 540px; margin: 0 auto;">
                <div style="background: #fff; padding: 45px 40px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); text-align: center;">
                    
                    <div id="makia-preview-logo-container" style="margin-bottom: 25px; <?php echo empty($customization['logo_url']) ? 'display: none;' : ''; ?>">
                        <img id="makia-preview-logo" src="<?php echo esc_url($customization['logo_url']); ?>" alt="Logo" style="max-width: 160px; height: auto;">
                    </div>
                    
                    <div style="margin-bottom: 35px;">
                        <h2 id="makia-preview-title" style="margin: 0 0 10px 0; font-size: 30px; font-weight: 600; color: <?php echo esc_attr($customization['primary_color']); ?>;">
                            <?php echo esc_html($customization['form_title']); ?>
                        </h2>
                        <p id="makia-preview-subtitle" style="color: #666; font-size: 16px; margin: 0;">
                            <?php echo esc_html($customization['form_subtitle']); ?>
                        </p>
                    </div>
                    
                    <div style="text-align: left; margin-bottom: 20px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: #333;">Nombre</label>
                        <input type="text" placeholder="Tu nombre" disabled style="width: 100%; padding: 12px 16px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 15px; background: #f9f9f9;">
                    </div>
                    
                    <div style="text-align: left; margin-bottom: 20px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: #333;">Email</label>
                        <input type="email" placeholder="tu@email.com" disabled style="width: 100%; padding: 12px 16px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 15px; background: #f9f9f9;">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
                        <div style="text-align: left;">
                            <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: #333;">Fecha</label>
                            <input type="date" disabled style="width: 100%; padding: 12px 16px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 15px; background: #f9f9f9;">
                        </div>
                        <div style="text-align: left;">
                            <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: #333;">Hora</label>
                            <select disabled style="width: 100%; padding: 12px 16px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 15px; background: #f9f9f9;">
                                <option>20:00</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="button" id="makia-preview-button" disabled 
                            style="width: 100%; padding: 16px 28px; font-size: 16px; font-weight: 600; color: #fff; background: <?php echo esc_attr($customization['primary_color']); ?>; border: none; border-radius: 8px; cursor: not-allowed; transition: all 0.3s ease;">
                        Confirmar Reserva
                    </button>
                </div>
            </div>
            
            <div style="margin-top: 40px; padding: 25px; background: rgba(255,255,255,0.9); border-radius: 10px; border-left: 4px solid #667eea; text-align: left; max-width: 700px; margin-left: auto; margin-right: auto;">
                <h4 style="margin: 0 0 12px 0; color: #667eea; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 22px;">💡</span>
                    Nota Importante
                </h4>
                <p style="margin: 0; color: #666; line-height: 1.7; font-size: 15px;">
                    Esta es una vista previa simplificada del formulario. El formulario completo incluirá todos los campos de reserva (teléfono, número de personas, motivo, comentarios, etc.). 
                    Para ver el resultado final con todas las funcionalidades, visita la página donde has colocado el shortcode 
                    <code style="background: #f5f5f5; padding: 4px 8px; border-radius: 4px; font-family: monospace; color: #667eea;">[makia_booking_form]</code>
                </p>
            </div>
        </div>
    </div>
    
</div>

<!-- Estilos CSS adicionales para la interfaz admin -->
<style>
.makia-design-tab-btn {
    transition: all 0.3s ease;
}
.makia-design-tab-btn:hover {
    color: #667eea !important;
    background: rgba(102, 126, 234, 0.05);
}
.makia-design-tab-btn.active {
    color: #667eea !important;
    border-bottom-color: #667eea !important;
}
.makia-template-card {
    transition: all 0.3s ease;
}
.makia-template-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12) !important;
    border-color: #667eea !important;
}
.makia-template-card.active {
    box-shadow: 0 4px 20px rgba(102,126,234,0.2) !important;
}
input[type="color"] {
    cursor: pointer;
}
input:focus, select:focus, textarea:focus {
    border-color: #667eea !important;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1) !important;
    outline: none;
}
label:has(input[type="checkbox"]):hover {
    background: #f0f0f0 !important;
}
.makia-custom-section {
    transition: all 0.3s ease;
}
.makia-custom-section:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.12) !important;
}
</style>

<!-- JavaScript para la funcionalidad de la interfaz -->
<script>
jQuery(document).ready(function($) {
    
    // ========================================
    // SISTEMA DE TABS
    // ========================================
    $('.makia-design-tab-btn').on('click', function() {
        var tab = $(this).data('tab');
        
        // Actualizar botones
        $('.makia-design-tab-btn').removeClass('active')
            .css({'color': '#666', 'border-bottom-color': 'transparent'});
        $(this).addClass('active')
            .css({'color': '#667eea', 'border-bottom-color': '#667eea'});
        
        // Mostrar sección
        $('.makia-design-section').hide();
        $('#makia-section-' + tab).fadeIn(300);
        
        // Actualizar preview si es la pestaña de preview
        if (tab === 'preview') {
            updatePreview();
        }
    });
    
    // ========================================
    // SINCRONIZACIÓN DE COLORES
    // ========================================
    $('input[type="color"]').on('change', function() {
        var name = $(this).attr('name');
        $('.makia-color-text[data-for="' + name + '"]').val($(this).val());
        updatePreviewColors();
    });
    
    $('.makia-color-text').on('input', function() {
        var name = $(this).data('for');
        var value = $(this).val();
        if (/^#[0-9A-F]{6}$/i.test(value)) {
            $('input[name="' + name + '"]').val(value);
            updatePreviewColors();
        }
    });
    
    // ========================================
    // ACTUALIZACIÓN DE PREVIEW EN TIEMPO REAL
    // ========================================
    $('input[name="form_title"], input[name="form_subtitle"], input[name="logo_url"]').on('input', updatePreview);
    
    function updatePreview() {
        var title = $('input[name="form_title"]').val() || 'Reserva tu Mesa';
        var subtitle = $('input[name="form_subtitle"]').val() || 'Completa el formulario y confirmaremos tu reserva';
        var logo = $('input[name="logo_url"]').val();
        
        $('#makia-preview-title').text(title);
        $('#makia-preview-subtitle').text(subtitle);
        
        if (logo) {
            $('#makia-preview-logo').attr('src', logo);
            $('#makia-preview-logo-container').show();
            $('#logo-preview-img').attr('src', logo);
            $('#logo-preview').show();
        } else {
            $('#makia-preview-logo-container').hide();
            $('#logo-preview').hide();
        }
        
        updatePreviewColors();
    }
    
    function updatePreviewColors() {
        var primaryColor = $('input[name="primary_color"]').val() || '#667eea';
        $('#makia-preview-button').css('background', primaryColor);
        $('#makia-preview-title').css('color', primaryColor);
    }
    
    // ========================================
    // SUBIR LOGO CON MEDIA LIBRARY
    // ========================================
    $('.makia-upload-logo').on('click', function(e) {
        e.preventDefault();
        
        var custom_uploader = wp.media({
            title: 'Seleccionar Logo',
            button: {
                text: 'Usar este logo'
            },
            multiple: false
        }).on('select', function() {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            $('#makia-logo-url').val(attachment.url);
            updatePreview();
        }).open();
    });
    
    // ========================================
    // SELECCIONAR PLANTILLA
    // ========================================
    $('.makia-select-template').on('click', function() {
        var $button = $(this);
        var templateId = $button.data('template');
        var $card = $button.closest('.makia-template-card');
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span> Aplicando...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'makia_save_template',
                template_id: templateId,
                nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar UI
                    $('.makia-template-card').removeClass('active').css('border-color', '#e0e0e0');
                    $('.makia-template-card .button-primary').css('background', '#667eea').text('Seleccionar Plantilla');
                    $('.template-badge-active').remove();
                    
                    $card.addClass('active').css('border-color', '#667eea');
                    $card.prepend('<div class="template-badge-active" style="position: absolute; top: 15px; right: 15px; background: #667eea; color: #fff; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; box-shadow: 0 2px 8px rgba(102,126,234,0.3);">✓ ACTIVA</div>');
                    $button.css('background', '#46b450').html('✓ Plantilla Activa');
                    
                    // Actualizar clase en preview
                    $('#makia-preview-container').attr('class', 'makia-template-' + templateId);
                    
                    showNotification('✓ Plantilla aplicada correctamente', 'success');
                } else {
                    showNotification('Error: ' + response.data.message, 'error');
                    $button.prop('disabled', false).text('Seleccionar Plantilla');
                }
            },
            error: function() {
                showNotification('Error al guardar la plantilla', 'error');
                $button.prop('disabled', false).text('Seleccionar Plantilla');
            }
        });
    });
    
    // ========================================
    // GUARDAR PERSONALIZACIÓN
    // ========================================
    $('#makia-customization-form').on('submit', function(e) {
        e.preventDefault();
        
        var $submitBtn = $(this).find('button[type="submit"]');
        var originalHtml = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span> Guardando...');
        
        var formData = {
            action: 'makia_save_customization',
            nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>',
            logo_url: $('input[name="logo_url"]').val(),
            primary_color: $('input[name="primary_color"]').val(),
            secondary_color: $('input[name="secondary_color"]').val(),
            form_title: $('input[name="form_title"]').val(),
            form_subtitle: $('input[name="form_subtitle"]').val(),
            enable_animations: $('input[name="enable_animations"]').is(':checked') ? '1' : '0',
            modal_position: $('select[name="modal_position"]').val(),
            show_logo: '1',
            font_family: 'default'
        };
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotification('✓ Personalización guardada correctamente', 'success');
                } else {
                    showNotification('Error: ' + response.data.message, 'error');
                }
                $submitBtn.prop('disabled', false).html(originalHtml);
            },
            error: function() {
                showNotification('Error al guardar la personalización', 'error');
                $submitBtn.prop('disabled', false).html(originalHtml);
            }
        });
    });
    
    // ========================================
    // RESTAURAR VALORES POR DEFECTO
    // ========================================
    $('#makia-reset-customization').on('click', function() {
        if (confirm('¿Estás seguro de restaurar los valores por defecto? Esta acción no se puede deshacer.')) {
            $('input[name="logo_url"]').val('');
            $('input[name="primary_color"]').val('#667eea');
            $('input[name="secondary_color"]').val('#764ba2');
            $('input[name="form_title"]').val('Reserva tu Mesa');
            $('input[name="form_subtitle"]').val('Completa el formulario y confirmaremos tu reserva');
            $('input[name="enable_animations"]').prop('checked', true);
            $('select[name="modal_position"]').val('center');
            
            // Sincronizar color pickers
            $('.makia-color-text[data-for="primary_color"]').val('#667eea');
            $('.makia-color-text[data-for="secondary_color"]').val('#764ba2');
            
            updatePreview();
            showNotification('Valores restaurados. No olvides guardar los cambios.', 'info');
        }
    });
    
    // ========================================
    // SISTEMA DE NOTIFICACIONES
    // ========================================
    function showNotification(message, type) {
        var bgColor = type === 'success' ? '#46b450' : type === 'error' ? '#dc3545' : '#667eea';
        var icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
        
        var notification = $('<div>')
            .css({
                'position': 'fixed',
                'top': '32px',
                'right': '20px',
                'background': bgColor,
                'color': '#fff',
                'padding': '16px 28px',
                'border-radius': '8px',
                'box-shadow': '0 6px 20px rgba(0,0,0,0.2)',
                'z-index': '999999',
                'font-weight': '600',
                'font-size': '15px',
                'min-width': '300px',
                'animation': 'slideInRight 0.3s ease'
            })
            .html('<span style="margin-right: 10px; font-size: 18px;">' + icon + '</span>' + message)
            .appendTo('body');
        
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3500);
    }
    
    // Inicializar preview
    updatePreview();
    
    // Añadir animación de spin
    $('<style>@keyframes spin { to { transform: rotate(360deg); } }</style>').appendTo('head');
});
</script>
