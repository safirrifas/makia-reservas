<?php
/**
 * Clase para manejar webhooks de WhatsApp
 * 
 * @package MakIA_Reservas
 * @subpackage WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MakIA_WhatsApp_Webhook {
    /**
     * Token de verificación del webhook
     */
    private $verify_token;

    /**
     * Constructor
     */
    public function __construct() {
        $this->verify_token = get_option( 'makia_whatsapp_verify_token' );
        add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
    }

    /**
     * Registrar ruta del webhook en la API REST
     */
    public function register_webhook_route() {
        register_rest_route(
            'makia/v1',
            '/whatsapp/webhook',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'verify_webhook' ),
                    'permission_callback' => '__return_true',
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'handle_webhook' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );
    }

    /**
     * Verificar webhook (GET)
     * 
     * @param WP_REST_Request $request
     * 
     * @return WP_REST_Response
     */
    public function verify_webhook( WP_REST_Request $request ) {
        $mode = $request->get_param( 'hub_mode' );
        $token = $request->get_param( 'hub_verify_token' );
        $challenge = $request->get_param( 'hub_challenge' );

        if ( $mode === 'subscribe' && $token === $this->verify_token ) {
            return new WP_REST_Response( $challenge, 200 );
        }

        return new WP_REST_Response( 'Forbidden', 403 );
    }

    /**
     * Manejar webhook (POST)
     * 
     * @param WP_REST_Request $request
     * 
     * @return WP_REST_Response
     */
    public function handle_webhook( WP_REST_Request $request ) {
        $body = $request->get_json_params();

        // Validar que sea un evento de WhatsApp
        if ( ! isset( $body['entry'] ) ) {
            return new WP_REST_Response( 'OK', 200 );
        }

        // Procesar cada entrada
        foreach ( $body['entry'] as $entry ) {
            if ( ! isset( $entry['changes'] ) ) {
                continue;
            }

            foreach ( $entry['changes'] as $change ) {
                $value = $change['value'];

                // Procesar mensajes
                if ( isset( $value['messages'] ) ) {
                    foreach ( $value['messages'] as $message ) {
                        $this->process_incoming_message( $message, $value );
                    }
                }

                // Procesar estados de mensajes
                if ( isset( $value['statuses'] ) ) {
                    foreach ( $value['statuses'] as $status ) {
                        $this->process_message_status( $status );
                    }
                }
            }
        }

        return new WP_REST_Response( 'OK', 200 );
    }

    /**
     * Procesar mensaje entrante de cliente
     * 
     * @param array $message Datos del mensaje
     * @param array $value Datos de la entrada
     */
    private function process_incoming_message( $message, $value ) {
        $from = $message['from'];
        $msg_id = $message['id'];
        $timestamp = $message['timestamp'];
        $text = '';

        // Extraer texto del mensaje
        if ( isset( $message['text']['body'] ) ) {
            $text = $message['text']['body'];
        } elseif ( isset( $message['button']['text'] ) ) {
            $text = $message['button']['text'];
        }

        // Guardar mensaje en BD
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'makia_whatsapp_incoming',
            array(
                'phone'     => $from,
                'message'   => $text,
                'msg_id'    => $msg_id,
                'timestamp' => date( 'Y-m-d H:i:s', $timestamp ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        // Procesar respuesta automática
        $this->process_auto_response( $from, $text );

        // Disparar hook para acciones personalizadas
        do_action( 'makia_whatsapp_message_received', $from, $text, $msg_id );
    }

    /**
     * Procesar estado de mensaje
     * 
     * @param array $status Datos del estado
     */
    private function process_message_status( $status ) {
        $msg_id = $status['id'];
        $status_value = $status['status'];
        $timestamp = $status['timestamp'];

        // Mapear estados de WhatsApp a nuestros estados
        $status_map = array(
            'sent'      => 'sent',
            'delivered' => 'delivered',
            'read'      => 'read',
            'failed'    => 'failed',
        );

        $mapped_status = $status_map[ $status_value ] ?? $status_value;

        // Actualizar estado en BD
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'makia_whatsapp_logs',
            array(
                'status'      => $mapped_status,
                'updated_at'  => date( 'Y-m-d H:i:s', $timestamp ),
            ),
            array( 'whatsapp_msg_id' => $msg_id ),
            array( '%s', '%s' ),
            array( '%s' )
        );

        // Disparar hook para acciones personalizadas
        do_action( 'makia_whatsapp_message_status_updated', $msg_id, $mapped_status );
    }

    /**
     * Procesar respuesta automática basada en palabras clave
     * 
     * @param string $phone Número de teléfono del cliente
     * @param string $response Respuesta del cliente
     */
    private function process_auto_response( $phone, $response ) {
        global $wpdb;

        // Normalizar respuesta
        $response_lower = strtolower( trim( $response ) );

        // Buscar reserva asociada
        $booking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}makia_bookings 
                WHERE customer_phone LIKE %s 
                ORDER BY booking_date DESC LIMIT 1",
                '%' . $phone . '%'
            )
        );

        if ( ! $booking ) {
            return;
        }

        // Procesar respuesta según palabras clave
        if ( strpos( $response_lower, 'sí' ) !== false || strpos( $response_lower, 'si' ) !== false || strpos( $response_lower, 'yes' ) !== false ) {
            // Cliente confirma
            do_action( 'makia_customer_confirmed_via_whatsapp', $booking->id, $phone );
            $this->send_auto_reply( $phone, 'Perfecto! Tu reserva está confirmada. ¡Te esperamos! 🍽️' );
        } elseif ( strpos( $response_lower, 'no' ) !== false || strpos( $response_lower, 'cancelar' ) !== false || strpos( $response_lower, 'cancel' ) !== false ) {
            // Cliente cancela
            do_action( 'makia_customer_cancelled_via_whatsapp', $booking->id, $phone );
            $this->send_auto_reply( $phone, 'Entendido. Tu reserva ha sido cancelada. Si deseas hacer una nueva, contáctanos. 📞' );
        } elseif ( strpos( $response_lower, 'cambiar' ) !== false || strpos( $response_lower, 'change' ) !== false || strpos( $response_lower, 'modificar' ) !== false ) {
            // Cliente quiere cambiar
            do_action( 'makia_customer_wants_to_change_via_whatsapp', $booking->id, $phone );
            $this->send_auto_reply( $phone, 'Claro! Nuestro equipo se pondrá en contacto contigo pronto para ayudarte. 📞' );
        }

        // Guardar respuesta del cliente
        $wpdb->update(
            $wpdb->prefix . 'makia_whatsapp_logs',
            array(
                'has_response'      => 1,
                'customer_response' => $response,
                'response_at'       => current_time( 'mysql' ),
            ),
            array(
                'phone'     => $phone,
                'booking_id' => $booking->id,
            ),
            array( '%d', '%s', '%s' ),
            array( '%s', '%d' )
        );
    }

    /**
     * Enviar respuesta automática
     * 
     * @param string $phone Número de teléfono
     * @param string $message Mensaje de respuesta
     */
    private function send_auto_reply( $phone, $message ) {
        // Obtener instancia de WhatsApp
        $whatsapp = new MakIA_WhatsApp();

        // Enviar respuesta
        $whatsapp->send_whatsapp( 0, $phone, $message, 'auto_reply' );
    }
}

// Instanciar la clase
new MakIA_WhatsApp_Webhook();
