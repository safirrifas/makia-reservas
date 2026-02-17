<?php
/**
 * Plugin Name: MakIA Restaurante Client
 * Plugin URI: https://contacpro.app
 * Description: Cliente ligero de MakIA Restaurante que conecta con la plataforma cloud. Widget de reservas, sincronización automática y gestión desde el dashboard centralizado.
 * Version: 3.4.0
 * Author: MakIA Team
 * Author URI: https://contacpro.app
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
 * 1. Obtén tu API Key en https://dashboard.contacpro.app/settings/api-keys
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
define( 'MAKIA_CLIENT_VERSION', '3.4.0' );
define( 'MAKIA_CLIENT_FILE', __FILE__ );
define( 'MAKIA_CLIENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAKIA_CLIENT_URL', plugin_dir_url( __FILE__ ) );
define( 'MAKIA_API_BASE', 'https://contacpro.app/api/trpc' );
define( 'MAKIA_API_LEGACY', 'https://contacpro.app/api' );

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
        add_action( 'wp_ajax_makia_activate_license_ajax', array( $this, 'ajax_activate_license' ) );
        add_action( 'wp_ajax_makia_deactivate_license_ajax', array( $this, 'ajax_deactivate_license' ) );
        add_action( 'wp_ajax_makia_verify_license_ajax', array( $this, 'ajax_verify_license' ) );

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
     * Solo necesita la clave de licencia para conectar con contacpro.app
     */
    public function is_configured() {
        return ! empty( $this->api_key );
    }

    /**
     * Menú de administración
     * Nota: El menú principal es gestionado por MakIA_License_Manager
     * Esta función se mantiene para compatibilidad
     */
    public function admin_menu() {
        // El menú principal ahora es gestionado por MakIA_License_Manager
        // que proporciona un menú completo con submenús para:
        // - Dashboard
        // - Mi Licencia
        // - Facturación
        // - Soporte
        // - Novedades
        // - Configuración
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
        // Verificar conexión/licencia
        $connection_status = $this->test_connection();
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'MakIA Restaurante - Configuración', 'makia-client' ); ?></h1>

            <?php if ( $connection_status['success'] ) : ?>
                <div class="notice notice-success">
                    <p>
                        <strong><?php esc_html_e( 'Licencia activa', 'makia-client' ); ?></strong> -
                        <?php echo esc_html( $domain ); ?>
                        <?php if ( ! empty( $connection_status['days_remaining'] ) ) : ?>
                            (<?php printf( esc_html__( '%d días restantes', 'makia-client' ), $connection_status['days_remaining'] ); ?>)
                        <?php endif; ?>
                    </p>
                </div>
            <?php elseif ( $this->is_configured() ) : ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php esc_html_e( 'Licencia no activada', 'makia-client' ); ?></strong>:
                        <?php echo esc_html( $connection_status['message'] ); ?>
                    </p>
                    <p>
                        <button type="button" class="button button-primary" id="makia-activate-license">
                            <?php esc_html_e( 'Activar licencia en este dominio', 'makia-client' ); ?>
                        </button>
                        <span class="spinner" style="float: none;"></span>
                    </p>
                </div>
                <script>
                jQuery(document).ready(function($) {
                    $('#makia-activate-license').on('click', function() {
                        var $btn = $(this);
                        var $spinner = $btn.next('.spinner');
                        $btn.prop('disabled', true);
                        $spinner.addClass('is-active');

                        $.post(ajaxurl, {
                            action: 'makia_activate_license_ajax',
                            nonce: '<?php echo wp_create_nonce( 'makia_activate_license' ); ?>'
                        }, function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || 'Error activando licencia');
                                $btn.prop('disabled', false);
                                $spinner.removeClass('is-active');
                            }
                        });
                    });
                });
                </script>
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
                                    '<a href="https://dashboard.contacpro.app/settings/api-keys" target="_blank">dashboard.contacpro.app</a>'
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
                <a href="https://dashboard.contacpro.app" target="_blank" class="button button-secondary">
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
        if ( empty( $this->api_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'Clave de licencia requerida', 'makia-client' ),
            );
        }

        // Verificar licencia usando tRPC
        $response = $this->verify_license();

        if ( isset( $response['valid'] ) && $response['valid'] ) {
            return array(
                'success'           => true,
                'organization_name' => $response['license']['domain'] ?? wp_parse_url( home_url(), PHP_URL_HOST ),
                'plan'              => $response['license']['status'] ?? 'active',
                'expires_at'        => $response['license']['expiresAt'] ?? null,
                'days_remaining'    => $response['license']['daysUntilExpiration'] ?? null,
            );
        }

        return array(
            'success' => false,
            'message' => $response['message'] ?? __( 'Licencia inválida o no activada', 'makia-client' ),
        );
    }

    /**
     * Realizar petición a la API (legacy REST)
     */
    public function api_request( $endpoint, $method = 'GET', $body = null ) {
        $url = MAKIA_API_LEGACY . $endpoint;

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
     * Realizar petición tRPC a la API de contacpro.app
     *
     * @param string $procedure Procedimiento tRPC (ej: 'licenses.verify')
     * @param array  $input     Datos de entrada
     * @param string $type      'query' o 'mutation'
     * @return array|WP_Error
     */
    public function trpc_request( $procedure, $input = array(), $type = 'mutation' ) {
        $url = MAKIA_API_BASE . '/' . $procedure;

        if ( $type === 'query' ) {
            // Para queries, el input va en la URL codificado
            if ( ! empty( $input ) ) {
                $url .= '?input=' . urlencode( wp_json_encode( array( 'json' => $input ) ) );
            }
            $method = 'GET';
            $body = null;
        } else {
            // Para mutations, el input va en el body
            $method = 'POST';
            $body = array( 'json' => $input );
        }

        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
        );

        // Añadir Authorization si tenemos API key
        if ( ! empty( $this->api_key ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->api_key;
        }

        if ( $body ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        // tRPC devuelve { result: { data: { json: ... } } } para éxito
        // o { error: { json: { message: ... } } } para error
        if ( isset( $data['result']['data']['json'] ) ) {
            return array(
                'success' => true,
                'data'    => $data['result']['data']['json'],
            );
        }

        if ( isset( $data['error'] ) ) {
            $error_message = $data['error']['json']['message']
                ?? $data['error']['message']
                ?? __( 'Error desconocido', 'makia-client' );
            return new WP_Error( 'trpc_error', $error_message );
        }

        // Respuesta inesperada
        if ( $response_code >= 400 ) {
            return new WP_Error(
                'http_error',
                sprintf( __( 'Error HTTP %d', 'makia-client' ), $response_code )
            );
        }

        return array(
            'success' => true,
            'data'    => $data,
        );
    }

    /**
     * Verificar licencia con el servidor tRPC
     *
     * @return array Resultado de la verificación
     */
    public function verify_license() {
        $license_key = $this->api_key;
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );

        if ( empty( $license_key ) ) {
            return array(
                'valid'   => false,
                'message' => __( 'Clave de licencia no configurada', 'makia-client' ),
            );
        }

        $response = $this->trpc_request( 'licenses.verify', array(
            'licenseKey' => $license_key,
            'domain'     => $domain,
            'ipAddress'  => $_SERVER['SERVER_ADDR'] ?? '',
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'valid'   => false,
                'message' => $response->get_error_message(),
            );
        }

        if ( isset( $response['data'] ) ) {
            return $response['data'];
        }

        return array(
            'valid'   => false,
            'message' => __( 'Respuesta inválida del servidor', 'makia-client' ),
        );
    }

    /**
     * Activar licencia en este dominio
     *
     * @return array Resultado de la activación
     */
    public function activate_license() {
        $license_key = $this->api_key;
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );

        if ( empty( $license_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'Clave de licencia no configurada', 'makia-client' ),
            );
        }

        $response = $this->trpc_request( 'licenses.activate', array(
            'licenseKey' => $license_key,
            'domain'     => $domain,
            'ipAddress'  => $_SERVER['SERVER_ADDR'] ?? '',
            'userAgent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; MakIA/' . MAKIA_CLIENT_VERSION,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        return $response['data'] ?? array(
            'success' => false,
            'message' => __( 'Respuesta inválida del servidor', 'makia-client' ),
        );
    }

    /**
     * Desactivar licencia de este dominio
     *
     * @return array Resultado de la desactivación
     */
    public function deactivate_license() {
        $license_key = $this->api_key;
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );

        if ( empty( $license_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'Clave de licencia no configurada', 'makia-client' ),
            );
        }

        $response = $this->trpc_request( 'licenses.deactivate', array(
            'licenseKey' => $license_key,
            'domain'     => $domain,
            'ipAddress'  => $_SERVER['SERVER_ADDR'] ?? '',
            'userAgent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; MakIA/' . MAKIA_CLIENT_VERSION,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        return $response['data'] ?? array(
            'success' => false,
            'message' => __( 'Respuesta inválida del servidor', 'makia-client' ),
        );
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
     * AJAX: Activar licencia
     */
    public function ajax_activate_license() {
        check_ajax_referer( 'makia_activate_license', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $result = $this->activate_license();

        if ( isset( $result['success'] ) && $result['success'] ) {
            // Limpiar caches
            delete_transient( 'makia_license_data' );
            delete_transient( 'makia_connection_status' );
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Desactivar licencia
     */
    public function ajax_deactivate_license() {
        check_ajax_referer( 'makia_deactivate_license', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $result = $this->deactivate_license();

        if ( isset( $result['success'] ) && $result['success'] ) {
            // Limpiar caches
            delete_transient( 'makia_license_data' );
            delete_transient( 'makia_connection_status' );
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Verificar licencia
     */
    public function ajax_verify_license() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $result = $this->verify_license();
        wp_send_json_success( $result );
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
            'https://cdn.contacpro.app/widget.js',
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

    /**
     * Obtener API key
     */
    public function get_api_key() {
        return $this->api_key;
    }

    /**
     * Obtener organización
     */
    public function get_organization() {
        return $this->organization;
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
    delete_transient( 'makia_license_data' );
});

// Cargar License Manager (gestión de planes, facturación, soporte)
require_once MAKIA_CLIENT_DIR . 'includes/class-makia-license-manager.php';

// Cargar Auto Sync (sincronización automática con contacpro.app)
require_once MAKIA_CLIENT_DIR . 'includes/class-makia-auto-sync.php';
