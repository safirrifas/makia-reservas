<?php
/**
 * MakIA Restaurante - License Manager
 *
 * Gestión completa de licencias: planes, facturación, soporte y notificaciones
 *
 * @package MakIA_Client
 * @since 1.1.0
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase para gestionar licencias del restaurante
 */
class MakIA_License_Manager {

    /**
     * Instancia singleton
     */
    private static $instance = null;

    /**
     * Datos de licencia cacheados
     */
    private $license_data = null;

    /**
     * Cache transient key
     */
    const CACHE_KEY = 'makia_license_data';

    /**
     * Cache duration (1 hora)
     */
    const CACHE_DURATION = HOUR_IN_SECONDS;

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
        // Hooks de administración
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // AJAX handlers
        add_action( 'wp_ajax_makia_get_license', array( $this, 'ajax_get_license' ) );
        add_action( 'wp_ajax_makia_get_billing', array( $this, 'ajax_get_billing' ) );
        add_action( 'wp_ajax_makia_get_invoices', array( $this, 'ajax_get_invoices' ) );
        add_action( 'wp_ajax_makia_submit_ticket', array( $this, 'ajax_submit_ticket' ) );
        add_action( 'wp_ajax_makia_get_tickets', array( $this, 'ajax_get_tickets' ) );
        add_action( 'wp_ajax_makia_get_notifications', array( $this, 'ajax_get_notifications' ) );
        add_action( 'wp_ajax_makia_mark_notification_read', array( $this, 'ajax_mark_notification_read' ) );
        add_action( 'wp_ajax_makia_upgrade_plan', array( $this, 'ajax_upgrade_plan' ) );
        add_action( 'wp_ajax_makia_update_payment_method', array( $this, 'ajax_update_payment_method' ) );

        // Cron para verificar licencia
        add_action( 'makia_check_license', array( $this, 'scheduled_license_check' ) );

