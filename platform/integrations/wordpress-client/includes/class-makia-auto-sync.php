<?php
/**
 * MakIA Restaurante - Auto Sync System
 *
 * Sistema de sincronización automática con contacpro.app
 * - Sincronización programada via cron
 * - Webhooks para actualizaciones en tiempo real
 * - Notificaciones en admin bar
 * - Auto-actualización del plugin
 *
 * @package MakIA_Client
 * @since 1.1.0
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase para sincronización automática
 */
class MakIA_Auto_Sync {

    /**
     * Instancia singleton
     */
    private static $instance = null;

    /**
     * Webhook secret para verificación
     */
    const WEBHOOK_SECRET_OPTION = 'makia_webhook_secret';

    /**
     * Intervalo de sincronización (en segundos)
     */
    const SYNC_INTERVAL = HOUR_IN_SECONDS;

    /**
     * URL del servidor de actualizaciones
     */
    const UPDATE_SERVER = 'https://api.contacpro.app/v1/updates/wordpress';

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
        // Registrar cron schedules
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

        // Hooks de sincronización programada
        add_action( 'makia_sync_license', array( $this, 'sync_license_data' ) );
        add_action( 'makia_sync_notifications', array( $this, 'sync_notifications' ) );
        add_action( 'makia_check_updates', array( $this, 'check_plugin_updates' ) );

        // Registrar endpoint para webhooks
        add_action( 'rest_api_init', array( $this, 'register_webhook_endpoint' ) );

        // Admin bar notifications
        add_action( 'admin_bar_menu', array( $this, 'admin_bar_notifications' ), 100 );
        add_action( 'wp_head', array( $this, 'admin_bar_styles' ) );
        add_action( 'admin_head', array( $this, 'admin_bar_styles' ) );

        // Hook de actualización del plugin
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

        // AJAX para sincronización manual
        add_action( 'wp_ajax_makia_manual_sync', array( $this, 'ajax_manual_sync' ) );
        add_action( 'wp_ajax_makia_dismiss_notification', array( $this, 'ajax_dismiss_notification' ) );

