<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Shortcodes de MakIA
 * Maneja la renderización del formulario de reservas
 */

class MakIA_Shortcodes {
    
    public function __construct() {
        add_shortcode('makia_reservas', array($this, 'render_booking_form'));
        add_shortcode('makia_gestionar_reserva', array($this, 'render_manage_booking'));
        add_shortcode('makia_boton_reserva', array($this, 'render_booking_button'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Cargar scripts y estilos (detección temprana via has_shortcode)
     */
    public function enqueue_scripts() {
        $post = get_post();
        if ($post && has_shortcode($post->post_content, 'makia_reservas')) {
            self::enqueue_modal_assets();
        }
    }

    /**
     * Encolar los assets del modal de reservas (reutilizable)
     */
    public static function enqueue_modal_assets() {
        wp_enqueue_style('makia-styles', MAKIA_PLUGIN_URL . 'assets/css/makia-styles.css', array(), MAKIA_VERSION);
        wp_enqueue_style('makia-modal-mobile', MAKIA_PLUGIN_URL . 'assets/css/makia-modal-mobile.css', array('makia-styles'), MAKIA_VERSION);
        wp_enqueue_script('makia-booking', MAKIA_PLUGIN_URL . 'assets/js/makia-booking.js', array('jquery'), MAKIA_VERSION, true);

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
    }
    
    /**
     * Renderizar formulario de reservas
     */
    public function render_booking_form($atts) {
        // Verificar licencia
        global $makia_license_manager;
        if (!$makia_license_manager->is_license_active()) {
            return '<div class="makia-error">El plugin MakIA no está activado. Por favor, contacta con el administrador del sitio.</div>';
        }

        // Fallback: encolar assets si has_shortcode() no los detectó
        // (ej. shortcode via page builder, widget, o template)
        self::enqueue_modal_assets();

        $GLOBALS['makia_modal_rendered'] = true;

        $atts = shortcode_atts(array(
            'theme' => 'light'
        ), $atts);

        ob_start();
        include MAKIA_PLUGIN_DIR . 'templates/booking-form.php';
        return ob_get_clean();
    }
    
    /**
     * Renderizar página de gestión de reservas
     */
    public function render_manage_booking($atts) {
        // Verificar licencia
        global $makia_license_manager;
        if (!$makia_license_manager->is_license_active()) {
            return '<div class="makia-error">El plugin MakIA no está activado. Por favor, contacta con el administrador del sitio.</div>';
        }

        ob_start();
        include MAKIA_PLUGIN_DIR . 'templates/manage-booking.php';
        return ob_get_clean();
    }
    
    /**
     * Renderizar botón de reservas
     * 
     * Uso: [makia_boton_reserva]
     * 
     * Parámetros opcionales:
     * - text: Texto del botón (default: configuración del plugin)
     * - size: small, medium, large (default: configuración del plugin)
     * - style: solid, outline, gradient (default: configuración del plugin)
     * - url: URL de destino personalizada
     * - icon: true/false para mostrar/ocultar icono
     * - class: Clases CSS adicionales
     */
    public function render_booking_button($atts) {
        global $makia_button_settings;
        
        // Si no existe la clase de configuración, crearla
        if (!isset($makia_button_settings) || !$makia_button_settings) {
            if (class_exists('MakIA_Button_Settings')) {
                $makia_button_settings = new MakIA_Button_Settings();
            } else {
                return '<!-- MakIA Button: Configuración no disponible -->';
            }
        }
        
        // Procesar atributos
        $atts = shortcode_atts(array(
            'text' => '',
            'size' => '',
            'style' => '',
            'url' => '',
            'icon' => 'true',
            'class' => ''
        ), $atts);
        
        // Convertir icon a booleano
        $atts['icon'] = filter_var($atts['icon'], FILTER_VALIDATE_BOOLEAN);
        
        // Cargar estilos del botón si no están cargados
        wp_enqueue_style(
            'makia-button-styles',
            MAKIA_PLUGIN_URL . 'assets/css/makia-button.css',
            array(),
            MAKIA_VERSION
        );
        
        // Renderizar botón
        return $makia_button_settings->render_button($atts);
    }
}
