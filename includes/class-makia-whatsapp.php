<?php
/**
 * Clase para gestionar WhatsApp con WhatsApp Business API
 * 
 * @package MakIA_Reservas
 * @subpackage WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MakIA_WhatsApp {
    /**
     * Token de acceso de WhatsApp
     */
    private $access_token;

    /**
     * ID del número de teléfono de WhatsApp
     */
    private $phone_number_id;

    /**
     * ID de la cuenta de negocio
     */
    private $business_account_id;

    /**
     * URL de la API de WhatsApp
     */
    private $api_url = 'https://graph.facebook.com/v18.0';

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_whatsapp();
        add_action( 'wp_ajax_makia_send_whatsapp', array( $this, 'send_whatsapp_ajax' ) );
        add_action( 'wp_ajax_makia_get_whatsapp_stats', array( $this, 'get_whatsapp_stats_ajax' ) );
        add_action( 'wp_ajax_makia_get_whatsapp_history', array( $this, 'get_whatsapp_history_ajax' ) );
    }

    /**
     * Inicializar configuración de WhatsApp
     */
    private function init_whatsapp() {
        $this->access_token = get_option( 'makia_whatsapp_access_token' );
        $this->phone_number_id = get_option( 'makia_whatsapp_phone_number_id' );
        $this->business_account_id = get_option( 'makia_whatsapp_business_account_id' );
    }

    /**
     * Enviar mensaje de WhatsApp
     * 
     * @param int    $booking_id ID de la reserva
     * @param string $phone Número de teléfono del cliente (con código de país)
     * @param string $message Mensaje de WhatsApp
     * @param string $type Tipo de mensaje (confirmation, reminder, cancellation)
     * 
     * @return array|WP_Error
     */
    public function send_whatsapp( $booking_id, $phone, $message, $type = 'confirmation' ) {
        if ( ! $this->access_token || ! $this->phone_number_id ) {
            return new WP_Error( 'whatsapp_not_configured', 'WhatsApp no está configurado' );
        }

        try {
            // Normalizar número de teléfono
            $phone = $this->normalize_phone( $phone );

            // Preparar payload
            $payload = array(
                'messaging_product' => 'whatsapp',
                'to'                => $phone,
                'type'              => 'text',
                'text'              => array(
                    'body' => $message,
                ),
            );

            // Hacer petición a la API
            $response = wp_remote_post(
                $this->api_url . '/' . $this->phone_number_id . '/messages',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode( $payload ),
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                error_log( 'WhatsApp API Error: ' . $response->get_error_message() );
                return $response;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $response_code !== 200 ) {
                error_log( 'WhatsApp API Error: ' . wp_json_encode( $response_body ) );
                return new WP_Error( 'whatsapp_error', 'Error al enviar mensaje de WhatsApp' );
            }

            // Guardar registro en BD
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'makia_whatsapp_logs',
                array(
                    'booking_id'      => $booking_id,
                    'phone'           => $phone,
                    'message'         => $message,
                    'type'            => $type,
                    'whatsapp_msg_id' => $response_body['messages'][0]['id'] ?? '',
                    'status'          => 'sent',
                    'sent_at'         => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
            );

            return array(
                'success'  => true,
                'msg_id'   => $response_body['messages'][0]['id'] ?? '',
                'message'  => 'Mensaje de WhatsApp enviado correctamente',
            );
        } catch ( Exception $e ) {
            error_log( 'Error sending WhatsApp: ' . $e->getMessage() );
            return new WP_Error( 'whatsapp_error', 'Error al enviar WhatsApp: ' . $e->getMessage() );
        }
    }

    /**
     * Enviar confirmación de reserva por WhatsApp
     * 
     * @param int $booking_id ID de la reserva
     */
    public function send_confirmation_whatsapp( $booking_id ) {
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
        $template = get_option( 'makia_whatsapp_confirmation_template' );
        if ( ! $template ) {
            $template = 'Hola {customer_name}, tu reserva ha sido confirmada para el {booking_date} a las {booking_time}. ¡Te esperamos en {restaurant_name}! 🍽️';
        }

        // Reemplazar variables
        $message = $this->replace_variables( $template, $booking );

        return $this->send_whatsapp( $booking_id, $booking->customer_phone, $message, 'confirmation' );
    }

    /**
     * Enviar recordatorio de reserva por WhatsApp
     * 
     * @param int $booking_id ID de la reserva
     */
    public function send_reminder_whatsapp( $booking_id ) {
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
        $template = get_option( 'makia_whatsapp_reminder_template' );
        if ( ! $template ) {
            $template = 'Recordatorio: Tu reserva es mañana a las {booking_time} en {restaurant_name}. ¿Necesitas cambiar algo? Responde SÍ o NO. 📅';
        }

        // Reemplazar variables
        $message = $this->replace_variables( $template, $booking );

        return $this->send_whatsapp( $booking_id, $booking->customer_phone, $message, 'reminder' );
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
            '{customer_name}'   => $booking->customer_name,
            '{customer_email}'  => $booking->customer_email,
            '{customer_phone}'  => $booking->customer_phone,
            '{booking_date}'    => date( 'd/m/Y', strtotime( $booking->booking_date ) ),
            '{booking_time}'    => date( 'H:i', strtotime( $booking->booking_date ) ),
            '{guests}'          => $booking->guests,
            '{restaurant_name}' => get_option( 'makia_restaurant_name', 'nuestro restaurante' ),
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
     * Obtener estadísticas de WhatsApp
     * 
     * @param string $period Período (day, week, month)
     * 
     * @return array
     */
    public function get_whatsapp_stats( $period = 'day' ) {
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
                    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN type = 'confirmation' THEN 1 ELSE 0 END) as confirmations,
                    SUM(CASE WHEN type = 'reminder' THEN 1 ELSE 0 END) as reminders,
                    SUM(CASE WHEN has_response = 1 THEN 1 ELSE 0 END) as with_response
                FROM {$wpdb->prefix}makia_whatsapp_logs
                WHERE sent_at >= %s",
                $start_date
            )
        );

        return array(
            'total_sent'      => (int) $stats->total_sent,
            'delivered'       => (int) $stats->delivered,
            'read'            => (int) $stats->read,
            'failed'          => (int) $stats->failed,
            'confirmations'   => (int) $stats->confirmations,
            'reminders'       => (int) $stats->reminders,
            'with_response'   => (int) $stats->with_response,
            'delivery_rate'   => $stats->total_sent > 0 ? round( ( $stats->delivered / $stats->total_sent ) * 100, 2 ) : 0,
            'read_rate'       => $stats->total_sent > 0 ? round( ( $stats->read / $stats->total_sent ) * 100, 2 ) : 0,
            'response_rate'   => $stats->total_sent > 0 ? round( ( $stats->with_response / $stats->total_sent ) * 100, 2 ) : 0,
            'period'          => $period,
        );
    }

    /**
     * Obtener historial de WhatsApp
     * 
     * @param int $limit Límite de registros
     * @param int $offset Offset
     * 
     * @return array
     */
    public function get_whatsapp_history( $limit = 50, $offset = 0 ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}makia_whatsapp_logs 
                ORDER BY sent_at DESC 
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Actualizar estado de mensaje desde webhook de WhatsApp
     * 
     * @param string $msg_id ID del mensaje
     * @param string $status Estado
     */
    public function update_message_status( $msg_id, $status ) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'makia_whatsapp_logs',
            array( 'status' => $status ),
            array( 'whatsapp_msg_id' => $msg_id ),
            array( '%s' ),
            array( '%s' )
        );
    }

    /**
     * Procesar respuesta de cliente
     * 
     * @param string $phone Número de teléfono del cliente
     * @param string $message Mensaje del cliente
     * @param string $msg_id ID del mensaje
     */
    public function process_customer_response( $phone, $message, $msg_id ) {
        global $wpdb;

        // Normalizar número
        $phone = $this->normalize_phone( $phone );

        // Buscar el mensaje original
        $original = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}makia_whatsapp_logs WHERE phone = %s ORDER BY sent_at DESC LIMIT 1",
                $phone
            )
        );

        if ( $original ) {
            // Marcar como que tiene respuesta
            $wpdb->update(
                $wpdb->prefix . 'makia_whatsapp_logs',
                array(
                    'has_response'    => 1,
                    'customer_response' => $message,
                    'response_at'     => current_time( 'mysql' ),
                ),
                array( 'id' => $original->id ),
                array( '%d', '%s', '%s' ),
                array( '%d' )
            );

            // Procesar respuesta automática
            $this->process_auto_response( $original->booking_id, $message );
        }
    }

    /**
     * Procesar respuesta automática basada en palabras clave
     * 
     * @param int    $booking_id ID de la reserva
     * @param string $response Respuesta del cliente
     */
    private function process_auto_response( $booking_id, $response ) {
        $response_lower = strtolower( $response );

        // Respuestas para confirmación
        if ( strpos( $response_lower, 'sí' ) !== false || strpos( $response_lower, 'si' ) !== false ) {
            // Cliente confirma
            do_action( 'makia_customer_confirmed_via_whatsapp', $booking_id );
        } elseif ( strpos( $response_lower, 'no' ) !== false || strpos( $response_lower, 'cancelar' ) !== false ) {
            // Cliente cancela
            do_action( 'makia_customer_cancelled_via_whatsapp', $booking_id );
        }
    }

    /**
     * AJAX: Enviar WhatsApp
     */
    public function send_whatsapp_ajax() {
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

        $result = $this->send_whatsapp( $booking_id, $phone, $message, $type );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Obtener estadísticas de WhatsApp
     */
    public function get_whatsapp_stats_ajax() {
        check_ajax_referer( 'makia_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No tienes permisos' );
        }

        $period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : 'day';
        $stats = $this->get_whatsapp_stats( $period );

        wp_send_json_success( $stats );
    }

    /**
     * AJAX: Obtener historial de WhatsApp
     */
    public function get_whatsapp_history_ajax() {
        check_ajax_referer( 'makia_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No tienes permisos' );
        }

        $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 50;
        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $history = $this->get_whatsapp_history( $limit, $offset );

        wp_send_json_success( $history );
    }
}

// Instanciar la clase
new MakIA_WhatsApp();