        // Programar eventos si no existen
        $this->schedule_events();
    }

    /**
     * Añadir schedules personalizados al cron
     */
    public function add_cron_schedules( $schedules ) {
        $schedules['makia_hourly'] = array(
            'interval' => HOUR_IN_SECONDS,
            'display'  => __( 'Cada hora (MakIA)', 'makia-client' ),
        );

        $schedules['makia_twice_daily'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __( 'Dos veces al día (MakIA)', 'makia-client' ),
        );

        return $schedules;
    }

    /**
     * Programar eventos de sincronización
     */
    public function schedule_events() {
        // Sincronización de licencia cada hora
        if ( ! wp_next_scheduled( 'makia_sync_license' ) ) {
            wp_schedule_event( time(), 'makia_hourly', 'makia_sync_license' );
        }

        // Sincronización de notificaciones cada 12 horas
        if ( ! wp_next_scheduled( 'makia_sync_notifications' ) ) {
            wp_schedule_event( time(), 'makia_twice_daily', 'makia_sync_notifications' );
        }

        // Verificación de actualizaciones cada 12 horas
        if ( ! wp_next_scheduled( 'makia_check_updates' ) ) {
            wp_schedule_event( time(), 'makia_twice_daily', 'makia_check_updates' );
        }
    }

    /**
     * Sincronizar datos de licencia
     */
    public function sync_license_data() {
        $client = makia_client();

        if ( ! $client->is_configured() ) {
            return false;
        }

        $response = $client->api_request( '/license/current' );

        if ( is_wp_error( $response ) || empty( $response['success'] ) ) {
            $this->log_sync_error( 'license', $response );
            return false;
        }

        // Guardar datos de licencia
        $license_data = $response['data'];
        set_transient( 'makia_license_data', $license_data, self::SYNC_INTERVAL * 2 );

        // Verificar si hay cambios importantes
        $previous_data = get_option( 'makia_last_license_data', array() );

        if ( $this->license_changed( $previous_data, $license_data ) ) {
            // Disparar acción para notificar cambios
            do_action( 'makia_license_changed', $license_data, $previous_data );

            // Guardar notificación local
            $this->add_local_notification(
                'license_update',
                __( 'Tu licencia de MakIA Restaurante ha sido actualizada', 'makia-client' ),
                sprintf(
                    __( 'Plan: %s | Estado: %s', 'makia-client' ),
                    $license_data['plan_name'],
                    $license_data['status']
                )
            );
        }

        update_option( 'makia_last_license_data', $license_data );
        update_option( 'makia_last_sync', current_time( 'mysql' ) );

        return true;
    }

    /**
     * Sincronizar notificaciones
     */
    public function sync_notifications() {
        $client = makia_client();

        if ( ! $client->is_configured() ) {
            return false;
        }

        $response = $client->api_request( '/notifications?limit=50' );

        if ( is_wp_error( $response ) || empty( $response['success'] ) ) {
            $this->log_sync_error( 'notifications', $response );
            return false;
        }

        $notifications = $response['data'];

        // Guardar notificaciones en transient
        set_transient( 'makia_notifications', $notifications, DAY_IN_SECONDS );

        // Contar no leídas
        $unread_count = count( array_filter( $notifications, function( $n ) {
            return empty( $n['read'] );
        } ) );

        update_option( 'makia_unread_notifications', $unread_count );

        return true;
    }

    /**
     * Verificar actualizaciones del plugin
     */
    public function check_plugin_updates() {
        $response = wp_remote_get( self::UPDATE_SERVER . '/check', array(
            'timeout' => 15,
            'headers' => array(
                'X-Plugin-Version' => MAKIA_CLIENT_VERSION,
                'X-WordPress-Version' => get_bloginfo( 'version' ),
                'X-PHP-Version' => PHP_VERSION,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['success'] ) && ! empty( $body['data']['version'] ) ) {
            $update_info = $body['data'];

            if ( version_compare( $update_info['version'], MAKIA_CLIENT_VERSION, '>' ) ) {
                // Hay una actualización disponible
                set_transient( 'makia_update_available', $update_info, DAY_IN_SECONDS );

                // Notificar al admin
                $this->add_local_notification(
                    'update_available',
                    sprintf(
                        __( 'MakIA Restaurante %s disponible', 'makia-client' ),
                        $update_info['version']
                    ),
                    $update_info['changelog'] ?? ''
                );
            } else {
                delete_transient( 'makia_update_available' );
            }
        }

        return true;
    }

    /**
     * Registrar endpoint de webhook
     */
    public function register_webhook_endpoint() {
        register_rest_route( 'makia/v1', '/webhook', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_webhook' ),
            'permission_callback' => array( $this, 'verify_webhook' ),
        ) );
    }

    /**
     * Verificar firma del webhook
     */
    public function verify_webhook( $request ) {
        $signature = $request->get_header( 'X-MakIA-Signature' );
        $timestamp = $request->get_header( 'X-MakIA-Timestamp' );

        if ( empty( $signature ) || empty( $timestamp ) ) {
            return false;
        }

        // Verificar que el timestamp no sea muy antiguo (5 minutos)
        if ( abs( time() - intval( $timestamp ) ) > 300 ) {
            return false;
        }

        // Obtener o generar webhook secret
        $secret = $this->get_webhook_secret();

        // Calcular firma esperada
        $payload = $request->get_body();
        $expected_signature = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

        return hash_equals( $expected_signature, $signature );
    }

    /**
     * Manejar webhook entrante
     */
    public function handle_webhook( $request ) {
        $event = $request->get_param( 'event' );
        $data  = $request->get_param( 'data' );

        if ( empty( $event ) ) {
            return new WP_Error( 'invalid_event', 'Event type required', array( 'status' => 400 ) );
        }

        // Log del webhook recibido
        $this->log_webhook( $event, $data );

        // Procesar según el tipo de evento
        switch ( $event ) {
            case 'license.updated':
                $this->handle_license_updated( $data );
                break;

            case 'license.expired':
                $this->handle_license_expired( $data );
                break;

            case 'license.renewed':
                $this->handle_license_renewed( $data );
                break;

            case 'plan.upgraded':
                $this->handle_plan_changed( $data, 'upgraded' );
                break;

            case 'plan.downgraded':
                $this->handle_plan_changed( $data, 'downgraded' );
                break;

            case 'payment.succeeded':
                $this->handle_payment_event( $data, 'succeeded' );
                break;

            case 'payment.failed':
                $this->handle_payment_event( $data, 'failed' );
                break;

            case 'notification.new':
                $this->handle_new_notification( $data );
                break;

            case 'plugin.update_available':
                $this->handle_plugin_update( $data );
                break;

            case 'system.maintenance':
                $this->handle_maintenance_notification( $data );
                break;

            default:
                // Evento desconocido, log y continuar
                do_action( 'makia_webhook_' . $event, $data );
                break;
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Webhook processed',
        ) );
    }

    /**
     * Manejar actualización de licencia
     */
    private function handle_license_updated( $data ) {
        // Limpiar cache de licencia
        delete_transient( 'makia_license_data' );

        // Sincronizar inmediatamente
        $this->sync_license_data();

        // Disparar acción
        do_action( 'makia_license_updated_webhook', $data );
    }

    /**
     * Manejar expiración de licencia
     */
    private function handle_license_expired( $data ) {
        delete_transient( 'makia_license_data' );

        $this->add_local_notification(
            'license_expired',
            __( 'Tu licencia de MakIA Restaurante ha expirado', 'makia-client' ),
            __( 'Renueva tu licencia para seguir disfrutando de todas las funcionalidades.', 'makia-client' ),
            'error'
        );

        do_action( 'makia_license_expired_webhook', $data );
    }

    /**
     * Manejar renovación de licencia
     */
    private function handle_license_renewed( $data ) {
        delete_transient( 'makia_license_data' );
        $this->sync_license_data();

        $this->add_local_notification(
            'license_renewed',
            __( 'Tu licencia de MakIA Restaurante ha sido renovada', 'makia-client' ),
            sprintf(
                __( 'Válida hasta: %s', 'makia-client' ),
                date_i18n( get_option( 'date_format' ), strtotime( $data['expires_at'] ?? '+1 year' ) )
            ),
            'success'
        );

        do_action( 'makia_license_renewed_webhook', $data );
    }

    /**
     * Manejar cambio de plan
     */
    private function handle_plan_changed( $data, $direction ) {
        delete_transient( 'makia_license_data' );
        $this->sync_license_data();

        $message = $direction === 'upgraded'
            ? __( 'Tu plan ha sido mejorado', 'makia-client' )
            : __( 'Tu plan ha cambiado', 'makia-client' );

        $this->add_local_notification(
            'plan_changed',
            $message,
            sprintf(
                __( 'Nuevo plan: %s', 'makia-client' ),
                $data['new_plan_name'] ?? $data['plan'] ?? 'N/A'
            ),
            $direction === 'upgraded' ? 'success' : 'info'
        );

        do_action( 'makia_plan_changed_webhook', $data, $direction );
    }

    /**
     * Manejar evento de pago
     */
    private function handle_payment_event( $data, $status ) {
        if ( $status === 'succeeded' ) {
            $this->add_local_notification(
                'payment_succeeded',
                __( 'Pago procesado correctamente', 'makia-client' ),
                sprintf(
                    __( 'Importe: %s€', 'makia-client' ),
                    $data['amount'] ?? '0'
                ),
                'success'
            );
        } else {
            $this->add_local_notification(
                'payment_failed',
                __( 'Error en el pago', 'makia-client' ),
                __( 'Por favor, actualiza tu método de pago para evitar interrupciones del servicio.', 'makia-client' ),
                'error'
            );
        }

        do_action( 'makia_payment_' . $status . '_webhook', $data );
    }

    /**
     * Manejar nueva notificación
     */
    private function handle_new_notification( $data ) {
        // Añadir a notificaciones locales
        $notifications = get_transient( 'makia_notifications' ) ?: array();

        array_unshift( $notifications, array(
            'id'         => $data['id'] ?? uniqid( 'notif_' ),
            'type'       => $data['type'] ?? 'announcement',
            'title'      => $data['title'] ?? '',
            'content'    => $data['content'] ?? '',
            'read'       => false,
            'created_at' => $data['created_at'] ?? current_time( 'c' ),
        ) );

        set_transient( 'makia_notifications', $notifications, DAY_IN_SECONDS );

        // Incrementar contador
        $unread = get_option( 'makia_unread_notifications', 0 );
        update_option( 'makia_unread_notifications', $unread + 1 );

        do_action( 'makia_new_notification_webhook', $data );
    }

    /**
     * Manejar actualización del plugin disponible
     */
    private function handle_plugin_update( $data ) {
        set_transient( 'makia_update_available', $data, DAY_IN_SECONDS );

        $this->add_local_notification(
            'update_available',
            sprintf(
                __( 'MakIA Restaurante %s disponible', 'makia-client' ),
                $data['version'] ?? 'Nueva versión'
            ),
            $data['changelog'] ?? __( 'Actualiza para obtener las últimas mejoras.', 'makia-client' ),
            'info'
        );

        // Forzar verificación de actualizaciones de WordPress
        delete_site_transient( 'update_plugins' );

        do_action( 'makia_plugin_update_webhook', $data );
    }

    /**
     * Manejar notificación de mantenimiento
     */
    private function handle_maintenance_notification( $data ) {
        $this->add_local_notification(
            'maintenance',
            __( 'Mantenimiento programado', 'makia-client' ),
            $data['message'] ?? __( 'Se realizarán tareas de mantenimiento próximamente.', 'makia-client' ),
            'warning'
        );

        // Guardar info de mantenimiento
        if ( ! empty( $data['start_time'] ) ) {
            update_option( 'makia_maintenance_scheduled', $data );
        }

        do_action( 'makia_maintenance_webhook', $data );
    }

    /**
     * Notificaciones en admin bar
     */
    public function admin_bar_notifications( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $unread = get_option( 'makia_unread_notifications', 0 );
        $update = get_transient( 'makia_update_available' );
        $license = get_transient( 'makia_license_data' );

        // Determinar estado e icono
        $status = 'ok';
        $badge = '';

        if ( $update ) {
            $status = 'update';
            $badge = '!';
        } elseif ( $unread > 0 ) {
            $status = 'notifications';
            $badge = $unread;
        } elseif ( $license && $license['status'] === 'expired' ) {
            $status = 'error';
            $badge = '!';
        }

        // Nodo principal
        $wp_admin_bar->add_node( array(
            'id'    => 'makia-status',
            'title' => $this->get_admin_bar_title( $status, $badge ),
            'href'  => admin_url( 'admin.php?page=makia-dashboard' ),
            'meta'  => array(
                'class' => 'makia-admin-bar makia-status-' . $status,
            ),
        ) );

        // Sub-nodos
        if ( $license ) {
            $wp_admin_bar->add_node( array(
                'parent' => 'makia-status',
                'id'     => 'makia-plan',
                'title'  => sprintf( __( 'Plan: %s', 'makia-client' ), $license['plan_name'] ),
                'href'   => admin_url( 'admin.php?page=makia-license' ),
            ) );
        }

        if ( $unread > 0 ) {
            $wp_admin_bar->add_node( array(
                'parent' => 'makia-status',
                'id'     => 'makia-notifications',
                'title'  => sprintf( __( '%d notificaciones nuevas', 'makia-client' ), $unread ),
                'href'   => admin_url( 'admin.php?page=makia-updates' ),
            ) );
        }

        if ( $update ) {
            $wp_admin_bar->add_node( array(
                'parent' => 'makia-status',
                'id'     => 'makia-update',
                'title'  => sprintf( __( 'Actualizar a v%s', 'makia-client' ), $update['version'] ),
                'href'   => admin_url( 'plugins.php' ),
            ) );
        }

        // Última sincronización
        $last_sync = get_option( 'makia_last_sync' );
        if ( $last_sync ) {
            $wp_admin_bar->add_node( array(
                'parent' => 'makia-status',
                'id'     => 'makia-last-sync',
                'title'  => sprintf( __( 'Última sync: %s', 'makia-client' ), human_time_diff( strtotime( $last_sync ) ) ),
                'href'   => '#',
                'meta'   => array(
                    'onclick' => 'makiaSyncNow(); return false;',
                ),
            ) );
        }
    }

    /**
     * Obtener título para admin bar
     */
    private function get_admin_bar_title( $status, $badge ) {
        $icon = '<span class="ab-icon dashicons dashicons-food"></span>';
        $text = 'MakIA';

        if ( $badge ) {
            $text .= ' <span class="makia-badge makia-badge-' . $status . '">' . esc_html( $badge ) . '</span>';
        }

        return $icon . '<span class="ab-label">' . $text . '</span>';
    }

    /**
     * Estilos para admin bar
     */
    public function admin_bar_styles() {
        if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <style>
            #wpadminbar .makia-admin-bar .ab-icon::before {
                content: "\f511";
                top: 2px;
            }
            #wpadminbar .makia-admin-bar .makia-badge {
                display: inline-block;
                min-width: 18px;
                height: 18px;
                line-height: 18px;
                text-align: center;
                border-radius: 9px;
                font-size: 11px;
                font-weight: 600;
                margin-left: 4px;
            }
            #wpadminbar .makia-badge-ok { background: #46b450; color: white; }
            #wpadminbar .makia-badge-notifications { background: #0073aa; color: white; }
            #wpadminbar .makia-badge-update { background: #f0b849; color: #23282d; }
            #wpadminbar .makia-badge-error { background: #dc3232; color: white; }
            #wpadminbar .makia-status-error > .ab-item { color: #dc3232 !important; }
        </style>
        <script>
            function makiaSyncNow() {
                if (typeof jQuery !== 'undefined') {
                    jQuery.post(ajaxurl, {
                        action: 'makia_manual_sync',
                        nonce: '<?php echo wp_create_nonce( 'makia_sync' ); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    });
                }
            }
        </script>
        <?php
    }

    /**
     * Hook para verificar actualizaciones del plugin
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $update = get_transient( 'makia_update_available' );

        if ( $update && version_compare( $update['version'], MAKIA_CLIENT_VERSION, '>' ) ) {
            $plugin_slug = plugin_basename( MAKIA_CLIENT_FILE );

            $transient->response[ $plugin_slug ] = (object) array(
                'slug'        => 'makia-client',
                'plugin'      => $plugin_slug,
                'new_version' => $update['version'],
                'url'         => 'https://contacpro.app/plugins/makia-restaurante',
                'package'     => $update['download_url'] ?? '',
                'icons'       => array(
                    'default' => 'https://contacpro.app/images/makia-icon.png',
                ),
                'banners'     => array(
                    'low'  => 'https://contacpro.app/images/makia-banner-772x250.png',
                    'high' => 'https://contacpro.app/images/makia-banner-1544x500.png',
                ),
                'tested'      => $update['tested_wp'] ?? get_bloginfo( 'version' ),
                'requires_php' => $update['requires_php'] ?? '7.4',
            );
        }

        return $transient;
    }

    /**
     * Información del plugin para la pantalla de actualizaciones
     */
    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== 'makia-client' ) {
            return $result;
        }

        $update = get_transient( 'makia_update_available' );

        if ( ! $update ) {
            return $result;
        }

        return (object) array(
            'name'              => 'MakIA Restaurante',
            'slug'              => 'makia-client',
            'version'           => $update['version'],
            'author'            => '<a href="https://contacpro.app">MakIA Team</a>',
            'author_profile'    => 'https://contacpro.app',
            'homepage'          => 'https://contacpro.app/plugins/makia-restaurante',
            'download_link'     => $update['download_url'] ?? '',
            'trunk'             => $update['download_url'] ?? '',
            'requires'          => $update['requires_wp'] ?? '5.0',
            'tested'            => $update['tested_wp'] ?? get_bloginfo( 'version' ),
            'requires_php'      => $update['requires_php'] ?? '7.4',
            'last_updated'      => $update['release_date'] ?? date( 'Y-m-d' ),
            'sections'          => array(
                'description'  => __( 'Sistema de reservas inteligente para restaurantes con widget embebible, notificaciones automáticas y gestión desde dashboard centralizado.', 'makia-client' ),
                'changelog'    => $update['changelog'] ?? '',
                'installation' => __( 'Sube el plugin a tu instalación de WordPress y actívalo desde el panel de plugins.', 'makia-client' ),
            ),
            'banners'           => array(
                'low'  => 'https://contacpro.app/images/makia-banner-772x250.png',
                'high' => 'https://contacpro.app/images/makia-banner-1544x500.png',
            ),
        );
    }

    /**
     * AJAX: Sincronización manual
     */
    public function ajax_manual_sync() {
        check_ajax_referer( 'makia_sync', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $license_synced = $this->sync_license_data();
        $notifications_synced = $this->sync_notifications();

        wp_send_json_success( array(
            'license'       => $license_synced,
            'notifications' => $notifications_synced,
            'last_sync'     => get_option( 'makia_last_sync' ),
        ) );
    }

    /**
     * AJAX: Descartar notificación
     */
    public function ajax_dismiss_notification() {
        check_ajax_referer( 'makia_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $notification_id = sanitize_text_field( $_POST['notification_id'] ?? '' );

        if ( empty( $notification_id ) ) {
            wp_send_json_error( 'ID required' );
        }

        // Marcar como descartada localmente
        $dismissed = get_option( 'makia_dismissed_notifications', array() );
        $dismissed[] = $notification_id;
        update_option( 'makia_dismissed_notifications', array_unique( $dismissed ) );

        // Decrementar contador
        $unread = max( 0, get_option( 'makia_unread_notifications', 0 ) - 1 );
        update_option( 'makia_unread_notifications', $unread );

        wp_send_json_success();
    }

    /**
     * Añadir notificación local
     */
    private function add_local_notification( $id, $title, $content, $type = 'info' ) {
        $local_notifications = get_option( 'makia_local_notifications', array() );

        $local_notifications[ $id ] = array(
            'id'         => $id,
            'type'       => $type,
            'title'      => $title,
            'content'    => $content,
            'created_at' => current_time( 'c' ),
            'read'       => false,
        );

        // Mantener solo las últimas 20 notificaciones locales
        if ( count( $local_notifications ) > 20 ) {
            $local_notifications = array_slice( $local_notifications, -20, 20, true );
        }

        update_option( 'makia_local_notifications', $local_notifications );
    }

    /**
     * Verificar si la licencia cambió
     */
    private function license_changed( $old, $new ) {
        if ( empty( $old ) ) {
            return true;
        }

        $check_fields = array( 'status', 'plan', 'expires_at' );

        foreach ( $check_fields as $field ) {
            if ( ( $old[ $field ] ?? null ) !== ( $new[ $field ] ?? null ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener o generar webhook secret
     */
    private function get_webhook_secret() {
        $secret = get_option( self::WEBHOOK_SECRET_OPTION );

        if ( empty( $secret ) ) {
            $secret = wp_generate_password( 32, false );
            update_option( self::WEBHOOK_SECRET_OPTION, $secret );
        }

        return $secret;
    }

    /**
     * Obtener URL del webhook
     */
    public function get_webhook_url() {
        return rest_url( 'makia/v1/webhook' );
    }

    /**
     * Log de errores de sincronización
     */
    private function log_sync_error( $type, $response ) {
        $errors = get_option( 'makia_sync_errors', array() );

        $errors[] = array(
            'type'    => $type,
            'message' => is_wp_error( $response ) ? $response->get_error_message() : 'Unknown error',
            'time'    => current_time( 'mysql' ),
        );

        // Mantener solo los últimos 50 errores
        if ( count( $errors ) > 50 ) {
            $errors = array_slice( $errors, -50 );
        }

        update_option( 'makia_sync_errors', $errors );
    }

    /**
     * Log de webhook recibido
     */
    private function log_webhook( $event, $data ) {
        $logs = get_option( 'makia_webhook_logs', array() );

        $logs[] = array(
            'event' => $event,
            'time'  => current_time( 'mysql' ),
            'data'  => $data,
        );

        // Mantener solo los últimos 100 logs
        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, -100 );
        }

        update_option( 'makia_webhook_logs', $logs );
    }
}

// Inicializar
function makia_auto_sync() {
    return MakIA_Auto_Sync::get_instance();
}

add_action( 'plugins_loaded', 'makia_auto_sync', 25 );

// Limpiar eventos al desactivar
register_deactivation_hook( MAKIA_CLIENT_FILE, function() {
    wp_clear_scheduled_hook( 'makia_sync_license' );
    wp_clear_scheduled_hook( 'makia_sync_notifications' );
    wp_clear_scheduled_hook( 'makia_check_updates' );
} );
