<?php
/**
 * MakIA Design Templates Manager
 * Gestiona las plantillas de diseño del formulario de reservas
 */

class MakIA_Design {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Cargar CSS de plantillas en el frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_template_styles'));
        
        // AJAX para guardar plantilla seleccionada
        add_action('wp_ajax_makia_save_template', array($this, 'save_template_ajax'));
        
        // AJAX para guardar personalizaciones
        add_action('wp_ajax_makia_save_customizations', array($this, 'save_customizations_ajax'));
    }
    
    /**
     * Obtener plantillas disponibles
     */
    public static function get_available_templates() {
        return array(
            'modern' => array(
                'name' => 'Moderno Minimalista',
                'description' => 'Diseño limpio y contemporáneo con líneas suaves y espacios amplios',
                'colors' => array('#4A90E2', '#ffffff', '#fafafa'),
                'style' => 'Minimalista, espacioso, profesional'
            ),
            'elegant' => array(
                'name' => 'Elegante Clásico',
                'description' => 'Estilo refinado con tipografía serif y detalles dorados',
                'colors' => array('#d4af37', '#fdfcfb', '#2c2c2c'),
                'style' => 'Clásico, sofisticado, luxury'
            ),
            'vibrant' => array(
                'name' => 'Colorido Vibrante',
                'description' => 'Gradientes modernos y colores vivos para un look contemporáneo',
                'colors' => array('#667eea', '#764ba2', '#ffffff'),
                'style' => 'Moderno, dinámico, llamativo'
            ),
            'dark' => array(
                'name' => 'Oscuro Premium',
                'description' => 'Fondo oscuro con acentos dorados para experiencia premium',
                'colors' => array('#1a1a1a', '#d4af37', '#2a2a2a'),
                'style' => 'Premium, exclusivo, elegante'
            ),
            'rustic' => array(
                'name' => 'Rústico Acogedor',
                'description' => 'Tonos cálidos y naturales para ambiente acogedor',
                'colors' => array('#8b7355', '#f5f1e8', '#5a4a3a'),
                'style' => 'Natural, cálido, tradicional'
            ),
            'corporate' => array(
                'name' => 'Profesional Corporativo',
                'description' => 'Diseño serio y confiable con estructura clara',
                'colors' => array('#003d7a', '#ffffff', '#f9f9f9'),
                'style' => 'Corporativo, confiable, estructurado'
            )
        );
    }
    
    /**
     * Obtener plantilla activa
     */
    public static function get_active_template() {
        return get_option('makia_active_template', 'modern');
    }
    
    /**
     * Guardar plantilla seleccionada
     */
    public static function save_template($template_id) {
        $templates = self::get_available_templates();
        
        if (isset($templates[$template_id])) {
            update_option('makia_active_template', $template_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Cargar estilos de plantillas en el frontend
     */
    public function enqueue_template_styles() {
        // Solo cargar en páginas con shortcode de reservas
        if (has_shortcode(get_post()->post_content, 'makia_booking_form')) {
            wp_enqueue_style(
                'makia-booking-templates',
                plugins_url('assets/css/makia-booking-templates.css', dirname(__FILE__)),
                array(),
                '1.0.0'
            );
        }
    }
    
    /**
     * Handler AJAX para guardar plantilla
     */
    public function save_template_ajax() {
        // Verificar nonce
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            return;
        }
        
        $template_id = sanitize_text_field($_POST['template_id']);
        
        if (self::save_template($template_id)) {
            wp_send_json_success(array(
                'message' => 'Plantilla guardada correctamente',
                'template' => $template_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Plantilla no válida'));
        }
    }
    
    /**
     * Obtener personalizaciones
     */
    public static function get_customizations() {
        return array(
            'primary_color' => get_option('makia_primary_color', ''),
            'secondary_color' => get_option('makia_secondary_color', ''),
            'text_color' => get_option('makia_text_color', ''),
            'logo_url' => get_option('makia_logo_url', ''),
            'form_title' => get_option('makia_form_title', 'Reserva tu Mesa'),
            'form_subtitle' => get_option('makia_form_subtitle', 'Completa el formulario y confirmaremos tu reserva'),
            'button_text' => get_option('makia_submit_button_text', 'Confirmar Reserva')
        );
    }
    
    /**
     * Guardar personalizaciones
     */
    public static function save_customizations($data) {
        if (isset($data['primary_color'])) {
            update_option('makia_primary_color', sanitize_hex_color($data['primary_color']));
        }
        if (isset($data['secondary_color'])) {
            update_option('makia_secondary_color', sanitize_hex_color($data['secondary_color']));
        }
        if (isset($data['text_color'])) {
            update_option('makia_text_color', sanitize_hex_color($data['text_color']));
        }
        if (isset($data['logo_url'])) {
            update_option('makia_logo_url', esc_url_raw($data['logo_url']));
        }
        if (isset($data['form_title'])) {
            update_option('makia_form_title', sanitize_text_field($data['form_title']));
        }
        if (isset($data['form_subtitle'])) {
            update_option('makia_form_subtitle', sanitize_text_field($data['form_subtitle']));
        }
        if (isset($data['button_text'])) {
            update_option('makia_submit_button_text', sanitize_text_field($data['button_text']));
        }
        return true;
    }
    
    /**
     * Handler AJAX para guardar personalizaciones
     */
    public function save_customizations_ajax() {
        // Verificar nonce
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            return;
        }
        
        $data = array(
            'primary_color' => isset($_POST['primary_color']) ? $_POST['primary_color'] : '',
            'secondary_color' => isset($_POST['secondary_color']) ? $_POST['secondary_color'] : '',
            'text_color' => isset($_POST['text_color']) ? $_POST['text_color'] : '',
            'logo_url' => isset($_POST['logo_url']) ? $_POST['logo_url'] : '',
            'form_title' => isset($_POST['form_title']) ? $_POST['form_title'] : '',
            'form_subtitle' => isset($_POST['form_subtitle']) ? $_POST['form_subtitle'] : '',
            'button_text' => isset($_POST['button_text']) ? $_POST['button_text'] : ''
        );
        
        if (self::save_customizations($data)) {
            wp_send_json_success(array(
                'message' => 'Personalizaciones guardadas correctamente'
            ));
        } else {
            wp_send_json_error(array('message' => 'Error al guardar'));
        }
    }
    
    /**
     * Renderizar pestaña de diseño en el panel admin
     */
    public static function render_design_tab() {
        $templates = self::get_available_templates();
        $active_template = self::get_active_template();
        $customizations = self::get_customizations();
        ?>
        
        <div class="makia-design-tab">
            <div class="makia-design-header" style="margin-bottom: 30px;">
                <h2 style="color: #667eea; margin: 0 0 10px 0; font-size: 28px;">🎨 Diseño del Formulario</h2>
                <p style="color: #666; margin: 0;">Personaliza completamente la apariencia del formulario de reservas con plantillas profesionales y opciones avanzadas.</p>
            </div>
            
            <!-- Navegación de secciones -->
            <div class="makia-design-nav" style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0;">
                <button type="button" class="makia-design-nav-btn active" data-section="templates" style="padding: 12px 24px; background: none; border: none; border-bottom: 3px solid #667eea; color: #667eea; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    Plantillas
                </button>
                <button type="button" class="makia-design-nav-btn" data-section="customize" style="padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; color: #666; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    Personalización
                </button>
                <button type="button" class="makia-design-nav-btn" data-section="preview" style="padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; color: #666; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    Vista Previa
                </button>
            </div>
            
            <!-- Sección: Plantillas -->
            <div class="makia-design-section" id="makia-section-templates">
                <h3 style="color: #333; margin-bottom: 20px; font-size: 20px;">Selecciona una Plantilla Base</h3>
                
                <div class="makia-templates-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; margin-bottom: 30px;">
                    
                    <?php foreach ($templates as $template_id => $template): ?>
                    <div class="makia-template-card <?php echo ($active_template === $template_id) ? 'active' : ''; ?>" 
                         data-template="<?php echo esc_attr($template_id); ?>"
                         style="background: #fff; border: 2px solid <?php echo ($active_template === $template_id) ? '#667eea' : '#e0e0e0'; ?>; border-radius: 12px; padding: 25px; cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden;">
                        
                        <?php if ($active_template === $template_id): ?>
                        <div class="makia-active-badge" style="position: absolute; top: 15px; right: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; box-shadow: 0 2px 8px rgba(102,126,234,0.3);">
                            ✓ ACTIVA
                        </div>
                        <?php endif; ?>
                        
                        <!-- Miniatura de la plantilla -->
                        <div style="background: <?php echo esc_attr($template['colors'][2]); ?>; height: 120px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                            <div style="width: 80%; height: 70%; background: #fff; border-radius: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 10px;">
                                <div style="height: 25%; background: <?php echo esc_attr($template['colors'][0]); ?>; border-radius: 4px; margin-bottom: 8px; opacity: 0.3;"></div>
                                <div style="height: 15%; background: #ddd; border-radius: 3px; margin-bottom: 6px;"></div>
                                <div style="height: 15%; background: #ddd; border-radius: 3px; margin-bottom: 6px;"></div>
                                <div style="height: 25%; background: <?php echo esc_attr($template['colors'][0]); ?>; border-radius: 4px; opacity: 0.5;"></div>
                            </div>
                        </div>
                        
                        <h3 style="margin: 0 0 12px 0; color: #333; font-size: 18px; font-weight: 600;">
                            <?php echo esc_html($template['name']); ?>
                        </h3>
                        
                        <p style="color: #666; font-size: 14px; line-height: 1.5; margin-bottom: 15px; min-height: 42px;">
                            <?php echo esc_html($template['description']); ?>
                        </p>
                        
                        <div style="margin-bottom: 15px;">
                            <span style="font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 0.5px;">Estilo:</span>
                            <span style="font-size: 13px; color: #555; margin-left: 8px;"><?php echo esc_html($template['style']); ?></span>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <span style="font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">Paleta:</span>
                            <div style="display: flex; gap: 8px;">
                                <?php foreach ($template['colors'] as $color): ?>
                                <div style="width: 36px; height: 36px; background: <?php echo esc_attr($color); ?>; border-radius: 8px; border: 2px solid rgba(0,0,0,0.1); box-shadow: 0 2px 4px rgba(0,0,0,0.05);"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="button" class="button makia-select-template" 
                                data-template="<?php echo esc_attr($template_id); ?>"
                                style="width: 100%; padding: 12px; background: <?php echo ($active_template === $template_id) ? 'linear-gradient(135deg, #46b450 0%, #3ea046 100%)' : '#667eea'; ?>; border: none; border-radius: 8px; color: #fff; font-weight: 600; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <?php echo ($active_template === $template_id) ? '✓ Plantilla Activa' : 'Seleccionar'; ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    
                </div>
            </div>
            
            <!-- Sección: Personalización -->
            <div class="makia-design-section" id="makia-section-customize" style="display: none;">
                <h3 style="color: #333; margin-bottom: 20px; font-size: 20px;">Opciones de Personalización Avanzada</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <!-- Columna izquierda: Colores y Logo -->
                    <div>
                        <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 25px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 20px 0; color: #667eea; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 20px;">🎨</span> Colores Personalizados
                            </h4>
                            
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #555; margin-bottom: 8px;">Color Principal</label>
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <input type="color" id="makia-primary-color" value="<?php echo esc_attr($customizations['primary_color'] ?: '#667eea'); ?>" style="width: 60px; height: 40px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer;">
                                    <input type="text" id="makia-primary-color-text" value="<?php echo esc_attr($customizations['primary_color'] ?: '#667eea'); ?>" style="flex: 1; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; font-family: monospace;">
                                </div>
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #999;">Botones, enlaces y elementos destacados</p>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #555; margin-bottom: 8px;">Color Secundario</label>
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <input type="color" id="makia-secondary-color" value="<?php echo esc_attr($customizations['secondary_color'] ?: '#ffffff'); ?>" style="width: 60px; height: 40px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer;">
                                    <input type="text" id="makia-secondary-color-text" value="<?php echo esc_attr($customizations['secondary_color'] ?: '#ffffff'); ?>" style="flex: 1; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; font-family: monospace;">
                                </div>
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #999;">Fondo y elementos secundarios</p>
                            </div>
                            
                            <div>
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #555; margin-bottom: 8px;">Color de Texto</label>
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <input type="color" id="makia-text-color" value="<?php echo esc_attr($customizations['text_color'] ?: '#333333'); ?>" style="width: 60px; height: 40px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer;">
                                    <input type="text" id="makia-text-color-text" value="<?php echo esc_attr($customizations['text_color'] ?: '#333333'); ?>" style="flex: 1; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; font-family: monospace;">
                                </div>
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #999;">Texto principal del formulario</p>
                            </div>
                        </div>
                        
                        <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 25px;">
                            <h4 style="margin: 0 0 20px 0; color: #667eea; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 20px;">🖼️</span> Logo del Restaurante
                            </h4>
                            
                            <div>
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #555; margin-bottom: 8px;">URL del Logo</label>
                                <input type="url" id="makia-logo-url" value="<?php echo esc_attr($customizations['logo_url']); ?>" placeholder="https://..." style="width: 100%; padding: 12px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 8px;">
                                <p style="margin: 0; font-size: 12px; color: #999;">Se mostrará en la parte superior del formulario</p>
                                
                                <?php if ($customizations['logo_url']): ?>
                                <div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 8px; text-align: center;">
                                    <img src="<?php echo esc_url($customizations['logo_url']); ?>" alt="Logo" style="max-width: 150px; max-height: 60px;">
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Columna derecha: Textos -->
                    <div>
                        <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 25px;">
                            <h4 style="margin: 0 0 20px 0; color: #667eea; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 20px;">✏️</span> Textos Personalizados
                            </h4>
                            
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #555; margin-bottom: 8px;">Título del Formulario</label>
                                <input type="text" id="makia-form-title" value="<?php echo esc_attr($customizations['form_title']); ?>" placeholder="Reserva tu Mesa" style="width: 100%; padding: 12px; border: 1px solid #e0e0e0; border-radius: 6px;">
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #999;">Encabezado principal del formulario</p>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #555; margin-bottom: 8px;">Subtítulo</label>
                                <input type="text" id="makia-form-subtitle" value="<?php echo esc_attr($customizations['form_subtitle']); ?>" placeholder="Completa el formulario..." style="width: 100%; padding: 12px; border: 1px solid #e0e0e0; border-radius: 6px;">
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #999;">Texto descriptivo bajo el título</p>
                            </div>
                            
                            <div>
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #555; margin-bottom: 8px;">Texto del Botón</label>
                                <input type="text" id="makia-button-text" value="<?php echo esc_attr($customizations['button_text']); ?>" placeholder="Confirmar Reserva" style="width: 100%; padding: 12px; border: 1px solid #e0e0e0; border-radius: 6px;">
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #999;">Texto del botón de envío</p>
                            </div>
                        </div>
                        
                        <button type="button" id="makia-save-customizations" class="button button-primary" style="width: 100%; margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(102,126,234,0.3); transition: all 0.3s;">
                            💾 Guardar Personalizaciones
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Sección: Vista Previa -->
            <div class="makia-design-section" id="makia-section-preview" style="display: none;">
                <h3 style="color: #333; margin-bottom: 20px; font-size: 20px;">Vista Previa en Tiempo Real</h3>
                
                <div style="background: #f5f5f5; padding: 40px; border-radius: 12px; min-height: 600px; display: flex; align-items: center; justify-content: center;">
                    <div id="makia-live-preview" style="max-width: 100%; transform: scale(0.85); transform-origin: center;">
                        <div class="makia-template-<?php echo esc_attr($active_template); ?>" id="makia-preview-container">
                            <?php if ($customizations['logo_url']): ?>
                            <div class="makia-form-logo" style="text-align: center; margin-bottom: 25px;">
                                <img src="<?php echo esc_url($customizations['logo_url']); ?>" alt="Logo" style="max-width: 180px; max-height: 70px;">
                            </div>
                            <?php endif; ?>
                            
                            <h2 class="makia-form-title"><?php echo esc_html($customizations['form_title']); ?></h2>
                            <p class="makia-form-subtitle"><?php echo esc_html($customizations['form_subtitle']); ?></p>
                            
                            <div class="makia-form-group">
                                <label>Nombre</label>
                                <input type="text" placeholder="Tu nombre" disabled>
                            </div>
                            
                            <div class="makia-form-group">
                                <label>Email</label>
                                <input type="email" placeholder="tu@email.com" disabled>
                            </div>
                            
                            <div class="makia-form-group">
                                <label>Fecha y Hora</label>
                                <input type="text" placeholder="Selecciona fecha y hora" disabled>
                            </div>
                            
                            <button type="button" disabled style="opacity: 0.9;">
                                <?php echo esc_html($customizations['button_text']); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px;">
                    <p style="margin: 0; color: #666; font-size: 14px;">
                        <strong>💡 Tip:</strong> Esta vista previa muestra cómo se verá el formulario en tu sitio web. Los cambios se aplican automáticamente al seleccionar una plantilla o personalizar colores y textos.
                    </p>
                </div>
            </div>
        </div>
        
        <style>
        .makia-design-nav-btn:hover {
            color: #667eea !important;
        }
        .makia-design-nav-btn.active {
            color: #667eea !important;
            border-bottom-color: #667eea !important;
        }
        .makia-template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12) !important;
            border-color: #667eea !important;
        }
        #makia-save-customizations:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102,126,234,0.4) !important;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Navegación entre secciones
            $('.makia-design-nav-btn').on('click', function() {
                var section = $(this).data('section');
                
                // Actualizar navegación
                $('.makia-design-nav-btn').removeClass('active').css({
                    'color': '#666',
                    'border-bottom-color': 'transparent'
                });
                $(this).addClass('active').css({
                    'color': '#667eea',
                    'border-bottom-color': '#667eea'
                });
                
                // Mostrar sección
                $('.makia-design-section').hide();
                $('#makia-section-' + section).fadeIn(300);
            });
            
            // Sincronizar inputs de color
            $('#makia-primary-color').on('input', function() {
                $('#makia-primary-color-text').val($(this).val());
                updatePreviewColors();
            });
            $('#makia-primary-color-text').on('input', function() {
                $('#makia-primary-color').val($(this).val());
                updatePreviewColors();
            });
            
            $('#makia-secondary-color').on('input', function() {
                $('#makia-secondary-color-text').val($(this).val());
                updatePreviewColors();
            });
            $('#makia-secondary-color-text').on('input', function() {
                $('#makia-secondary-color').val($(this).val());
                updatePreviewColors();
            });
            
            $('#makia-text-color').on('input', function() {
                $('#makia-text-color-text').val($(this).val());
                updatePreviewColors();
            });
            $('#makia-text-color-text').on('input', function() {
                $('#makia-text-color').val($(this).val());
                updatePreviewColors();
            });
            
            // Actualizar textos en preview
            $('#makia-form-title, #makia-form-subtitle, #makia-button-text').on('input', function() {
                var title = $('#makia-form-title').val() || 'Reserva tu Mesa';
                var subtitle = $('#makia-form-subtitle').val() || 'Completa el formulario...';
                var buttonText = $('#makia-button-text').val() || 'Confirmar Reserva';
                
                $('#makia-preview-container .makia-form-title').text(title);
                $('#makia-preview-container .makia-form-subtitle').text(subtitle);
                $('#makia-preview-container button').text(buttonText);
            });
            
            // Actualizar colores en preview
            function updatePreviewColors() {
                var primary = $('#makia-primary-color').val();
                var secondary = $('#makia-secondary-color').val();
                var text = $('#makia-text-color').val();
                
                var $preview = $('#makia-preview-container');
                
                if (primary) {
                    $preview.find('button').css('background', primary);
                    $preview.find('input:focus, select:focus, textarea:focus').css('border-color', primary);
                }
                if (secondary) {
                    $preview.css('background', secondary);
                }
                if (text) {
                    $preview.find('label, .makia-form-title').css('color', text);
                }
            }
            
            // Seleccionar plantilla
            $('.makia-select-template').on('click', function() {
                var $button = $(this);
                var templateId = $button.data('template');
                var $card = $button.closest('.makia-template-card');
                
                $button.prop('disabled', true).text('Aplicando...');
                
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
                            $('.makia-template-card .makia-active-badge').remove();
                            $('.makia-select-template').css('background', '#667eea').text('Seleccionar');
                            
                            $card.addClass('active').css('border-color', '#667eea');
                            $card.prepend('<div class="makia-active-badge" style="position: absolute; top: 15px; right: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; box-shadow: 0 2px 8px rgba(102,126,234,0.3);">✓ ACTIVA</div>');
                            $button.css('background', 'linear-gradient(135deg, #46b450 0%, #3ea046 100%)').text('✓ Plantilla Activa');
                            
                            // Actualizar preview
                            $('#makia-preview-container').attr('class', 'makia-template-' + templateId);
                            
                            // Notificación
                            showNotification('Plantilla aplicada correctamente', 'success');
                        } else {
                            showNotification('Error: ' + response.data.message, 'error');
                            $button.prop('disabled', false).text('Seleccionar');
                        }
                    },
                    error: function() {
                        showNotification('Error al guardar la plantilla', 'error');
                        $button.prop('disabled', false).text('Seleccionar');
                    }
                });
            });
            
            // Guardar personalizaciones
            $('#makia-save-customizations').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Guardando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'makia_save_customizations',
                        primary_color: $('#makia-primary-color').val(),
                        secondary_color: $('#makia-secondary-color').val(),
                        text_color: $('#makia-text-color').val(),
                        logo_url: $('#makia-logo-url').val(),
                        form_title: $('#makia-form-title').val(),
                        form_subtitle: $('#makia-form-subtitle').val(),
                        button_text: $('#makia-button-text').val(),
                        nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('Personalizaciones guardadas correctamente', 'success');
                            $button.text('💾 Guardar Personalizaciones');
                        } else {
                            showNotification('Error: ' + response.data.message, 'error');
                        }
                        $button.prop('disabled', false);
                    },
                    error: function() {
                        showNotification('Error al guardar', 'error');
                        $button.prop('disabled', false).text('💾 Guardar Personalizaciones');
                    }
                });
            });
            
            // Sistema de notificaciones
            function showNotification(message, type) {
                var bgColor = type === 'success' ? '#46b450' : '#dc3232';
                var $notification = $('<div>')
                    .css({
                        'position': 'fixed',
                        'top': '20px',
                        'right': '20px',
                        'background': bgColor,
                        'color': '#fff',
                        'padding': '15px 25px',
                        'border-radius': '8px',
                        'box-shadow': '0 4px 15px rgba(0,0,0,0.2)',
                        'z-index': '999999',
                        'font-weight': '600',
                        'animation': 'slideInRight 0.3s ease'
                    })
                    .text(message)
                    .appendTo('body');
                
                setTimeout(function() {
                    $notification.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        });
        </script>
        
        <?php
    }
}

// Inicializar
new MakIA_Design();
