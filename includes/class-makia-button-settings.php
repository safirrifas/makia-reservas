<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Configuración del Botón de Reservas MakIA
 * Gestiona las opciones de personalización y el botón flotante
 */

class MakIA_Button_Settings {
    
    /**
     * Opciones por defecto del botón
     */
    private $defaults = array(
        'makia_button_enabled' => true,
        'makia_button_color' => '#2c5530',
        'makia_button_text_color' => '#ffffff',
        'makia_button_text' => 'Reservar Mesa',
        'makia_button_size' => 'medium',
        'makia_button_style' => 'solid',
        'makia_button_border_radius' => '8',
        'makia_button_url' => '',
        'makia_floating_enabled' => true,
        'makia_floating_position' => 'left',
        'makia_floating_scroll_offset' => '0',
        'makia_floating_mobile' => true,
        'makia_floating_icon' => 'calendar',
        'makia_floating_animation' => 'fade'
    );
    
    public function __construct() {
        // Registrar opciones
        add_action('admin_init', array($this, 'register_settings'));

        // Agregar botón flotante al frontend
        add_action('wp_footer', array($this, 'render_floating_button'));

        // Renderizar modal SIEMPRE en el footer (independiente del botón flotante)
        add_action('wp_footer', array($this, 'ensure_modal_rendered'), 5);

        // Cargar assets del botón
        add_action('wp_enqueue_scripts', array($this, 'enqueue_button_assets'));
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        $sanitize_map = array(
            'makia_button_text' => 'sanitize_text_field',
            'makia_button_url' => 'esc_url_raw',
            'makia_button_color' => 'sanitize_hex_color',
            'makia_button_text_color' => 'sanitize_hex_color',
            'makia_button_size' => 'sanitize_text_field',
            'makia_button_style' => 'sanitize_text_field',
            'makia_button_border_radius' => 'absint',
            'makia_button_icon' => 'sanitize_text_field',
            'makia_floating_enabled' => 'sanitize_text_field',
            'makia_floating_position' => 'sanitize_text_field',
            'makia_floating_animation' => 'sanitize_text_field',
        );
        foreach ($this->defaults as $option => $default) {
            $callback = isset($sanitize_map[$option]) ? $sanitize_map[$option] : 'sanitize_text_field';
            register_setting('makia_button_options', $option, array('sanitize_callback' => $callback));
        }
    }
    
    /**
     * Obtener opción con valor por defecto
     */
    public function get_option($key) {
        $default = isset($this->defaults[$key]) ? $this->defaults[$key] : '';
        return get_option($key, $default);
    }
    
    /**
     * Obtener todas las opciones del botón
     */
    public function get_all_options() {
        $options = array();
        foreach ($this->defaults as $key => $default) {
            $options[$key] = get_option($key, $default);
        }
        return $options;
    }
    
    /**
     * Cargar assets del botón en el frontend
     * Siempre carga los assets del modal para que esté disponible en todas las páginas
     */
    public function enqueue_button_assets() {
        $floating_enabled = $this->get_option('makia_floating_enabled');

        wp_enqueue_style(
            'makia-button-styles',
            MAKIA_PLUGIN_URL . 'assets/css/makia-button.css',
            array(),
            MAKIA_VERSION
        );

        // SIEMPRE cargar los assets del modal en todas las páginas del frontend
        wp_enqueue_style('makia-styles', MAKIA_PLUGIN_URL . 'assets/css/makia-styles.css', array(), MAKIA_VERSION);
        wp_enqueue_style('makia-modal-mobile', MAKIA_PLUGIN_URL . 'assets/css/makia-modal-mobile.css', array('makia-styles'), MAKIA_VERSION);

        wp_enqueue_script(
            'makia-booking',
            MAKIA_PLUGIN_URL . 'assets/js/makia-booking.js',
            array('jquery'),
            MAKIA_VERSION,
            true
        );

        // Localizar makiaConfig para makia-booking.js
        if (!isset($GLOBALS['makia_config_localized'])) {
            $special_days = get_option('makia_special_days', array());
            if (is_string($special_days)) {
                $special_days = maybe_unserialize($special_days);
            }
            if (!is_array($special_days)) {
                $special_days = array();
            }

            wp_localize_script('makia-booking', 'makiaConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('makia_booking_nonce'),
                'apiUrl' => get_option('makia_api_url', MAKIA_API_URL),
                'restaurantName' => get_option('makia_restaurant_name'),
                'businessHours' => get_option('makia_business_hours'),
                'specialDays' => $special_days,
                'maxCapacity' => get_option('makia_max_capacity', 50),
                'maxGuestsPerReservation' => get_option('makia_max_guests_per_reservation', 20)
            ));
            $GLOBALS['makia_config_localized'] = true;
        }