        // Admin notices
        add_action( 'admin_notices', array( $this, 'license_notices' ) );
    }

    /**
     * Registrar menú de administración
     */
    public function register_admin_menu() {
        // Menú principal
        add_menu_page(
            __( 'MakIA Restaurante', 'makia-client' ),
            __( 'MakIA Restaurante', 'makia-client' ),
            'manage_options',
            'makia-dashboard',
            array( $this, 'render_dashboard_page' ),
            'data:image/svg+xml;base64,' . base64_encode( $this->get_menu_icon() ),
            30
        );

        // Submenús
        add_submenu_page(
            'makia-dashboard',
            __( 'Dashboard', 'makia-client' ),
            __( 'Dashboard', 'makia-client' ),
            'manage_options',
            'makia-dashboard',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'makia-dashboard',
            __( 'Mi Licencia', 'makia-client' ),
            __( 'Mi Licencia', 'makia-client' ),
            'manage_options',
            'makia-license',
            array( $this, 'render_license_page' )
        );

        add_submenu_page(
            'makia-dashboard',
            __( 'Facturación', 'makia-client' ),
            __( 'Facturación', 'makia-client' ),
            'manage_options',
            'makia-billing',
            array( $this, 'render_billing_page' )
        );

        add_submenu_page(
            'makia-dashboard',
            __( 'Soporte', 'makia-client' ),
            __( 'Soporte', 'makia-client' ),
            'manage_options',
            'makia-support',
            array( $this, 'render_support_page' )
        );

        add_submenu_page(
            'makia-dashboard',
            __( 'Novedades', 'makia-client' ),
            __( 'Novedades', 'makia-client' ),
            'manage_options',
            'makia-updates',
            array( $this, 'render_updates_page' )
        );

        add_submenu_page(
            'makia-dashboard',
            __( 'Configuración', 'makia-client' ),
            __( 'Configuración', 'makia-client' ),
            'manage_options',
            'makia-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        // Solo en páginas de MakIA
        if ( strpos( $hook, 'makia' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'makia-admin',
            MAKIA_CLIENT_URL . 'assets/css/admin.css',
            array(),
            MAKIA_CLIENT_VERSION
        );

        wp_enqueue_script(
            'makia-admin',
            MAKIA_CLIENT_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            MAKIA_CLIENT_VERSION,
            true
        );

        wp_localize_script( 'makia-admin', 'makiaAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'makia_admin_nonce' ),
            'i18n'    => array(
                'loading'         => __( 'Cargando...', 'makia-client' ),
                'error'           => __( 'Error al procesar la solicitud', 'makia-client' ),
                'success'         => __( 'Operación completada', 'makia-client' ),
                'confirmUpgrade'  => __( '¿Confirmar cambio de plan?', 'makia-client' ),
                'confirmCancel'   => __( '¿Seguro que deseas cancelar tu suscripción?', 'makia-client' ),
            ),
        ) );
    }

    /**
     * Obtener datos de licencia
     */
    public function get_license_data( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( $cached !== false ) {
                return $cached;
            }
        }

        $client = makia_client();
        $response = $client->api_request( '/license/current' );

        if ( is_wp_error( $response ) || ! isset( $response['success'] ) || ! $response['success'] ) {
            return $this->get_default_license_data();
        }

        $data = $response['data'];
        set_transient( self::CACHE_KEY, $data, self::CACHE_DURATION );

        return $data;
    }

    /**
     * Datos de licencia por defecto
     */
    private function get_default_license_data() {
        return array(
            'status'           => 'inactive',
            'plan'             => 'free',
            'plan_name'        => 'Free',
            'expires_at'       => null,
            'features'         => array(),
            'usage'            => array(
                'reservations'     => 0,
                'reservations_limit' => 50,
                'sms_sent'         => 0,
                'sms_limit'        => 0,
                'email_sent'       => 0,
                'email_limit'      => 100,
            ),
            'organization'     => array(
                'name'    => get_bloginfo( 'name' ),
                'email'   => get_option( 'admin_email' ),
            ),
        );
    }

    /**
     * Renderizar página de Dashboard
     */
    public function render_dashboard_page() {
        $license = $this->get_license_data();
        ?>
        <div class="wrap makia-wrap">
            <h1 class="makia-header">
                <span class="makia-logo"><?php echo $this->get_logo_svg(); ?></span>
                MakIA Restaurante
            </h1>

            <div class="makia-dashboard">
                <!-- Resumen de licencia -->
                <div class="makia-card makia-card-primary">
                    <div class="makia-card-header">
                        <h2><?php esc_html_e( 'Tu Plan Actual', 'makia-client' ); ?></h2>
                        <span class="makia-badge makia-badge-<?php echo esc_attr( $license['status'] ); ?>">
                            <?php echo esc_html( ucfirst( $license['status'] ) ); ?>
                        </span>
                    </div>
                    <div class="makia-card-body">
                        <div class="makia-plan-info">
                            <span class="makia-plan-name"><?php echo esc_html( $license['plan_name'] ); ?></span>
                            <?php if ( $license['expires_at'] ) : ?>
                                <span class="makia-plan-expiry">
                                    <?php
                                    printf(
                                        esc_html__( 'Válido hasta: %s', 'makia-client' ),
                                        date_i18n( get_option( 'date_format' ), strtotime( $license['expires_at'] ) )
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo admin_url( 'admin.php?page=makia-license' ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Gestionar Plan', 'makia-client' ); ?>
                        </a>
                    </div>
                </div>

                <!-- Estadísticas de uso -->
                <div class="makia-card">
                    <div class="makia-card-header">
                        <h2><?php esc_html_e( 'Uso del Mes', 'makia-client' ); ?></h2>
                    </div>
                    <div class="makia-card-body">
                        <div class="makia-usage-stats">
                            <div class="makia-stat">
                                <div class="makia-stat-label"><?php esc_html_e( 'Reservas', 'makia-client' ); ?></div>
                                <div class="makia-stat-value">
                                    <?php echo esc_html( $license['usage']['reservations'] ); ?>
                                    <span class="makia-stat-limit">
                                        / <?php echo $license['usage']['reservations_limit'] === -1 ? '∞' : esc_html( $license['usage']['reservations_limit'] ); ?>
                                    </span>
                                </div>
                                <?php $this->render_usage_bar( $license['usage']['reservations'], $license['usage']['reservations_limit'] ); ?>
                            </div>
                            <div class="makia-stat">
                                <div class="makia-stat-label"><?php esc_html_e( 'Emails enviados', 'makia-client' ); ?></div>
                                <div class="makia-stat-value">
                                    <?php echo esc_html( $license['usage']['email_sent'] ); ?>
                                    <span class="makia-stat-limit">
                                        / <?php echo $license['usage']['email_limit'] === -1 ? '∞' : esc_html( $license['usage']['email_limit'] ); ?>
                                    </span>
                                </div>
                                <?php $this->render_usage_bar( $license['usage']['email_sent'], $license['usage']['email_limit'] ); ?>
                            </div>
                            <div class="makia-stat">
                                <div class="makia-stat-label"><?php esc_html_e( 'SMS enviados', 'makia-client' ); ?></div>
                                <div class="makia-stat-value">
                                    <?php echo esc_html( $license['usage']['sms_sent'] ); ?>
                                    <span class="makia-stat-limit">
                                        / <?php echo $license['usage']['sms_limit'] === -1 ? '∞' : esc_html( $license['usage']['sms_limit'] ); ?>
                                    </span>
                                </div>
                                <?php $this->render_usage_bar( $license['usage']['sms_sent'], $license['usage']['sms_limit'] ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accesos rápidos -->
                <div class="makia-card">
                    <div class="makia-card-header">
                        <h2><?php esc_html_e( 'Accesos Rápidos', 'makia-client' ); ?></h2>
                    </div>
                    <div class="makia-card-body">
                        <div class="makia-quick-links">
                            <a href="https://dashboard.contacpro.app" target="_blank" class="makia-quick-link">
                                <span class="dashicons dashicons-external"></span>
                                <?php esc_html_e( 'Dashboard Online', 'makia-client' ); ?>
                            </a>
                            <a href="<?php echo admin_url( 'admin.php?page=makia-billing' ); ?>" class="makia-quick-link">
                                <span class="dashicons dashicons-money-alt"></span>
                                <?php esc_html_e( 'Facturación', 'makia-client' ); ?>
                            </a>
                            <a href="<?php echo admin_url( 'admin.php?page=makia-support' ); ?>" class="makia-quick-link">
                                <span class="dashicons dashicons-sos"></span>
                                <?php esc_html_e( 'Soporte', 'makia-client' ); ?>
                            </a>
                            <a href="<?php echo admin_url( 'admin.php?page=makia-updates' ); ?>" class="makia-quick-link">
                                <span class="dashicons dashicons-megaphone"></span>
                                <?php esc_html_e( 'Novedades', 'makia-client' ); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Notificaciones recientes -->
                <div class="makia-card">
                    <div class="makia-card-header">
                        <h2><?php esc_html_e( 'Notificaciones Recientes', 'makia-client' ); ?></h2>
                    </div>
                    <div class="makia-card-body">
                        <div id="makia-recent-notifications" class="makia-notifications-list">
                            <p class="makia-loading"><?php esc_html_e( 'Cargando...', 'makia-client' ); ?></p>
                        </div>
                        <a href="<?php echo admin_url( 'admin.php?page=makia-updates' ); ?>" class="makia-view-all">
                            <?php esc_html_e( 'Ver todas las novedades', 'makia-client' ); ?> →
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar página de Licencia
     */
    public function render_license_page() {
        $license = $this->get_license_data();
        $plans = $this->get_available_plans();
        ?>
        <div class="wrap makia-wrap">
            <h1><?php esc_html_e( 'Mi Licencia', 'makia-client' ); ?></h1>

            <div class="makia-license-page">
                <!-- Plan actual -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Plan Actual', 'makia-client' ); ?></h2>

                    <div class="makia-current-plan">
                        <div class="makia-plan-card makia-plan-current">
                            <div class="makia-plan-header">
                                <h3><?php echo esc_html( $license['plan_name'] ); ?></h3>
                                <span class="makia-badge makia-badge-<?php echo esc_attr( $license['status'] ); ?>">
                                    <?php echo esc_html( $this->get_status_label( $license['status'] ) ); ?>
                                </span>
                            </div>
                            <div class="makia-plan-details">
                                <?php if ( $license['expires_at'] ) : ?>
                                    <p>
                                        <strong><?php esc_html_e( 'Próxima renovación:', 'makia-client' ); ?></strong>
                                        <?php echo date_i18n( get_option( 'date_format' ), strtotime( $license['expires_at'] ) ); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="makia-plan-features">
                                    <h4><?php esc_html_e( 'Características incluidas:', 'makia-client' ); ?></h4>
                                    <ul>
                                        <?php foreach ( $this->get_plan_features( $license['plan'] ) as $feature ) : ?>
                                            <li><span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html( $feature ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Planes disponibles -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Cambiar de Plan', 'makia-client' ); ?></h2>

                    <div class="makia-plans-grid">
                        <?php foreach ( $plans as $plan_id => $plan ) : ?>
                            <div class="makia-plan-card <?php echo $plan_id === $license['plan'] ? 'makia-plan-current' : ''; ?> <?php echo $plan['recommended'] ? 'makia-plan-recommended' : ''; ?>">
                                <?php if ( $plan['recommended'] ) : ?>
                                    <span class="makia-recommended-badge"><?php esc_html_e( 'Recomendado', 'makia-client' ); ?></span>
                                <?php endif; ?>

                                <div class="makia-plan-header">
                                    <h3><?php echo esc_html( $plan['name'] ); ?></h3>
                                    <div class="makia-plan-price">
                                        <?php if ( $plan['price'] === 0 ) : ?>
                                            <span class="makia-price-amount"><?php esc_html_e( 'Gratis', 'makia-client' ); ?></span>
                                        <?php else : ?>
                                            <span class="makia-price-amount"><?php echo esc_html( $plan['price'] ); ?>€</span>
                                            <span class="makia-price-period">/<?php esc_html_e( 'mes', 'makia-client' ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="makia-plan-features">
                                    <ul>
                                        <?php foreach ( $plan['features'] as $feature ) : ?>
                                            <li><span class="dashicons dashicons-yes"></span> <?php echo esc_html( $feature ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                                <div class="makia-plan-action">
                                    <?php if ( $plan_id === $license['plan'] ) : ?>
                                        <button class="button" disabled><?php esc_html_e( 'Plan Actual', 'makia-client' ); ?></button>
                                    <?php elseif ( $this->is_upgrade( $license['plan'], $plan_id ) ) : ?>
                                        <button class="button button-primary makia-upgrade-btn" data-plan="<?php echo esc_attr( $plan_id ); ?>">
                                            <?php esc_html_e( 'Mejorar Plan', 'makia-client' ); ?>
                                        </button>
                                    <?php else : ?>
                                        <button class="button makia-downgrade-btn" data-plan="<?php echo esc_attr( $plan_id ); ?>">
                                            <?php esc_html_e( 'Cambiar a este plan', 'makia-client' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Datos de la organización -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Datos del Restaurante', 'makia-client' ); ?></h2>

                    <div class="makia-card">
                        <div class="makia-card-body">
                            <table class="makia-info-table">
                                <tr>
                                    <th><?php esc_html_e( 'Nombre:', 'makia-client' ); ?></th>
                                    <td><?php echo esc_html( $license['organization']['name'] ?? get_bloginfo( 'name' ) ); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Email:', 'makia-client' ); ?></th>
                                    <td><?php echo esc_html( $license['organization']['email'] ?? get_option( 'admin_email' ) ); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Clave de licencia:', 'makia-client' ); ?></th>
                                    <td>
                                        <code><?php echo esc_html( $this->mask_api_key( get_option( 'makia_api_key' ) ) ); ?></code>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Sitio web:', 'makia-client' ); ?></th>
                                    <td><?php echo esc_url( home_url() ); ?></td>
                                </tr>
                            </table>

                            <p class="makia-info-note">
                                <?php
                                printf(
                                    esc_html__( 'Para modificar los datos de tu restaurante, accede al %s', 'makia-client' ),
                                    '<a href="https://dashboard.contacpro.app/settings/organization" target="_blank">' . esc_html__( 'Dashboard de contacpro.app', 'makia-client' ) . '</a>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar página de Facturación
     */
    public function render_billing_page() {
        ?>
        <div class="wrap makia-wrap">
            <h1><?php esc_html_e( 'Facturación', 'makia-client' ); ?></h1>

            <div class="makia-billing-page">
                <!-- Método de pago -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Método de Pago', 'makia-client' ); ?></h2>

                    <div class="makia-card" id="makia-payment-method">
                        <div class="makia-card-body">
                            <div class="makia-loading"><?php esc_html_e( 'Cargando información de pago...', 'makia-client' ); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Datos de facturación -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Datos de Facturación', 'makia-client' ); ?></h2>

                    <div class="makia-card" id="makia-billing-info">
                        <div class="makia-card-body">
                            <div class="makia-loading"><?php esc_html_e( 'Cargando datos de facturación...', 'makia-client' ); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Historial de facturas -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Historial de Facturas', 'makia-client' ); ?></h2>

                    <div class="makia-card">
                        <div class="makia-card-body">
                            <table class="wp-list-table widefat fixed striped" id="makia-invoices-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Factura', 'makia-client' ); ?></th>
                                        <th><?php esc_html_e( 'Fecha', 'makia-client' ); ?></th>
                                        <th><?php esc_html_e( 'Importe', 'makia-client' ); ?></th>
                                        <th><?php esc_html_e( 'Estado', 'makia-client' ); ?></th>
                                        <th><?php esc_html_e( 'Acciones', 'makia-client' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="makia-loading"><?php esc_html_e( 'Cargando facturas...', 'makia-client' ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Próxima factura -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Próxima Factura', 'makia-client' ); ?></h2>

                    <div class="makia-card" id="makia-next-invoice">
                        <div class="makia-card-body">
                            <div class="makia-loading"><?php esc_html_e( 'Cargando...', 'makia-client' ); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar página de Soporte
     */
    public function render_support_page() {
        ?>
        <div class="wrap makia-wrap">
            <h1><?php esc_html_e( 'Soporte', 'makia-client' ); ?></h1>

            <div class="makia-support-page">
                <!-- Crear nuevo ticket -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Nuevo Ticket de Soporte', 'makia-client' ); ?></h2>

                    <div class="makia-card">
                        <div class="makia-card-body">
                            <form id="makia-support-form" class="makia-form">
                                <div class="makia-form-row">
                                    <label for="ticket-category"><?php esc_html_e( 'Categoría', 'makia-client' ); ?></label>
                                    <select id="ticket-category" name="category" required>
                                        <option value=""><?php esc_html_e( 'Selecciona una categoría', 'makia-client' ); ?></option>
                                        <option value="technical"><?php esc_html_e( 'Problema técnico', 'makia-client' ); ?></option>
                                        <option value="billing"><?php esc_html_e( 'Facturación', 'makia-client' ); ?></option>
                                        <option value="feature"><?php esc_html_e( 'Solicitud de funcionalidad', 'makia-client' ); ?></option>
                                        <option value="general"><?php esc_html_e( 'Consulta general', 'makia-client' ); ?></option>
                                    </select>
                                </div>

                                <div class="makia-form-row">
                                    <label for="ticket-priority"><?php esc_html_e( 'Prioridad', 'makia-client' ); ?></label>
                                    <select id="ticket-priority" name="priority">
                                        <option value="low"><?php esc_html_e( 'Baja', 'makia-client' ); ?></option>
                                        <option value="medium" selected><?php esc_html_e( 'Media', 'makia-client' ); ?></option>
                                        <option value="high"><?php esc_html_e( 'Alta', 'makia-client' ); ?></option>
                                        <option value="urgent"><?php esc_html_e( 'Urgente', 'makia-client' ); ?></option>
                                    </select>
                                </div>

                                <div class="makia-form-row">
                                    <label for="ticket-subject"><?php esc_html_e( 'Asunto', 'makia-client' ); ?></label>
                                    <input type="text" id="ticket-subject" name="subject" required
                                           placeholder="<?php esc_attr_e( 'Describe brevemente tu problema', 'makia-client' ); ?>">
                                </div>

                                <div class="makia-form-row">
                                    <label for="ticket-message"><?php esc_html_e( 'Mensaje', 'makia-client' ); ?></label>
                                    <textarea id="ticket-message" name="message" rows="6" required
                                              placeholder="<?php esc_attr_e( 'Explica tu problema o consulta con el mayor detalle posible...', 'makia-client' ); ?>"></textarea>
                                </div>

                                <div class="makia-form-row">
                                    <button type="submit" class="button button-primary">
                                        <?php esc_html_e( 'Enviar Ticket', 'makia-client' ); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tickets anteriores -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Mis Tickets', 'makia-client' ); ?></h2>

                    <div class="makia-card">
                        <div class="makia-card-body">
                            <table class="wp-list-table widefat fixed striped" id="makia-tickets-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'ID', 'makia-client' ); ?></th>
                                        <th><?php esc_html_e( 'Asunto', 'makia-client' ); ?></th>
                                        <th><?php esc_html_e( 'Estado', 'makia-client' ); ?></th>
                                        <th><?php esc_html_e( 'Última actualización', 'makia-client' ); ?></th>
                                        <th><?php esc_html_e( 'Acciones', 'makia-client' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="makia-loading"><?php esc_html_e( 'Cargando tickets...', 'makia-client' ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recursos de ayuda -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Recursos de Ayuda', 'makia-client' ); ?></h2>

                    <div class="makia-help-resources">
                        <a href="https://docs.contacpro.app" target="_blank" class="makia-resource-card">
                            <span class="dashicons dashicons-book"></span>
                            <h3><?php esc_html_e( 'Documentación', 'makia-client' ); ?></h3>
                            <p><?php esc_html_e( 'Guías y tutoriales completos', 'makia-client' ); ?></p>
                        </a>

                        <a href="https://docs.contacpro.app/faq" target="_blank" class="makia-resource-card">
                            <span class="dashicons dashicons-editor-help"></span>
                            <h3><?php esc_html_e( 'FAQ', 'makia-client' ); ?></h3>
                            <p><?php esc_html_e( 'Preguntas frecuentes', 'makia-client' ); ?></p>
                        </a>

                        <a href="https://www.youtube.com/@contacpro" target="_blank" class="makia-resource-card">
                            <span class="dashicons dashicons-video-alt3"></span>
                            <h3><?php esc_html_e( 'Video Tutoriales', 'makia-client' ); ?></h3>
                            <p><?php esc_html_e( 'Aprende viendo', 'makia-client' ); ?></p>
                        </a>

                        <a href="mailto:soporte@contacpro.app" class="makia-resource-card">
                            <span class="dashicons dashicons-email"></span>
                            <h3><?php esc_html_e( 'Email Directo', 'makia-client' ); ?></h3>
                            <p>soporte@contacpro.app</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar página de Novedades
     */
    public function render_updates_page() {
        ?>
        <div class="wrap makia-wrap">
            <h1><?php esc_html_e( 'Novedades y Actualizaciones', 'makia-client' ); ?></h1>

            <div class="makia-updates-page">
                <!-- Filtros -->
                <div class="makia-filters">
                    <select id="makia-notification-filter">
                        <option value="all"><?php esc_html_e( 'Todas las notificaciones', 'makia-client' ); ?></option>
                        <option value="feature"><?php esc_html_e( 'Nuevas funcionalidades', 'makia-client' ); ?></option>
                        <option value="improvement"><?php esc_html_e( 'Mejoras', 'makia-client' ); ?></option>
                        <option value="maintenance"><?php esc_html_e( 'Mantenimiento', 'makia-client' ); ?></option>
                        <option value="announcement"><?php esc_html_e( 'Anuncios', 'makia-client' ); ?></option>
                    </select>

                    <button id="makia-mark-all-read" class="button">
                        <?php esc_html_e( 'Marcar todo como leído', 'makia-client' ); ?>
                    </button>
                </div>

                <!-- Lista de notificaciones -->
                <div class="makia-notifications-container" id="makia-notifications-list">
                    <div class="makia-loading"><?php esc_html_e( 'Cargando novedades...', 'makia-client' ); ?></div>
                </div>

                <!-- Roadmap -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Próximamente', 'makia-client' ); ?></h2>

                    <div class="makia-roadmap" id="makia-roadmap">
                        <div class="makia-loading"><?php esc_html_e( 'Cargando roadmap...', 'makia-client' ); ?></div>
                    </div>
                </div>

                <!-- Changelog -->
                <div class="makia-section">
                    <h2><?php esc_html_e( 'Historial de Versiones', 'makia-client' ); ?></h2>

                    <div class="makia-changelog" id="makia-changelog">
                        <div class="makia-loading"><?php esc_html_e( 'Cargando changelog...', 'makia-client' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar página de Configuración (la original mejorada)
     */
    public function render_settings_page() {
        // Redirigir a la página original de settings
        $client = makia_client();
        $client->settings_page();
    }

    /**
     * Renderizar barra de uso
     */
    private function render_usage_bar( $used, $limit ) {
        if ( $limit === -1 ) {
            $percentage = 0;
        } elseif ( $limit === 0 ) {
            $percentage = 100;
        } else {
            $percentage = min( 100, ( $used / $limit ) * 100 );
        }

        $class = 'makia-usage-bar';
        if ( $percentage >= 90 ) {
            $class .= ' makia-usage-critical';
        } elseif ( $percentage >= 75 ) {
            $class .= ' makia-usage-warning';
        }
        ?>
        <div class="<?php echo esc_attr( $class ); ?>">
            <div class="makia-usage-fill" style="width: <?php echo esc_attr( $percentage ); ?>%"></div>
        </div>
        <?php
    }

    /**
     * Obtener planes disponibles
     */
    private function get_available_plans() {
        return array(
            'free' => array(
                'name'        => 'Free',
                'price'       => 0,
                'recommended' => false,
                'features'    => array(
                    __( '50 reservas/mes', 'makia-client' ),
                    __( '100 emails/mes', 'makia-client' ),
                    __( 'Widget básico', 'makia-client' ),
                    __( 'Soporte por email', 'makia-client' ),
                ),
            ),
            'starter' => array(
                'name'        => 'Starter',
                'price'       => 9,
                'recommended' => false,
                'features'    => array(
                    __( '200 reservas/mes', 'makia-client' ),
                    __( '500 emails/mes', 'makia-client' ),
                    __( '50 SMS/mes', 'makia-client' ),
                    __( 'Widget personalizable', 'makia-client' ),
                    __( 'Recordatorios automáticos', 'makia-client' ),
                    __( 'Soporte prioritario', 'makia-client' ),
                ),
            ),
            'pro' => array(
                'name'        => 'Pro',
                'price'       => 29,
                'recommended' => true,
                'features'    => array(
                    __( 'Reservas ilimitadas', 'makia-client' ),
                    __( '2000 emails/mes', 'makia-client' ),
                    __( '200 SMS/mes', 'makia-client' ),
                    __( 'WhatsApp Business', 'makia-client' ),
                    __( 'Telegram Bot', 'makia-client' ),
                    __( 'Integraciones avanzadas', 'makia-client' ),
                    __( 'Analytics completo', 'makia-client' ),
                    __( 'Soporte 24/7', 'makia-client' ),
                ),
            ),
            'business' => array(
                'name'        => 'Business',
                'price'       => 79,
                'recommended' => false,
                'features'    => array(
                    __( 'Todo de Pro', 'makia-client' ),
                    __( 'Multi-restaurante', 'makia-client' ),
                    __( 'API personalizada', 'makia-client' ),
                    __( 'White-label', 'makia-client' ),
                    __( 'SMS ilimitados', 'makia-client' ),
                    __( 'Account manager dedicado', 'makia-client' ),
                    __( 'SLA garantizado', 'makia-client' ),
                ),
            ),
        );
    }

    /**
     * Obtener características del plan actual
     */
    private function get_plan_features( $plan ) {
        $plans = $this->get_available_plans();
        return $plans[ $plan ]['features'] ?? array();
    }

    /**
     * Verificar si es un upgrade
     */
    private function is_upgrade( $current, $new ) {
        $order = array( 'free' => 0, 'starter' => 1, 'pro' => 2, 'business' => 3 );
        return ( $order[ $new ] ?? 0 ) > ( $order[ $current ] ?? 0 );
    }

    /**
     * Obtener etiqueta de estado
     */
    private function get_status_label( $status ) {
        $labels = array(
            'active'    => __( 'Activo', 'makia-client' ),
            'inactive'  => __( 'Inactivo', 'makia-client' ),
            'trial'     => __( 'Prueba', 'makia-client' ),
            'expired'   => __( 'Expirado', 'makia-client' ),
            'cancelled' => __( 'Cancelado', 'makia-client' ),
        );
        return $labels[ $status ] ?? $status;
    }

    /**
     * Enmascarar API key
     */
    private function mask_api_key( $key ) {
        if ( strlen( $key ) <= 8 ) {
            return str_repeat( '*', strlen( $key ) );
        }
        return substr( $key, 0, 4 ) . str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -4 );
    }

    /**
     * AJAX: Obtener licencia
     */
    public function ajax_get_license() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $license = $this->get_license_data( true );
        wp_send_json_success( $license );
    }

    /**
     * AJAX: Obtener facturación
     */
    public function ajax_get_billing() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $client = makia_client();
        $response = $client->api_request( '/billing/info' );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        wp_send_json( $response );
    }

    /**
     * AJAX: Obtener facturas
     */
    public function ajax_get_invoices() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $client = makia_client();
        $response = $client->api_request( '/billing/invoices' );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        wp_send_json( $response );
    }

    /**
     * AJAX: Enviar ticket de soporte
     */
    public function ajax_submit_ticket() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $category = sanitize_text_field( $_POST['category'] ?? '' );
        $priority = sanitize_text_field( $_POST['priority'] ?? 'medium' );
        $subject  = sanitize_text_field( $_POST['subject'] ?? '' );
        $message  = sanitize_textarea_field( $_POST['message'] ?? '' );

        if ( empty( $category ) || empty( $subject ) || empty( $message ) ) {
            wp_send_json_error( array( 'message' => __( 'Todos los campos son obligatorios', 'makia-client' ) ) );
        }

        $client = makia_client();
        $response = $client->api_request( '/support/tickets', 'POST', array(
            'category' => $category,
            'priority' => $priority,
            'subject'  => $subject,
            'message'  => $message,
            'site_url' => home_url(),
            'wp_version' => get_bloginfo( 'version' ),
            'plugin_version' => MAKIA_CLIENT_VERSION,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        wp_send_json( $response );
    }

    /**
     * AJAX: Obtener tickets
     */
    public function ajax_get_tickets() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $client = makia_client();
        $response = $client->api_request( '/support/tickets' );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        wp_send_json( $response );
    }

    /**
     * AJAX: Obtener notificaciones
     */
    public function ajax_get_notifications() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $filter = sanitize_text_field( $_POST['filter'] ?? 'all' );

        $client = makia_client();
        $endpoint = '/notifications';
        if ( $filter !== 'all' ) {
            $endpoint .= '?type=' . $filter;
        }

        $response = $client->api_request( $endpoint );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        wp_send_json( $response );
    }

    /**
     * AJAX: Marcar notificación como leída
     */
    public function ajax_mark_notification_read() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $notification_id = sanitize_text_field( $_POST['notification_id'] ?? '' );

        $client = makia_client();

        if ( $notification_id === 'all' ) {
            $response = $client->api_request( '/notifications/mark-all-read', 'POST' );
        } else {
            $response = $client->api_request( '/notifications/' . $notification_id . '/read', 'POST' );
        }

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        wp_send_json( $response );
    }

    /**
     * AJAX: Upgrade de plan
     */
    public function ajax_upgrade_plan() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $plan = sanitize_text_field( $_POST['plan'] ?? '' );

        if ( empty( $plan ) ) {
            wp_send_json_error( array( 'message' => __( 'Plan no especificado', 'makia-client' ) ) );
        }

        $client = makia_client();
        $response = $client->api_request( '/billing/upgrade', 'POST', array(
            'plan' => $plan,
            'return_url' => admin_url( 'admin.php?page=makia-license&upgraded=1' ),
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        // Limpiar cache de licencia
        delete_transient( self::CACHE_KEY );

        wp_send_json( $response );
    }

    /**
     * AJAX: Actualizar método de pago
     */
    public function ajax_update_payment_method() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $client = makia_client();
        $response = $client->api_request( '/billing/payment-method/update-url', 'POST', array(
            'return_url' => admin_url( 'admin.php?page=makia-billing&updated=1' ),
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        wp_send_json( $response );
    }

    /**
     * Verificación programada de licencia
     */
    public function scheduled_license_check() {
        $license = $this->get_license_data( true );

        // Notificar si la licencia está próxima a expirar
        if ( $license['status'] === 'active' && $license['expires_at'] ) {
            $days_until_expiry = ( strtotime( $license['expires_at'] ) - time() ) / DAY_IN_SECONDS;

            if ( $days_until_expiry <= 7 && $days_until_expiry > 0 ) {
                update_option( 'makia_license_expiry_warning', true );
            }
        }
    }

    /**
     * Admin notices para licencia
     */
    public function license_notices() {
        $screen = get_current_screen();

        // Solo mostrar en páginas de MakIA o dashboard
        if ( strpos( $screen->id, 'makia' ) === false && $screen->id !== 'dashboard' ) {
            return;
        }

        // Advertencia de expiración
        if ( get_option( 'makia_license_expiry_warning' ) ) {
            $license = $this->get_license_data();
            if ( $license['expires_at'] ) {
                $days = ceil( ( strtotime( $license['expires_at'] ) - time() ) / DAY_IN_SECONDS );
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong>MakIA Restaurante:</strong>
                        <?php
                        printf(
                            esc_html__( 'Tu licencia expira en %d días. %s para evitar interrupciones.', 'makia-client' ),
                            $days,
                            '<a href="' . admin_url( 'admin.php?page=makia-billing' ) . '">' . esc_html__( 'Renueva ahora', 'makia-client' ) . '</a>'
                        );
                        ?>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * Icono del menú (SVG)
     */
    private function get_menu_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 110-12 6 6 0 010 12zm-1-5h2v2H9v-2zm0-6h2v5H9V5z"/></svg>';
    }

    /**
     * Logo SVG
     */
    private function get_logo_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="32" height="32"><circle cx="20" cy="20" r="18" fill="#4f46e5"/><text x="20" y="26" text-anchor="middle" fill="white" font-size="16" font-weight="bold">M</text></svg>';
    }
}

// Inicializar
function makia_license_manager() {
    return MakIA_License_Manager::get_instance();
}

add_action( 'plugins_loaded', 'makia_license_manager', 20 );

// Programar verificación de licencia
register_activation_hook( MAKIA_CLIENT_FILE, function() {
    if ( ! wp_next_scheduled( 'makia_check_license' ) ) {
        wp_schedule_event( time(), 'daily', 'makia_check_license' );
    }
} );

register_deactivation_hook( MAKIA_CLIENT_FILE, function() {
    wp_clear_scheduled_hook( 'makia_check_license' );
} );
