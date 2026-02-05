<?php
/**
 * Plugin Name: MakIA Restaurante Client
 * Plugin URI: https://makia.app
 * Description: Cliente ligero de MakIA Restaurante que conecta con la plataforma cloud. Widget de reservas, sincronización automática y gestión desde el dashboard centralizado.
 * Version: 1.0.0
 * Author: MakIA Team
 * Author URI: https://makia.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: makia-client
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * ============================================================================
 * CONFIGURACIÓN
 * ============================================================================
 *
 * 1. Obtén tu API Key en https://dashboard.makia.app/settings/api-keys
 * 2. Configura en Ajustes > MakIA Restaurante
 *
 * O define en wp-config.php:
 *   define('MAKIA_API_KEY', 'mk_live_xxx');
 *   define('MAKIA_ORGANIZATION', 'mi-restaurante');
 *
 * ============================================================================
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constantes del plugin
define( 'MAKIA_CLIENT_VERSION', '1.0.0' );
define( 'MAKIA_CLIENT_FILE', __FILE__ );
define( 'MAKIA_CLIENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAKIA_CLIENT_URL', plugin_dir_url( __FILE__ ) );
define( 'MAKIA_API_BASE', 'https://api.makia.app/v1' );

/**
 * Clase principal del plugin
 */
class MakIA_Client {

    /**
     * Instancia singleton
     */
    private static $instance = null;

    /**
     * API Key
     */
    private $api_key;

    /**
     * Organization slug
     */
    private $organization;