        // Cargar scripts del botón flotante si está habilitado
        if ($floating_enabled) {
            wp_enqueue_script(
                'makia-floating-button',
                MAKIA_PLUGIN_URL . 'assets/js/makia-floating-button.js',
                array('jquery', 'makia-booking'),
                MAKIA_VERSION,
                true
            );

            wp_localize_script('makia-floating-button', 'makiaButtonOptions', array(
                'scrollOffset' => intval($this->get_option('makia_floating_scroll_offset')),
                'position' => $this->get_option('makia_floating_position'),
                'animation' => $this->get_option('makia_floating_animation'),
                'showOnMobile' => $this->get_option('makia_floating_mobile')
            ));
        }

        // Agregar estilos inline personalizados
        $this->add_custom_styles();
    }

    /**
     * Asegurar que el modal HTML esté siempre en el DOM
     * Se ejecuta en wp_footer con prioridad 5 (antes del botón flotante)
     */
    public function ensure_modal_rendered() {
        if (!empty($GLOBALS['makia_modal_rendered'])) {
            return;
        }
        $GLOBALS['makia_modal_rendered'] = true;
        $GLOBALS['makia_skip_inline_button'] = true;
        include MAKIA_PLUGIN_DIR . 'templates/booking-form.php';
    }
    
    /**
     * Agregar estilos CSS personalizados
     */
    private function add_custom_styles() {
        $color = sanitize_hex_color($this->get_option('makia_button_color')) ?: '#2c5530';
        $text_color = sanitize_hex_color($this->get_option('makia_button_text_color')) ?: '#ffffff';
        $border_radius = intval($this->get_option('makia_button_border_radius'));
        $style = $this->get_option('makia_button_style');
        
        $custom_css = "
            .makia-booking-button,
            .makia-floating-button {
                --makia-btn-color: {$color};
                --makia-btn-text-color: {$text_color};
                --makia-btn-radius: {$border_radius}px;
            }
        ";
        
        // Estilos según el tipo
        if ($style === 'outline') {
            $custom_css .= "
                .makia-booking-button.makia-style-outline,
                .makia-floating-button.makia-style-outline {
                    background: transparent;
                    border: 2px solid {$color};
                    color: {$color};
                }
                .makia-booking-button.makia-style-outline:hover,
                .makia-floating-button.makia-style-outline:hover {
                    background: {$color};
                    color: {$text_color};
                }
            ";
        } elseif ($style === 'gradient') {
            $custom_css .= "
                .makia-booking-button.makia-style-gradient,
                .makia-floating-button.makia-style-gradient {
                    background: linear-gradient(135deg, {$color} 0%, " . $this->adjust_brightness($color, -30) . " 100%);
                }
                .makia-booking-button.makia-style-gradient:hover,
                .makia-floating-button.makia-style-gradient:hover {
                    background: linear-gradient(135deg, " . $this->adjust_brightness($color, 10) . " 0%, {$color} 100%);
                }
            ";
        }
        
        wp_add_inline_style('makia-button-styles', $custom_css);
    }
    
    /**
     * Ajustar brillo de un color hex
     */
    private function adjust_brightness($hex, $percent) {
        $hex = ltrim($hex, '#');
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r + ($r * $percent / 100)));
        $g = max(0, min(255, $g + ($g * $percent / 100)));
        $b = max(0, min(255, $b + ($b * $percent / 100)));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    /**
     * Renderizar el botón de reservas
     */
    public function render_button($atts = array()) {
        $defaults = array(
            'text' => $this->get_option('makia_button_text'),
            'size' => $this->get_option('makia_button_size'),
            'style' => $this->get_option('makia_button_style'),
            'url' => $this->get_option('makia_button_url'),
            'class' => '',
            'icon' => true
        );
        
        $atts = shortcode_atts($defaults, $atts);
        
        // Determinar URL del botón
        $url = !empty($atts['url']) ? esc_url($atts['url']) : $this->get_booking_page_url();
        
        // Construir clases CSS
        $classes = array(
            'makia-booking-button',
            'makia-size-' . sanitize_html_class($atts['size']),
            'makia-style-' . sanitize_html_class($atts['style'])
        );
        
        if (!empty($atts['class'])) {
            $classes[] = sanitize_html_class($atts['class']);
        }
        
        // Icono
        $icon_html = '';
        if ($atts['icon']) {
            $icon_html = '<span class="makia-btn-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </span>';
        }
        
        $html = sprintf(
            '<a href="%s" class="%s">%s<span class="makia-btn-text">%s</span></a>',
            esc_url($url),
            esc_attr(implode(' ', $classes)),
            $icon_html,
            esc_html($atts['text'])
        );
        
        return $html;
    }
    
    /**
     * Renderizar botón flotante
     */
    public function render_floating_button() {
        if (!$this->get_option('makia_floating_enabled')) {
            return;
        }

        $position = $this->get_option('makia_floating_position');
        $style = $this->get_option('makia_button_style');
        $text = $this->get_option('makia_button_text');
        $url = $this->get_option('makia_button_url');
        $animation = $this->get_option('makia_floating_animation');

        if (empty($url)) {
            $url = $this->get_booking_page_url();
        }

        $classes = array(
            'makia-floating-button',
            'makia-floating-' . sanitize_html_class($position),
            'makia-style-' . sanitize_html_class($style),
            'makia-animation-' . sanitize_html_class($animation),
            'makia-floating-hidden'
        );

        // Icono del calendario
        $icon_html = '<span class="makia-floating-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </span>';

        ?>
        <a href="#makia-booking-modal"
           class="<?php echo esc_attr(implode(' ', $classes)); ?>"
           id="makia-floating-btn"
           data-makia-open-modal="true"
           aria-label="<?php echo esc_attr($text); ?>">
            <?php echo $icon_html; ?>
            <span class="makia-floating-text"><?php echo esc_html($text); ?></span>
        </a>
        <?php
    }
    
    /**
     * Obtener URL de la página de reservas
     */
    private function get_booking_page_url() {
        // Buscar página con shortcode de reservas
        $pages = get_pages();
        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, 'makia_reservas') || 
                has_shortcode($page->post_content, 'makia_booking')) {
                return get_permalink($page->ID);
            }
        }
        
        // Fallback: página de reservas por slug común
        $booking_page = get_page_by_path('reservas');
        if ($booking_page) {
            return get_permalink($booking_page->ID);
        }
        
        $booking_page = get_page_by_path('reservar');
        if ($booking_page) {
            return get_permalink($booking_page->ID);
        }
        
        // Último fallback: home con anchor
        return home_url('/#reservas');
    }
    
    /**
     * Renderizar panel de configuración del botón
     */
    public function render_settings_page() {
        $options = $this->get_all_options();
        ?>
        <div class="makia-button-settings">
            <h2>Configuración del Botón de Reservas</h2>
            
            <div class="makia-settings-grid">
                <!-- Columna de configuración -->
                <div class="makia-settings-column">
                    <h3>Apariencia del Botón</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="makia_button_text">Texto del botón</label></th>
                            <td>
                                <input type="text" 
                                       id="makia_button_text" 
                                       name="makia_button_text" 
                                       value="<?php echo esc_attr($options['makia_button_text']); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="makia_button_color">Color del botón</label></th>
                            <td>
                                <input type="color" 
                                       id="makia_button_color" 
                                       name="makia_button_color" 
                                       value="<?php echo esc_attr($options['makia_button_color']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="makia_button_text_color">Color del texto</label></th>
                            <td>
                                <input type="color" 
                                       id="makia_button_text_color" 
                                       name="makia_button_text_color" 
                                       value="<?php echo esc_attr($options['makia_button_text_color']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="makia_button_size">Tamaño</label></th>
                            <td>
                                <select id="makia_button_size" name="makia_button_size">
                                    <option value="small" <?php selected($options['makia_button_size'], 'small'); ?>>Pequeño</option>
                                    <option value="medium" <?php selected($options['makia_button_size'], 'medium'); ?>>Mediano</option>
                                    <option value="large" <?php selected($options['makia_button_size'], 'large'); ?>>Grande</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="makia_button_style">Estilo</label></th>
                            <td>
                                <select id="makia_button_style" name="makia_button_style">
                                    <option value="solid" <?php selected($options['makia_button_style'], 'solid'); ?>>Sólido</option>
                                    <option value="outline" <?php selected($options['makia_button_style'], 'outline'); ?>>Contorno</option>
                                    <option value="gradient" <?php selected($options['makia_button_style'], 'gradient'); ?>>Gradiente</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="makia_button_border_radius">Bordes redondeados (px)</label></th>
                            <td>
                                <input type="number" 
                                       id="makia_button_border_radius" 
                                       name="makia_button_border_radius" 
                                       value="<?php echo esc_attr($options['makia_button_border_radius']); ?>" 
                                       min="0" 
                                       max="50"
                                       class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="makia_button_url">URL de destino</label></th>
                            <td>
                                <input type="url" 
                                       id="makia_button_url" 
                                       name="makia_button_url" 
                                       value="<?php echo esc_url($options['makia_button_url']); ?>" 
                                       class="regular-text"
                                       placeholder="Dejar vacío para detectar automáticamente">
                                <p class="description">URL de la página de reservas. Si se deja vacío, se detectará automáticamente.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>Botón Flotante</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="makia_floating_enabled">Activar botón flotante</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="makia_floating_enabled" 
                                           name="makia_floating_enabled" 
                                           value="1" 
                                           <?php checked($options['makia_floating_enabled'], true); ?>>
                                    Mostrar botón flotante en todas las páginas
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="makia_floating_position">Posición</label></th>
                            <td>
                                <select id="makia_floating_position" name="makia_floating_position">
                                    <option value="right" <?php selected($options['makia_floating_position'], 'right'); ?>>Derecha</option>
                                    <option value="left" <?php selected($options['makia_floating_position'], 'left'); ?>>Izquierda</option>
                                    <option value="center" <?php selected($options['makia_floating_position'], 'center'); ?>>Centro (abajo)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="makia_floating_scroll_offset">Aparecer después de (px)</label></th>
                            <td>
                                <input type="number" 
                                       id="makia_floating_scroll_offset" 
                                       name="makia_floating_scroll_offset" 
                                       value="<?php echo esc_attr($options['makia_floating_scroll_offset']); ?>" 
                                       min="0" 
                                       class="small-text">
                                <p class="description">Píxeles de scroll antes de mostrar el botón. Usar 0 para mostrar inmediatamente.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="makia_floating_animation">Animación</label></th>
                            <td>
                                <select id="makia_floating_animation" name="makia_floating_animation">
                                    <option value="fade" <?php selected($options['makia_floating_animation'], 'fade'); ?>>Desvanecer</option>
                                    <option value="slide" <?php selected($options['makia_floating_animation'], 'slide'); ?>>Deslizar</option>
                                    <option value="scale" <?php selected($options['makia_floating_animation'], 'scale'); ?>>Escalar</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="makia_floating_mobile">Mostrar en móvil</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="makia_floating_mobile" 
                                           name="makia_floating_mobile" 
                                           value="1" 
                                           <?php checked($options['makia_floating_mobile'], true); ?>>
                                    Mostrar botón flotante en dispositivos móviles
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>Uso del Shortcode</h3>
                    <div class="makia-shortcode-info">
                        <p>Usa el siguiente shortcode para insertar el botón en cualquier página o entrada:</p>
                        <code>[makia_boton_reserva]</code>
                        
                        <p style="margin-top: 15px;"><strong>Parámetros opcionales:</strong></p>
                        <ul>
                            <li><code>text="Reservar Ahora"</code> - Texto personalizado</li>
                            <li><code>size="small|medium|large"</code> - Tamaño del botón</li>
                            <li><code>style="solid|outline|gradient"</code> - Estilo visual</li>
                            <li><code>url="https://..."</code> - URL de destino personalizada</li>
                            <li><code>icon="true|false"</code> - Mostrar/ocultar icono</li>
                        </ul>
                        
                        <p style="margin-top: 15px;"><strong>Ejemplo completo:</strong></p>
                        <code>[makia_boton_reserva text="Reserva tu mesa" size="large" style="gradient"]</code>
                    </div>
                </div>
                
                <!-- Columna de vista previa -->
                <div class="makia-preview-column">
                    <h3>Vista Previa</h3>
                    <div class="makia-button-preview" id="makia-button-preview">
                        <div class="preview-container">
                            <p>Botón normal:</p>
                            <div id="preview-button-normal"></div>
                            
                            <p style="margin-top: 20px;">Botón flotante (simulación):</p>
                            <div id="preview-button-floating"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .makia-settings-grid {
                display: grid;
                grid-template-columns: 1fr 300px;
                gap: 30px;
                margin-top: 20px;
            }
            .makia-preview-column {
                background: #f5f5f5;
                padding: 20px;
                border-radius: 8px;
                position: sticky;
                top: 50px;
                height: fit-content;
            }
            .makia-button-preview .preview-container {
                background: #fff;
                padding: 30px;
                border-radius: 8px;
                text-align: center;
            }
            .makia-shortcode-info {
                background: #f0f0f1;
                padding: 15px;
                border-radius: 4px;
                margin-top: 10px;
            }
            .makia-shortcode-info code {
                background: #fff;
                padding: 4px 8px;
                border-radius: 3px;
                display: inline-block;
            }
            .makia-shortcode-info ul {
                margin-left: 20px;
            }
            @media (max-width: 1200px) {
                .makia-settings-grid {
                    grid-template-columns: 1fr;
                }
                .makia-preview-column {
                    position: static;
                }
            }
        </style>
        <?php
    }
}

// Inicializar
if (!isset($GLOBALS['makia_button_settings'])) {
    $GLOBALS['makia_button_settings'] = new MakIA_Button_Settings();
}
