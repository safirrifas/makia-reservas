<?php
/**
 * Clase para gestionar SMS con Twilio
 * 
 * @package MakIA_Reservas
 * @subpackage SMS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MakIA_SMS {
    /**
     * Instancia de Twilio
     */
    private $twilio_client;

    /**
     * Número de teléfono de Twilio
     */
    private $twilio_phone;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_twilio();
        add_action( 'wp_ajax_makia_send_sms', array( $this, 'send_sms_ajax' ) );
        add_action( 'wp_ajax_makia_get_sms_stats', array( $this, 'get_sms_stats_ajax' ) );
        add_action( 'wp_ajax_makia_get_sms_history', array( $this, 'get_sms_history_ajax' ) );
    }

    /**
     * Inicializar cliente de Twilio
     */
    private function init_twilio() {
        $twilio_sid = get_option( 'makia_twilio_account_sid' );
        $twilio_token = get_option( 'makia_twilio_auth_token' );
        $this->twilio_phone = get_option( 'makia_twilio_phone' );

        if ( $twilio_sid && $twilio_token ) {
            require_once dirname( __FILE__ ) . '/../vendor/autoload.php';
            try {
                $this->twilio_client = new \Twilio\Rest\Client( $twilio_sid, $twilio_token );
            } catch ( Exception $e ) {
                error_log( 'Error initializing Twilio: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Enviar SMS a un cliente
     * 
     * @param int    $booking_id ID de la reserva
     * @param string $phone Número de teléfono del cliente
     * @param string $message Mensaje SMS
     * @param string $type Tipo de SMS (confirmation, reminder, cancellation)
     * 
     * @return array|WP_Error
     */
    public function send_sms( $booking_id, $phone, $message, $type = 'confirmation' ) {
        if ( ! $this->twilio_client || ! $this->twilio_phone ) {
            return new WP_Error( 'twilio_not_configured', 'Twilio no está configurado' );
        }

        try {
            // Normalizar número de teléfono
            $phone = $this->normalize_phone( $phone );

            // Enviar SMS
            $message_obj = $this->twilio_client->messages->create(
                $phone,
                array(
                    'from' => $this->twilio_phone,
                    'body' => $message,
                )
            );

            // Guardar registro en BD
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'makia_sms_logs',
                array(
                    'booking_id'   => $booking_id,
                    'phone'        => $phone,
                    'message'      => $message,
                    'type'         => $type,
                    'twilio_sid'   => $message_obj->sid,
                    'status'       => 'sent',
                    'sent_at'      => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
            );

            return array(
                'success' => true,
                'sid'     => $message_obj->sid,
                'message' => 'SMS enviado correctamente',
            );
        } catch ( Exception $e ) {
            MakIA_Logger::log('error', 'Error al enviar SMS: ' . $e->getMessage());
            return new WP_Error( 'sms_error', 'Error al enviar SMS. Revisa los logs para más detalles.' );
        }
    }

    /**
     * Enviar confirmación de reserva
     * 
     * @param int $booking_id ID de la reserva
     */
    public function send_confirmation_sms( $booking_id ) {
        global $wpdb;

        // Obtener datos de la reserva
        $booking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}makia_bookings WHERE id = %d",
                $booking_id
            )
        );

        if ( ! $booking ) {
            return new WP_Error( 'booking_not_found', 'Reserva no encontrada' );
        }

        // Obtener plantilla de confirmación
        $template = get_option( 'makia_sms_confirmation_template' );
        if ( ! $template ) {
            $template = 'Hola {customer_name}, tu reserva ha sido confirmada para el {booking_date} a las {booking_time}. Gracias por tu reserva.';
        }

        // Reemplazar variables
        $message = $this->replace_variables( $template, $booking );

        return $this->send_sms( $booking_id, $booking->customer_phone, $message, 'confirmation' );
    }

    /**
     * Enviar recordatorio de reserva
     * 
     * @param int $booking_id ID de la reserva
     */
    public function send_reminder_sms( $booking_id ) {
        global $wpdb;

        // Obtener datos de la reserva
        $booking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}makia_bookings WHERE id = %d",
                $booking_id
            )
        );

        if ( ! $booking ) {
            return new WP_Error( 'booking_not_found', 'Reserva no encontrada' );
        }

        // Obtener plantilla de recordatorio
        $template = get_option( 'makia_sms_reminder_template' );
        if ( ! $template ) {
            $template = 'Recordatorio: Tu reserva es mañana a las {booking_time}. ¿Necesitas cambiar algo? Responde SÍ o NO.';
        }

        // Reemplazar variables
        $message = $this->replace_variables( $template, $booking );

        return $this->send_sms( $booking_id, $booking->customer_phone, $message, 'reminder' );
    }

    /**
     * Reemplazar variables en plantilla
     * 
     * @param string $template Plantilla con variables
     * @param object $booking Objeto de reserva
     * 
     * @return string
     */
    private function replace_variables( $template, $booking ) {
        $replacements = array(
            '{customer_name}'  => $booking->customer_name,
            '{customer_email}' => $booking->customer_email,
            '{customer_phone}' => $booking->customer_phone,
            '{booking_date}'   => date( 'd/m/Y', strtotime( $booking->booking_date ) ),
            '{booking_time}'   => date( 'H:i', strtotime( $booking->booking_date ) ),
            '{guests}'         => $booking->guests,
            '{status}'         => $booking->status,
        );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }

    /**
     * Normalizar número de teléfono
     * 
     * @param string $phone Número de teléfono
     * 
     * @return string
     */
    private function normalize_phone( $phone ) {
        // Remover espacios, guiones y paréntesis
        $phone = preg_replace( '/[^0-9+]/', '', $phone );

        // Si no comienza con +, agregar código de país
        if ( strpos( $phone, '+' ) !== 0 ) {
            $phone = '+34' . ltrim( $phone, '0' ); // Asumir España
        }

        return $phone;
    }

    /**
     * Obtener estadísticas de SMS
     * 
     * @param string $period Período (day, week, month)
     * 
     * @return array
     */
    public function get_sms_stats( $period = 'day' ) {
        global $wpdb;

        // Calcular fecha de inicio
        $start_date = current_time( 'mysql' );
        switch ( $period ) {
            case 'week':
                $start_date = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
                break;
            case 'month':
                $start_date = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
                break;
            case 'day':
            default:
                $start_date = date( 'Y-m-d 00:00:00' );
                break;
        }

        // Obtener estadísticas
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN type = 'confirmation' THEN 1 ELSE 0 END) as confirmations,
                    SUM(CASE WHEN type = 'reminder' THEN 1 ELSE 0 END) as reminders
                FROM {$wpdb->prefix}makia_sms_logs
                WHERE sent_at >= %s",
                $start_date
            )
        );

        return array(
            'total_sent'      => (int) $stats->total_sent,
            'delivered'       => (int) $stats->delivered,
            'failed'          => (int) $stats->failed,
            'confirmations'   => (int) $stats->confirmations,
            'reminders'       => (int) $stats->reminders,
            'delivery_rate'   => $stats->total_sent > 0 ? round( ( $stats->delivered / $stats->total_sent ) * 100, 2 ) : 0,
            'period'          => $period,
        );
    }

    /**
     * Obtener historial de SMS
     * 
     * @param int $limit Límite de registros
     * @param int $offset Offset
     * 
     * @return array
     */
    public function get_sms_history( $limit = 50, $offset = 0 ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}makia_sms_logs 
                ORDER BY sent_at DESC 
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Actualizar estado de SMS desde webhook de Twilio
     * 
     * @param string $sid SID del mensaje
     * @param string $status Estado
     */
    public function update_sms_status( $sid, $status ) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'makia_sms_logs',
            array( 'status' => $status ),
            array( 'twilio_sid' => $sid ),
            array( '%s' ),
            array( '%s' )
        );
    }

    /**
     * AJAX: Enviar SMS
     */
    public function send_sms_ajax() {
        check_ajax_referer( 'makia_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No tienes permisos' );
        }

        $booking_id = isset( $_POST['booking_id'] ) ? intval( $_POST['booking_id'] ) : 0;
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';
        $type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'confirmation';

        if ( ! $booking_id || ! $phone || ! $message ) {
            wp_send_json_error( 'Faltan parámetros requeridos' );
        }

        $result = $this->send_sms( $booking_id, $phone, $message, $type );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Obtener estadísticas de SMS
     */
    public function get_sms_stats_ajax() {
        check_ajax_referer( 'makia_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No tienes permisos' );
        }

        $period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : 'day';
        $stats = $this->get_sms_stats( $period );

        wp_send_json_success( $stats );
    }

    /**
     * AJAX: Obtener historial de SMS
     */
    public function get_sms_history_ajax() {
        check_ajax_referer( 'makia_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No tienes permisos' );
        }

        $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 50;
        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $history = $this->get_sms_history( $limit, $offset );

        wp_send_json_success( $history );
    }
}

// Instanciar la clase
new MakIA_SMS();
