<?php
/**
 * Clase para gestionar la API REST del plugin
 * Proporciona endpoints para sincronizar datos con la app PWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MakIA_API {
	/**
	 * Namespace de la API
	 */
	private $namespace = 'makia/v1';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registrar rutas de la API
	 */
	public function register_routes() {
		// Autenticación
		register_rest_route(
			$this->namespace,
			'/operator/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'operator_login' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'password' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		// Obtener operario autenticado
		register_rest_route(
			$this->namespace,
			'/operator/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_operator_me' ),
				'permission_callback' => array( $this, 'check_operator_permission' ),
			)
		);

		// Obtener reservas del operario
		register_rest_route(
			$this->namespace,
			'/operator/bookings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_operator_bookings' ),
				'permission_callback' => array( $this, 'check_operator_permission' ),
				'args'                => array(
					'date'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Actualizar estado de reserva
		register_rest_route(
			$this->namespace,
			'/bookings/(?P<id>\d+)/status',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_booking_status' ),
				'permission_callback' => array( $this, 'check_operator_permission' ),
				'args'                => array(
					'id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'status' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Agregar nota a reserva
		register_rest_route(
			$this->namespace,
			'/bookings/(?P<id>\d+)/notes',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add_booking_note' ),
				'permission_callback' => array( $this, 'check_operator_permission' ),
				'args'                => array(
					'id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'content' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// Obtener notas de reserva
		register_rest_route(
			$this->namespace,
			'/bookings/(?P<id>\d+)/notes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_booking_notes' ),
				'permission_callback' => array( $this, 'check_operator_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Registrar dispositivo para push notifications
		register_rest_route(
			$this->namespace,
			'/operator/push-subscription',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'register_push_subscription' ),
				'permission_callback' => array( $this, 'check_operator_permission' ),
				'args'                => array(
					'endpoint' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
					'auth'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'p256dh'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Buscar reservas
		register_rest_route(
			$this->namespace,
			'/bookings/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_bookings' ),
				'permission_callback' => array( $this, 'check_operator_permission' ),
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Login de operario
	 */
	public function operator_login( $request ) {
		$email    = $request->get_param( 'email' );
		$password = $request->get_param( 'password' );

		// Validar credenciales contra WordPress
		$user = wp_authenticate( $email, $password );

		if ( is_wp_error( $user ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Credenciales inválidas',
				),
				401
			);
		}

		// Verificar que el usuario tiene el rol de operario
		if ( ! in_array( 'makia_operator', $user->roles, true ) && ! in_array( 'administrator', $user->roles, true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'No tienes permisos para acceder',
				),
				403
			);
		}

		// Generar JWT token
		$token = $this->generate_jwt_token( $user );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'token'    => $token,
				'operator' => array(
					'id'    => $user->ID,
					'email' => $user->user_email,
					'name'  => $user->display_name,
					'role'  => $user->roles[0] ?? 'makia_operator',
				),
			),
			200
		);
	}

	/**
	 * Obtener datos del operario autenticado
	 */
	public function get_operator_me( $request ) {
		$operator_id = $this->get_operator_from_token( $request );

		if ( ! $operator_id ) {
			return new WP_REST_Response(
				array( 'message' => 'No autorizado' ),
				401
			);
		}

		$user = get_user_by( 'id', $operator_id );

		return new WP_REST_Response(
			array(
				'id'    => $user->ID,
				'email' => $user->user_email,
				'name'  => $user->display_name,
				'role'  => $user->roles[0] ?? 'makia_operator',
			),
			200
		);
	}

	/**
	 * Obtener reservas del operario
	 */
	public function get_operator_bookings( $request ) {
		$operator_id = $this->get_operator_from_token( $request );

		if ( ! $operator_id ) {
			return new WP_REST_Response(
				array( 'message' => 'No autorizado' ),
				401
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'makia_bookings';

		$date   = $request->get_param( 'date' );
		$status = $request->get_param( 'status' );

		$query = "SELECT * FROM {$table} WHERE 1=1";

		if ( $date ) {
			$query .= $wpdb->prepare( ' AND DATE(booking_date) = %s', $date );
		}

		if ( $status ) {
			$query .= $wpdb->prepare( ' AND status = %s', $status );
		}

		$query .= ' ORDER BY booking_date DESC';

		$bookings = $wpdb->get_results( $query );

		return new WP_REST_Response( $bookings, 200 );
	}

	/**
	 * Actualizar estado de reserva
	 */
	public function update_booking_status( $request ) {
		$operator_id = $this->get_operator_from_token( $request );

		if ( ! $operator_id ) {
			return new WP_REST_Response(
				array( 'message' => 'No autorizado' ),
				401
			);
		}

		$booking_id = $request->get_param( 'id' );
		$status     = $request->get_param( 'status' );

		global $wpdb;
		$table = $wpdb->prefix . 'makia_bookings';

		$result = $wpdb->update(
			$table,
			array(
				'status'      => $status,
				'modified_at' => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( ! $result ) {
			return new WP_REST_Response(
				array( 'message' => 'Error al actualizar la reserva' ),
				500
			);
		}

		// Registrar en auditoría
		do_action( 'makia_booking_status_changed', $booking_id, $status, $operator_id );

		// Enviar notificación push si hay nuevas reservas
		if ( 'confirmed' === $status ) {
			$this->send_push_notification(
				$operator_id,
				'Reserva confirmada',
				'Has confirmado una reserva exitosamente'
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Reserva actualizada',
			),
			200
		);
	}

	/**
	 * Agregar nota a reserva
	 */
	public function add_booking_note( $request ) {
		$operator_id = $this->get_operator_from_token( $request );

		if ( ! $operator_id ) {
			return new WP_REST_Response(
				array( 'message' => 'No autorizado' ),
				401
			);
		}

		$booking_id = $request->get_param( 'id' );
		$content    = $request->get_param( 'content' );

		global $wpdb;
		$notes_table = $wpdb->prefix . 'makia_booking_notes';

		$result = $wpdb->insert(
			$notes_table,
			array(
				'booking_id'  => $booking_id,
				'operator_id' => $operator_id,
				'content'     => $content,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return new WP_REST_Response(
				array( 'message' => 'Error al guardar la nota' ),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'note_id' => $wpdb->insert_id,
				'message' => 'Nota guardada',
			),
			201
		);
	}

	/**
	 * Obtener notas de reserva
	 */
	public function get_booking_notes( $request ) {
		$operator_id = $this->get_operator_from_token( $request );

		if ( ! $operator_id ) {
			return new WP_REST_Response(
				array( 'message' => 'No autorizado' ),
				401
			);
		}

		$booking_id = $request->get_param( 'id' );

		global $wpdb;
		$notes_table = $wpdb->prefix . 'makia_booking_notes';
		$users_table = $wpdb->prefix . 'users';

		$query = $wpdb->prepare(
			"SELECT n.*, u.display_name as operator_name 
			FROM {$notes_table} n
			LEFT JOIN {$users_table} u ON n.operator_id = u.ID
			WHERE n.booking_id = %d
			ORDER BY n.created_at DESC",
			$booking_id
		);

		$notes = $wpdb->get_results( $query );

		return new WP_REST_Response( $notes, 200 );
	}

	/**
	 * Registrar subscripción push
	 */
	public function register_push_subscription( $request ) {
		$operator_id = $this->get_operator_from_token( $request );

		if ( ! $operator_id ) {
			return new WP_REST_Response(
				array( 'message' => 'No autorizado' ),
				401
			);
		}

		$endpoint = $request->get_param( 'endpoint' );
		$auth     = $request->get_param( 'auth' );
		$p256dh   = $request->get_param( 'p256dh' );

		global $wpdb;
		$table = $wpdb->prefix . 'makia_push_subscriptions';

		// Crear tabla si no existe
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			operator_id bigint(20) NOT NULL,
			endpoint varchar(500) NOT NULL,
			auth varchar(255) NOT NULL,
			p256dh varchar(255) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY operator_endpoint (operator_id, endpoint)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Insertar o actualizar subscripción
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (operator_id, endpoint, auth, p256dh) 
				VALUES (%d, %s, %s, %s)
				ON DUPLICATE KEY UPDATE 
				auth = VALUES(auth), 
				p256dh = VALUES(p256dh)",
				$operator_id,
				$endpoint,
				$auth,
				$p256dh
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Dispositivo registrado para notificaciones',
			),
			201
		);
	}

	/**
	 * Buscar reservas
	 */
	public function search_bookings( $request ) {
		$operator_id = $this->get_operator_from_token( $request );

		if ( ! $operator_id ) {
			return new WP_REST_Response(
				array( 'message' => 'No autorizado' ),
				401
			);
		}

		$query = $request->get_param( 'q' );

		if ( ! $query ) {
			return new WP_REST_Response( array(), 200 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'makia_bookings';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE (customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s)
				ORDER BY booking_date DESC
				LIMIT 20",
				'%' . $wpdb->esc_like( $query ) . '%',
				'%' . $wpdb->esc_like( $query ) . '%',
				'%' . $wpdb->esc_like( $query ) . '%'
			)
		);

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Generar JWT token
	 */
	private function generate_jwt_token( $user ) {
		$secret = defined( 'JWT_AUTH_SECRET_KEY' ) ? JWT_AUTH_SECRET_KEY : wp_salt();
		$issued_at = time();
		$expire = $issued_at + ( 7 * 24 * 60 * 60 ); // 7 días

		$payload = array(
			'iss' => get_bloginfo( 'url' ),
			'iat' => $issued_at,
			'exp' => $expire,
			'user_id' => $user->ID,
			'email' => $user->user_email,
		);

		// Aquí se usaría una librería JWT real como firebase/php-jwt
		// Por ahora, retornar un token simulado
		return base64_encode( wp_json_encode( $payload ) );
	}

	/**
	 * Obtener operario desde token
	 */
	private function get_operator_from_token( $request ) {
		$auth_header = $request->get_header( 'Authorization' );

		if ( ! $auth_header ) {
			return false;
		}

		// Extraer token del header "Bearer <token>"
		$parts = explode( ' ', $auth_header );
		if ( count( $parts ) !== 2 || 'Bearer' !== $parts[0] ) {
			return false;
		}

		$token = $parts[1];

		// Decodificar token
		$payload = json_decode( base64_decode( $token ), true );

		if ( ! isset( $payload['user_id'] ) ) {
			return false;
		}

		return $payload['user_id'];
	}

	/**
	 * Verificar permiso de operario
	 */
	public function check_operator_permission( $request ) {
		$operator_id = $this->get_operator_from_token( $request );
		return (bool) $operator_id;
	}

	/**
	 * Enviar notificación push
	 */
	private function send_push_notification( $operator_id, $title, $body ) {
		global $wpdb;
		$table = $wpdb->prefix . 'makia_push_subscriptions';

		$subscriptions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE operator_id = %d",
				$operator_id
			)
		);

		foreach ( $subscriptions as $subscription ) {
			// Aquí se enviaría la notificación push real
			// usando una librería como web-push
			do_action( 'makia_send_push', $subscription, $title, $body );
		}
	}
}

// Instanciar la clase
new MakIA_API();