    /**
     * Obtener instancia
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->api_key = defined( 'MAKIA_API_KEY' ) ? MAKIA_API_KEY : get_option( 'makia_api_key', '' );
        $this->organization = defined( 'MAKIA_ORGANIZATION' ) ? MAKIA_ORGANIZATION : get_option( 'makia_organization', '' );

        // Hooks
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Shortcodes
        add_shortcode( 'makia_booking', array( $this, 'booking_shortcode' ) );
        add_shortcode( 'makia_widget', array( $this, 'widget_shortcode' ) );

        // AJAX handlers
        add_action( 'wp_ajax_makia_proxy', array( $this, 'api_proxy' ) );
        add_action( 'wp_ajax_nopriv_makia_proxy', array( $this, 'api_proxy' ) );

        // Widget
        add_action( 'widgets_init', array( $this, 'register_widget' ) );
    }

    /**
     * Inicializar
     */
    public function init() {
        load_plugin_textdomain( 'makia-client', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Verificar si está configurado
     */
    public function is_configured() {
        return ! empty( $this->api_key ) && ! empty( $this->organization );
    }

    /**
     * Menú de administración
     */
    public function admin_menu() {
        add_options_page(
            __( 'MakIA Restaurante', 'makia-client' ),
            __( 'MakIA Restaurante', 'makia-client' ),
            'manage_options',
            'makia-settings',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Registrar ajustes
     */
    public function register_settings() {
        register_setting( 'makia_settings', 'makia_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'makia_settings', 'makia_organization', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'makia_settings', 'makia_widget_theme', array(
            'type' => 'string',
            'default' => 'light',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'makia_settings', 'makia_primary_color', array(
            'type' => 'string',
            'default' => '#4f46e5',
            'sanitize_callback' => 'sanitize_hex_color',
        ) );
    }

    /**
     * Página de ajustes
     */
    public function settings_page() {
        // Verificar conexión
        $connection_status = $this->test_connection();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'MakIA Restaurante', 'makia-client' ); ?></h1>

            <?php if ( $connection_status['success'] ) : ?>
                <div class="notice notice-success">
                    <p>
                        <strong><?php esc_html_e( 'Conectado correctamente', 'makia-client' ); ?></strong> -
                        <?php echo esc_html( $connection_status['organization_name'] ); ?>
                        (<?php echo esc_html( $connection_status['plan'] ); ?>)
                    </p>
                </div>
            <?php elseif ( $this->is_configured() ) : ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php esc_html_e( 'Error de conexión', 'makia-client' ); ?></strong>:
                        <?php echo esc_html( $connection_status['message'] ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'makia_settings' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="makia_api_key"><?php esc_html_e( 'API Key', 'makia-client' ); ?></label>
                        </th>
                        <td>
                            <input type="password" name="makia_api_key" id="makia_api_key"
                                   value="<?php echo esc_attr( get_option( 'makia_api_key' ) ); ?>"
                                   class="regular-text" autocomplete="off">
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: dashboard URL */
                                    esc_html__( 'Obtén tu API Key en %s', 'makia-client' ),
                                    '<a href="https://dashboard.makia.app/settings/api-keys" target="_blank">dashboard.makia.app</a>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="makia_organization"><?php esc_html_e( 'Organización (slug)', 'makia-client' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="makia_organization" id="makia_organization"
                                   value="<?php echo esc_attr( get_option( 'makia_organization' ) ); ?>"
                                   class="regular-text" placeholder="mi-restaurante">
                            <p class="description">
                                <?php esc_html_e( 'El identificador único de tu restaurante en MakIA', 'makia-client' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="makia_widget_theme"><?php esc_html_e( 'Tema del widget', 'makia-client' ); ?></label>
                        </th>
                        <td>
                            <select name="makia_widget_theme" id="makia_widget_theme">
                                <option value="light" <?php selected( get_option( 'makia_widget_theme', 'light' ), 'light' ); ?>>
                                    <?php esc_html_e( 'Claro', 'makia-client' ); ?>
                                </option>
                                <option value="dark" <?php selected( get_option( 'makia_widget_theme' ), 'dark' ); ?>>
                                    <?php esc_html_e( 'Oscuro', 'makia-client' ); ?>
                                </option>
                                <option value="auto" <?php selected( get_option( 'makia_widget_theme' ), 'auto' ); ?>>
                                    <?php esc_html_e( 'Automático', 'makia-client' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="makia_primary_color"><?php esc_html_e( 'Color primario', 'makia-client' ); ?></label>
                        </th>
                        <td>
                            <input type="color" name="makia_primary_color" id="makia_primary_color"
                                   value="<?php echo esc_attr( get_option( 'makia_primary_color', '#4f46e5' ) ); ?>">
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php esc_html_e( 'Uso', 'makia-client' ); ?></h2>

            <h3><?php esc_html_e( 'Shortcode', 'makia-client' ); ?></h3>
            <p><?php esc_html_e( 'Añade el formulario de reservas en cualquier página:', 'makia-client' ); ?></p>
            <code>[makia_booking]</code>

            <h3><?php esc_html_e( 'Widget', 'makia-client' ); ?></h3>
            <p><?php esc_html_e( 'También puedes usar el widget de MakIA Restaurante en cualquier área de widgets.', 'makia-client' ); ?></p>

            <h3><?php esc_html_e( 'Dashboard', 'makia-client' ); ?></h3>
            <p>
                <?php esc_html_e( 'Gestiona todas tus reservas desde el dashboard centralizado:', 'makia-client' ); ?>
                <a href="https://dashboard.makia.app" target="_blank" class="button button-secondary">
                    <?php esc_html_e( 'Ir al Dashboard', 'makia-client' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Test de conexión con la API
     */
    private function test_connection() {
        if ( ! $this->is_configured() ) {
            return array(
                'success' => false,
                'message' => __( 'API Key y organización requeridos', 'makia-client' ),
            );
        }

        $response = $this->api_request( '/organizations/current' );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        if ( ! $response['success'] ) {
            return array(
                'success' => false,
                'message' => $response['error']['message'] ?? __( 'Error desconocido', 'makia-client' ),
            );
        }

        return array(
            'success'           => true,
            'organization_name' => $response['data']['name'],
            'plan'              => $response['data']['plan'],
        );
    }

    /**
     * Realizar petición a la API
     */
    public function api_request( $endpoint, $method = 'GET', $body = null ) {
        $url = MAKIA_API_BASE . $endpoint;

        // Añadir organización como query param si no está en el endpoint
        if ( strpos( $endpoint, 'org=' ) === false && strpos( $endpoint, '/organizations/' ) === false ) {
            $separator = strpos( $endpoint, '?' ) !== false ? '&' : '?';
            $url .= $separator . 'org=' . urlencode( $this->organization );
        }

        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
        );

        if ( $body && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body, true );
    }

    /**
     * Proxy AJAX para la API
     */
    public function api_proxy() {
        // Verificar nonce
        if ( ! check_ajax_referer( 'makia_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
        }

        $endpoint = isset( $_POST['endpoint'] ) ? sanitize_text_field( $_POST['endpoint'] ) : '';
        $method   = isset( $_POST['method'] ) ? sanitize_text_field( $_POST['method'] ) : 'GET';
        $data     = isset( $_POST['data'] ) ? json_decode( stripslashes( $_POST['data'] ), true ) : null;

        if ( empty( $endpoint ) ) {
            wp_send_json_error( array( 'message' => 'Endpoint required' ), 400 );
        }

        // Solo permitir ciertos endpoints públicos
        $allowed_public = array( '/availability', '/bookings' );
        $is_public      = false;
        foreach ( $allowed_public as $public ) {
            if ( strpos( $endpoint, $public ) === 0 ) {
                $is_public = true;
                break;
            }
        }

        if ( ! $is_public && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 401 );
        }

        $response = $this->api_request( $endpoint, $method, $data );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ), 500 );
        }

        wp_send_json( $response );
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if ( ! $this->is_configured() ) {
            return;
        }

        // Encolar widget desde CDN
        wp_enqueue_script(
            'makia-widget',
            'https://cdn.makia.app/widget.js',
            array(),
            MAKIA_CLIENT_VERSION,
            true
        );

        // Configuración local
        wp_localize_script( 'makia-widget', 'makiaClientConfig', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'makia_nonce' ),
            'organization' => $this->organization,
            'theme'        => get_option( 'makia_widget_theme', 'light' ),
            'primaryColor' => get_option( 'makia_primary_color', '#4f46e5' ),
        ) );
    }

    /**
     * Shortcode [makia_booking]
     */
    public function booking_shortcode( $atts ) {
        if ( ! $this->is_configured() ) {
            if ( current_user_can( 'manage_options' ) ) {
                return '<p>' . sprintf(
                    /* translators: %s: settings URL */
                    esc_html__( 'MakIA Restaurante no está configurado. %s', 'makia-client' ),
                    '<a href="' . admin_url( 'options-general.php?page=makia-settings' ) . '">' . esc_html__( 'Configurar', 'makia-client' ) . '</a>'
                ) . '</p>';
            }
            return '';
        }

        $atts = shortcode_atts( array(
            'theme'         => get_option( 'makia_widget_theme', 'light' ),
            'primary_color' => get_option( 'makia_primary_color', '#4f46e5' ),
        ), $atts, 'makia_booking' );

        return sprintf(
            '<div id="makia-booking" data-restaurant="%s" data-theme="%s" data-primary-color="%s"></div>',
            esc_attr( $this->organization ),
            esc_attr( $atts['theme'] ),
            esc_attr( $atts['primary_color'] )
        );
    }

    /**
     * Shortcode [makia_widget] - versión compacta
     */
    public function widget_shortcode( $atts ) {
        return $this->booking_shortcode( $atts );
    }

    /**
     * Registrar widget de WordPress
     */
    public function register_widget() {
        require_once MAKIA_CLIENT_DIR . 'includes/class-makia-widget.php';
        register_widget( 'MakIA_Booking_Widget' );
    }
}

// Inicializar plugin
function makia_client() {
    return MakIA_Client::get_instance();
}

// Arrancar
add_action( 'plugins_loaded', 'makia_client' );

// Activación
register_activation_hook( __FILE__, function() {
    // Crear opciones por defecto
    add_option( 'makia_widget_theme', 'light' );
    add_option( 'makia_primary_color', '#4f46e5' );
});

// Desactivación
register_deactivation_hook( __FILE__, function() {
    // Limpiar transients
    delete_transient( 'makia_connection_status' );
});
