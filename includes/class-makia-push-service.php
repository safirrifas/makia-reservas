<?php
/**
 * Clase para gestionar Web Push Notifications
 * Envía notificaciones push a los operarios en tiempo real
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MakIA_Push_Service {
	/**
	 * Clave privada VAPID (debe configurarse en wp-config.php)
	 */
	private $vapid_private_key;

	/**
	 * Clave pública VAPID
	 */
	private $vapid_public_key;

	/**
	 * Email del sitio para VAPID
	 */
	private $vapid_email;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->vapid_private_key = defined( 'MAKIA_VAPID_PRIVATE_KEY' ) ? MAKIA_VAPID_PRIVATE_KEY : '';
		$this->vapid_public_key  = defined( 'MAKIA_VAPID_PUBLIC_KEY' ) ? MAKIA_VAPID_PUBLIC_KEY : '';
		$this->vapid_email       = 'mailto:' . get_option( 'admin_email' );

		// Hooks para enviar notificaciones
		add_action( 'makia_booking_created', array( $this, 'notify_new_booking' ), 10, 2 );
		add_action( 'makia_booking_status_changed', array( $this, 'notify_status_changed' ), 10, 3 );
		add_action( 'makia_send_push', array( $this, 'send_push_notification' ), 10, 3 );
	}

	/**
	 * Notificar cuando se crea una nueva reserva
	 */
	public function notify_new_booking( $booking_id, $booking_data ) {
		$title = 'Nueva Reserva';
		$body  = sprintf(
			'Nueva reserva de %s para %s',
			$booking_data['customer_name'] ?? 'Cliente',
			$booking_data['booking_date'] ?? 'pronto'
		);

		// Enviar a todos los operarios
		$this->send_to_all_operators( $title, $body, $booking_id );
	}

	/**
	 * Notificar cuando cambia el estado de una reserva
	 */
	public function notify_status_changed( $booking_id, $status, $operator_id ) {
		$status_labels = array(
			'pending'     => 'Pendiente',
			'confirmed'   => 'Confirmada',
			'cancelled'   => 'Cancelada',
			'completed'   => 'Completada',
			'no_show'     => 'No se presentó',
		);

		$title = 'Cambio de Estado';
		$body  = sprintf(
			'Reserva actualizada a: %s',
			$status_labels[ $status ] ?? $status
		);

		// Notificar al operario que hizo el cambio
		$this->send_to_operator( $operator_id, $title, $body, $booking_id );
	}

	/**
	 * Enviar notificación a todos los operarios
	 */
	private function send_to_all_operators( $title, $body, $booking_id = null ) {
		global $wpdb;
		$users_table = $wpdb->prefix . 'users';
		$usermeta_table = $wpdb->prefix . 'usermeta';

		// Obtener todos los operarios
		$operators = $wpdb->get_results(
			"SELECT u.ID FROM {$users_table} u
			INNER JOIN {$usermeta_table} um ON u.ID = um.user_id
			WHERE um.meta_key = '{$wpdb->prefix}capabilities'
			AND um.meta_value LIKE '%makia_operator%'"
		);

		foreach ( $operators as $operator ) {
			$this->send_to_operator( $operator->ID, $title, $body, $booking_id );
		}
	}

	/**
	 * Enviar notificación a un operario específico
	 */
	private function send_to_operator( $operator_id, $title, $body, $booking_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'makia_push_subscriptions';

		$subscriptions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE operator_id = %d",
				$operator_id
			)
		);

		foreach ( $subscriptions as $subscription ) {
			$this->send_push_notification( $subscription, $title, $body, $booking_id );
		}
	}

	/**
	 * Enviar notificación push real
	 */
	public function send_push_notification( $subscription, $title, $body, $booking_id = null ) {
		if ( ! $this->vapid_private_key || ! $this->vapid_public_key ) {
			error_log( '[MakIA Push] VAPID keys no configuradas' );
			return false;
		}

		try {
			// Preparar payload
			$payload = array(
				'title'       => $title,
				'body'        => $body,
				'icon'        => get_site_icon_url( 192 ),
				'badge'       => get_site_icon_url( 96 ),
				'tag'         => 'makia-notification',
				'requireInteraction' => true,
				'data'        => array(
					'booking_id' => $booking_id,
					'url'        => admin_url( 'admin.php?page=makia-reservas' ),
				),
			);

			// Crear petición
			$response = wp_remote_post(
				$subscription->endpoint,
				array(
					'method'  => 'POST',
					'headers' => array(
						'Content-Type'     => 'application/json',
						'TTL'              => '24',
						'Urgency'          => 'high',
						'Authorization'    => $this->get_authorization_header(),
					),
					'body'    => wp_json_encode( $payload ),
					'timeout' => 10,
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( '[MakIA Push] Error: ' . $response->get_error_message() );
				return false;
			}

			$status = wp_remote_retrieve_response_code( $response );

			if ( 201 === $status || 200 === $status ) {
				error_log( '[MakIA Push] Notificación enviada exitosamente' );
				return true;
			} else {
				error_log( '[MakIA Push] Error HTTP ' . $status );
				return false;
			}
		} catch ( Exception $e ) {
			error_log( '[MakIA Push] Excepción: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Generar header de autorización VAPID
	 */
	private function get_authorization_header() {
		// Aquí se usaría una librería JWT real
		// Por ahora, retornar un placeholder
		return 'vapid t=' . base64_encode( 'token' ) . ', k=' . $this->vapid_public_key;
	}

	/**
	 * Enviar notificación de recordatorio (24h antes)
	 */
	public function send_reminder_notifications() {
		global $wpdb;
		$bookings_table = $wpdb->prefix . 'makia_bookings';

		// Obtener reservas para mañana
		$tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$bookings_table} 
				WHERE DATE(booking_date) = %s 
				AND status IN ('pending', 'confirmed')",
				$tomorrow
			)
		);

		foreach ( $bookings as $booking ) {
			$title = 'Recordatorio de Reserva';
			$body  = sprintf(
				'Reserva de %s para mañana a las %s',
				$booking->customer_name,
				date( 'H:i', strtotime( $booking->booking_date ) )
			);

			$this->send_to_all_operators( $title, $body, $booking->id );
		}
	}

	/**
	 * Obtener clave pública VAPID para el cliente
	 */
	public static function get_vapid_public_key() {
		return defined( 'MAKIA_VAPID_PUBLIC_KEY' ) ? MAKIA_VAPID_PUBLIC_KEY : '';
	}
}

// Instanciar la clase
new MakIA_Push_Service();

/**
 * Programar envío de recordatorios diarios
 */
function makia_schedule_push_reminders() {
	if ( ! wp_next_scheduled( 'makia_send_reminders' ) ) {
		wp_schedule_event( time(), 'daily', 'makia_send_reminders' );
	}
}
add_action( 'wp', 'makia_schedule_push_reminders' );

add_action( 'makia_send_reminders', function() {
	$push_service = new MakIA_Push_Service();
	$push_service->send_reminder_notifications();
} );
