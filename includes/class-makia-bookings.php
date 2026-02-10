<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Gestión de Reservas MakIA
 * Maneja el procesamiento, almacenamiento y gestión de reservas
 */

class MakIA_Bookings {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'makia_bookings';
        
        // Hooks
        add_action('wp_ajax_makia_submit_booking', array($this, 'submit_booking'));
        add_action('wp_ajax_nopriv_makia_submit_booking', array($this, 'submit_booking'));
        add_action('admin_post_makia_update_booking_status', array($this, 'update_booking_status'));
        add_action('admin_post_makia_delete_booking', array($this, 'delete_booking'));
        add_action('wp_ajax_makia_update_booking_status_ajax', array($this, 'update_booking_status_ajax'));
        add_action('wp_ajax_makia_add_booking_note', array($this, 'add_booking_note_ajax'));
        add_action('wp_ajax_makia_get_booking_notes', array($this, 'get_booking_notes_ajax'));
        add_action('wp_ajax_makia_send_reminder', array($this, 'send_reminder_ajax'));
        add_action('wp_ajax_makia_bulk_action', array($this, 'handle_bulk_action'));
        
        // AJAX para gestión pública de reservas
        add_action('wp_ajax_makia_modify_booking', array($this, 'modify_booking_public'));
        add_action('wp_ajax_nopriv_makia_modify_booking', array($this, 'modify_booking_public'));
        add_action('wp_ajax_makia_cancel_booking', array($this, 'cancel_booking_public'));
        add_action('wp_ajax_nopriv_makia_cancel_booking', array($this, 'cancel_booking_public'));
    }
    
    /**
     * Crear tabla de reservas
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'makia_bookings';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla principal de reservas
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            guests int(11) NOT NULL,
            occasion varchar(50) DEFAULT NULL,
            special_requests text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            edit_token varchar(64) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY booking_date_time (booking_date, booking_time),
            KEY status (status),
            KEY email (email),
            KEY edit_token (edit_token)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Tabla de notas internas
        $notes_table = $wpdb->prefix . 'makia_booking_notes';
        $sql_notes = "CREATE TABLE IF NOT EXISTS {$notes_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            note text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql_notes);
    }
    
    /**
     * Procesar envío de reserva (AJAX)
     */
    public function submit_booking() {
        global $wpdb;

        // Verificar nonce (antes de rate limiting para no penalizar solicitudes inválidas)
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'makia_booking_nonce')) {
            wp_send_json_error(array('message' => 'Seguridad: Solicitud no válida'));
            return;
        }

        // ============================================
        // RATE LIMITING - Prevención de SPAM
        // ============================================
        // Obtener IP del cliente (solo REMOTE_ADDR para evitar spoofing)
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';

        // Crear clave única para esta IP
        $transient_key = 'makia_rate_limit_' . md5($ip);
        $attempts = get_transient($transient_key);

        // Límite: 3 intentos cada 10 minutos
        $max_attempts = 3;
        $time_window = 10 * MINUTE_IN_SECONDS;

        if ($attempts === false) {
            // Primera vez, registrar intento
            set_transient($transient_key, 1, $time_window);
        } else {
            if ($attempts >= $max_attempts) {
                $time_remaining = ceil($time_window / 60);

                // Log de seguridad
                MakIA_Logger::security("Rate limit exceeded", array(
                    'ip' => $ip,
                    'attempts' => $attempts
                ));

                wp_send_json_error(array(
                    'message' => "Has superado el límite de intentos de reserva. Por favor, espera {$time_remaining} minutos e inténtalo de nuevo.",
                    'rate_limited' => true,
                    'retry_after' => $time_remaining
                ));
                return;
            }
            // Incrementar contador
            set_transient($transient_key, $attempts + 1, $time_window);
        }

        // ============================================
        // VERIFICACIÓN DE LÍMITE DEL PLAN
        // ============================================
        global $makia_license_manager;
        if (isset($makia_license_manager)) {
            $can_book = $makia_license_manager->can_create_booking();

            if (!$can_book['allowed']) {
                MakIA_Logger::security("Plan limit exceeded", array(
                    'plan' => $can_book['plan'],
                    'current' => $can_book['current'],
                    'limit' => $can_book['limit']
                ));

                wp_send_json_error(array(
                    'message' => $can_book['message'],
                    'plan_limit_exceeded' => true,
                    'upgrade_url' => 'https://contacpro.app'
                ));
                return;
            }
        }
        
        // Validar datos requeridos
        $required_fields = array('name', 'email', 'phone', 'date', 'time', 'guests', 'reason');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => 'Por favor, completa todos los campos obligatorios'));
                return;
            }
        }
        
        // Validar aceptación de términos legales
        if (empty($_POST['legal']) || $_POST['legal'] !== 'on') {
            wp_send_json_error(array('message' => 'Debes aceptar el Aviso Legal y la Política de Privacidad para continuar'));
            return;
        }
        
        // Sanitizar nombre
        $name = sanitize_text_field($_POST['name']);
        $name = trim($name);
        
        // ============================================
        // VALIDACIÓN DE NOMBRE
        // ============================================
        // Longitud mínima y máxima
        if (mb_strlen($name, 'UTF-8') < 2) {
            wp_send_json_error(array(
                'message' => 'El nombre debe tener al menos 2 caracteres',
                'field' => 'name'
            ));
            return;
        }
        
        if (mb_strlen($name, 'UTF-8') > 100) {
            wp_send_json_error(array(
                'message' => 'El nombre es demasiado largo (máximo 100 caracteres)',
                'field' => 'name'
            ));
            return;
        }
        
        // Solo letras, espacios, guiones y apóstrofes (nombres compuestos y extranjeros)
        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜàèìòùÀÈÌÒÙâêîôûÂÊÎÔÛçÇ\'\-\s]+$/', $name)) {
            wp_send_json_error(array(
                'message' => 'El nombre solo puede contener letras, espacios, guiones y apóstrofes',
                'field' => 'name'
            ));
            return;
        }
        
        // Verificar que no sea spam obvio (nombres repetidos)
        if (preg_match('/(.)\1{4,}/', $name)) { // 5+ caracteres iguales seguidos
            wp_send_json_error(array(
                'message' => 'Nombre no válido',
                'field' => 'name'
            ));
            return;
        }
        // Sanitizar email
        $email = sanitize_email($_POST['email']);
        
        // ============================================
        // VALIDACIÓN DE EMAIL
        // ============================================
        // Validar formato de email
        if (!is_email($email)) {
            wp_send_json_error(array(
                'message' => 'El email no tiene un formato válido',
                'field' => 'email'
            ));
            return;
        }
        
        // Verificar que el dominio tiene registros MX (email real)
        list($user, $domain) = explode('@', $email);
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            wp_send_json_error(array(
                'message' => 'El dominio del email no parece válido. Por favor, verifica tu dirección de correo',
                'field' => 'email'
            ));
            return;
        }
        
        // Bloquear emails desechables conocidos
        $disposable_domains = array('mailinator.com', 'guerrillamail.com', 'temp-mail.org', '10minutemail.com', 'throwaway.email', 'trashmail.com', 'tempmail.com');
        if (in_array(strtolower($domain), $disposable_domains)) {
            wp_send_json_error(array(
                'message' => 'No se permiten direcciones de email temporales o desechables',
                'field' => 'email'
            ));
            return;
        }
        // Sanitizar teléfono
        $phone = sanitize_text_field($_POST['phone']);
        
        // ============================================
        // VALIDACIÓN DE FORMATO DE TELÉFONO
        // ============================================
        // Remover espacios para validación
        $phone_check = str_replace([' ', '-', '(', ')'], '', $phone);
        
        // Validar: debe empezar con + o número, tener entre 9-15 dígitos
        if (!preg_match('/^[\+]?[0-9]{9,15}$/', $phone_check)) {
            wp_send_json_error(array(
                'message' => 'Formato de teléfono no válido. Debe contener entre 9 y 15 dígitos. Ejemplos: +34666777888 o 666 777 888',
                'field' => 'phone'
            ));
            return;
        }
        
        // Normalizar formato para almacenamiento
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $guests = intval($_POST['guests']);

        // Validación estricta de formato de fecha
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            wp_send_json_error(array('message' => 'Formato de fecha inválido'));
            return;
        }
        $time_obj = DateTime::createFromFormat('H:i', $time);
        if (!$time_obj || $time_obj->format('H:i') !== $time) {
            wp_send_json_error(array('message' => 'Formato de hora inválido'));
            return;
        }

        // Validación mínima de comensales
        if ($guests < 1) {
            wp_send_json_error(array('message' => 'El número de comensales debe ser al menos 1'));
            return;
        }

        $reason = sanitize_text_field($_POST['reason']);
        $highchair = sanitize_text_field($_POST['highchair'] ?? 'no');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        // Añadir información de trona/carrito a las notas si aplica
        if ($highchair !== 'no') {
            $highchair_labels = array(
                'trona' => '👶 Necesita TRONA',
                'carrito' => '🛍️ Espacio para CARRITO',
                'ambos' => '👶 Necesita TRONA + 🛍️ Espacio para CARRITO'
            );
            $highchair_note = $highchair_labels[$highchair] ?? '';
            if ($highchair_note) {
                $notes = $highchair_note . ($notes ? "\n" . $notes : '');
            }
        }
        
        // ============================================
        // VALIDACIÓN DE FECHA Y HORA
        // ============================================
        $booking_date = strtotime($date);
        $today = strtotime(date('Y-m-d'));
        
        // No se puede reservar en el pasado
        if ($booking_date < $today) {
            wp_send_json_error(array(
                'message' => 'No se pueden hacer reservas para fechas pasadas',
                'field' => 'date'
            ));
            return;
        }
        
        // Reserva con al menos X horas de antelación (configurable)
        $min_hours_advance = intval(get_option('makia_min_hours_advance', 2));
        $min_booking_time = strtotime("+{$min_hours_advance} hours");
        $booking_datetime = strtotime($date . ' ' . $time);
        
        if ($booking_datetime < $min_booking_time) {
            wp_send_json_error(array(
                'message' => "Las reservas deben hacerse con al menos {$min_hours_advance} horas de antelación",
                'field' => 'date'
            ));
            return;
        }
        
        // Límite máximo de antelación (configurable, por defecto 3 meses)
        $max_months_advance = intval(get_option('makia_max_months_advance', 3));
        $max_advance_date = strtotime("+{$max_months_advance} months");
        
        if ($booking_date > $max_advance_date) {
            wp_send_json_error(array(
                'message' => "No se pueden hacer reservas con más de {$max_months_advance} meses de antelación",
                'field' => 'date'
            ));
            return;
        }
        
        // ============================================
        // VALIDACIÓN DE HORARIOS DE NEGOCIO
        // ============================================
        // Obtener día de la semana
        $day_of_week = strtolower(date('l', strtotime($date)));
        $day_map = array(
            'monday' => 'monday',
            'tuesday' => 'tuesday',
            'wednesday' => 'wednesday',
            'thursday' => 'thursday',
            'friday' => 'friday',
            'saturday' => 'saturday',
            'sunday' => 'sunday'
        );
        
        $day_key = isset($day_map[$day_of_week]) ? $day_map[$day_of_week] : $day_of_week;
        $business_hours = get_option('makia_business_hours', array());
        
        // Verificar si hay horarios configurados
        if (empty($business_hours)) {
            wp_send_json_error(array(
                'message' => 'El restaurante no tiene horarios configurados. Contacta al administrador.',
                'field' => 'time'
            ));
            return;
        }
        
        // Verificar si el restaurante abre ese día
        if (!isset($business_hours[$day_key]) || empty($business_hours[$day_key]['enabled'])) {
            wp_send_json_error(array(
                'message' => 'El restaurante está cerrado ese día',
                'field' => 'date'
            ));
            return;
        }
        
        // Verificar que la hora esté dentro de alguna franja horaria
        $time_valid = false;
        $available_slots = array();
        
        if (!empty($business_hours[$day_key]['slots'])) {
            foreach ($business_hours[$day_key]['slots'] as $slot) {
                $available_slots[] = $slot['open'] . ' - ' . $slot['close'];
                
                // Verificar si la hora está dentro de esta franja
                if ($time >= $slot['open'] && $time <= $slot['close']) {
                    $time_valid = true;
                    break;
                }
            }
        }
        
        if (!$time_valid) {
            $slots_text = implode(', ', $available_slots);
            wp_send_json_error(array(
                'message' => "La hora seleccionada no está disponible. Horarios disponibles: {$slots_text}",
                'field' => 'time',
                'available_slots' => $available_slots
            ));
            return;
        }
        
        // ============================================
        // VERIFICACIÓN DE LISTA NEGRA
        // ============================================
        $blacklist_check = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}makia_blacklist 
             WHERE (email = %s OR phone = %s) 
             AND is_active = 1 
             LIMIT 1",
            $email,
            $phone
        ));
        
        if ($blacklist_check) {
            // Usuario en lista negra - logging de seguridad
            MakIA_Logger::security("Blacklisted user attempt", array(
                'email' => $email,
                'phone' => $phone,
                'reason' => $blacklist_check->reason
            ));
            
            // No revelar que están en lista negra (seguridad)
            wp_send_json_error(array(
                'message' => 'No podemos procesar tu reserva en este momento. Por favor, contacta directamente con el restaurante.'
            ));
            return;
        }
        
        // Validar capacidad por reserva individual
        $max_guests = get_option('makia_max_guests_per_reservation', 20);
        if ($guests > $max_guests) {
            wp_send_json_error(array('message' => "Máximo $max_guests comensales por reserva"));
            return;
        }
        
        // ============================================
        // VALIDACIÓN DE CAPACIDAD TOTAL - MEJORADA
        // ============================================
        // Usar transacción para evitar race condition en la verificación de capacidad
        $wpdb->query('START TRANSACTION');

        // Calcular total de comensales ya reservados para esa fecha/hora (con bloqueo FOR UPDATE)
        $existing_guests = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(guests), 0) FROM {$this->table_name}
             WHERE booking_date = %s
             AND booking_time = %s
             AND status IN ('pending', 'approved')
             FOR UPDATE",
            $date,
            $time
        ));

        // Obtener capacidad máxima configurada
        $max_capacity = intval(get_option('makia_max_capacity', 50));

        // Verificar si hay espacio suficiente
        if (($existing_guests + $guests) > $max_capacity) {
            $wpdb->query('ROLLBACK');
            $available_slots = max(0, $max_capacity - $existing_guests);

            $message = $available_slots > 0
                ? "Lo sentimos, solo quedan {$available_slots} plazas disponibles para ese horario. Estás intentando reservar para {$guests} personas."
                : "Lo sentimos, no hay plazas disponibles para ese horario. Aforo completo.";

            wp_send_json_error(array(
                'message' => $message,
                'available' => $available_slots,
                'requested' => $guests
            ));
            return;
        }

        // Generar token único para gestión de reserva
        $edit_token = bin2hex(random_bytes(32));

        // Insertar reserva
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'booking_date' => $date,
                'booking_time' => $time,
                'guests' => $guests,
                'occasion' => $reason,
                'special_requests' => $notes,
                'status' => 'pending',
                'edit_token' => $edit_token
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            // Log del error para debugging
            $db_error = $wpdb->last_error;
            error_log('[MakIA Reservas] Error al guardar reserva: ' . $db_error);

            // Si el error es por columna desconocida, intentar actualizar la tabla
            if (strpos($db_error, 'Unknown column') !== false || strpos($db_error, 'edit_token') !== false) {
                $wpdb->query('ROLLBACK');
                // Intentar añadir la columna edit_token si no existe
                $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN edit_token varchar(64) DEFAULT NULL");

                // Reintentar la inserción (nueva transacción)
                $wpdb->query('START TRANSACTION');
                $result = $wpdb->insert(
                    $this->table_name,
                    array(
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'booking_date' => $date,
                        'booking_time' => $time,
                        'guests' => $guests,
                        'occasion' => $reason,
                        'special_requests' => $notes,
                        'status' => 'pending',
                        'edit_token' => $edit_token
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
                );

                if ($result !== false) {
                    $wpdb->query('COMMIT');
                    // Éxito después de actualizar la tabla
                    $booking_id = $wpdb->insert_id;
                    $this->send_customer_email($booking_id);
                    $this->send_restaurant_email($booking_id);
                    wp_send_json_success(array(
                        'message' => '¡Reserva recibida! Te enviaremos un email de confirmación cuando sea aprobada por el restaurante.',
                        'booking_id' => $booking_id
                    ));
                    return;
                }
                $wpdb->query('ROLLBACK');
            } else {
                $wpdb->query('ROLLBACK');
            }

            wp_send_json_error(array('message' => 'Error al guardar la reserva. Por favor, inténtalo de nuevo.'));
            return;
        }

        $wpdb->query('COMMIT');
        
        $booking_id = $wpdb->insert_id;
        
        // Log de reserva exitosa
        MakIA_Logger::booking($booking_id, 'created', array(
            'name' => $name,
            'email' => $email,
            'date' => $date,
            'time' => $time,
            'guests' => $guests,
            'status' => 'pending'
        ));
        
        // Enviar email al cliente
        $this->send_customer_email($booking_id);
        
        // Enviar email al restaurante
        $this->send_restaurant_email($booking_id);
        
        // Incrementar contador de reservas mensuales del plan
        global $makia_license_manager;
        if (isset($makia_license_manager)) {
            $new_count = $makia_license_manager->increment_booking_count();
            $stats = $makia_license_manager->get_usage_stats();
            
            MakIA_Logger::log("Booking count incremented", "INFO", array(
                'count' => $new_count,
                'plan' => $stats['plan_name'],
                'remaining' => $stats['remaining']
            ));
        }
        
        // Respuesta exitosa
        wp_send_json_success(array(
            'message' => '¡Reserva recibida! Te enviaremos un email de confirmación cuando sea aprobada por el restaurante.',
            'booking_id' => $booking_id
        ));
    }
    
    /**
     * Enviar email al cliente
     */
    private function send_customer_email($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) return;
        
        $restaurant_name = get_option('makia_restaurant_name', get_bloginfo('name'));
        $restaurant_email = get_option('makia_restaurant_email', get_option('admin_email'));
        
        $subject = "Reserva pendiente de confirmación - $restaurant_name";
        
        $message = "Hola {$booking->name},\n\n";
        $message .= "Hemos recibido tu solicitud de reserva en $restaurant_name.\n\n";
        $message .= "DETALLES DE LA RESERVA:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Fecha: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
        $message .= "Hora: " . date('H:i', strtotime($booking->booking_time)) . "\n";
        $message .= "Comensales: {$booking->guests}\n";
        if ($booking->occasion) {
            $message .= "Motivo: {$booking->occasion}\n";
        }
        if ($booking->special_requests) {
            $message .= "Notas: {$booking->special_requests}\n";
        }
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "Tu reserva está PENDIENTE DE CONFIRMACIÓN.\n";
        $message .= "Te enviaremos un email cuando el restaurante la apruebe.\n\n";
        
        // Añadir enlaces de gestión
        $manage_url = home_url('/gestionar-reserva/?token=' . $booking->edit_token);
        $modify_url = $manage_url . '&action=modify';
        $cancel_url = $manage_url . '&action=cancel';
        
        $message .= "GESTIONA TU RESERVA:
";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
";
        $message .= "<a href=\"$modify_url\">📝 Modificar mi reserva</a>
";
        $message .= "<a href=\"$cancel_url\">❌ Cancelar mi reserva</a>
";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

";
        
        $message .= "Si tienes alguna pregunta, puedes contactarnos en:\n";
        $message .= "Email: $restaurant_email\n";
        $message .= "Teléfono: " . get_option('makia_restaurant_phone', '') . "\n\n";
        $message .= "Gracias por elegir $restaurant_name.\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Powered by MakIA Reservas - https://contacpro.app\n";
        
        $headers = array(
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>',
            'Reply-To: ' . $restaurant_email,
            'Content-Type: text/html; charset=UTF-8'
        );
        
        $result = wp_mail($booking->email, $subject, $message, $headers);
        error_log('[MakIA] Email cliente a ' . $booking->email . ': ' . ($result ? 'OK' : 'FALLO'));
        return $result;
    }
    
    /**
     * Enviar email al restaurante
     */
    private function send_restaurant_email($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) return;
        
        $restaurant_name = get_option('makia_restaurant_name', get_bloginfo('name'));
        $restaurant_email = get_option('makia_restaurant_email', get_option('admin_email'));
        
        $subject = "Nueva reserva pendiente de aprobación";
        
        $message = "Nueva solicitud de reserva en $restaurant_name:\n\n";
        $message .= "DATOS DEL CLIENTE:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Nombre: {$booking->name}\n";
        $message .= "Email: {$booking->email}\n";
        $message .= "Teléfono: {$booking->phone}\n\n";
        $message .= "DETALLES DE LA RESERVA:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Fecha: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
        $message .= "Hora: " . date('H:i', strtotime($booking->booking_time)) . "\n";
        $message .= "Comensales: {$booking->guests}\n";
        if ($booking->occasion) {
            $message .= "Motivo: {$booking->occasion}\n";
        }
        if ($booking->special_requests) {
            $message .= "Notas: {$booking->special_requests}\n";
        }
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "Para gestionar esta reserva, accede al panel de administración:\n";
        $message .= admin_url('admin.php?page=makia&tab=bookings') . "\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "MakIA Reservas - https://contacpro.app\n";
        
        $headers = array(
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>',
            'Reply-To: ' . $restaurant_email
        );
        
        $result = wp_mail($restaurant_email, $subject, $message, $headers);
        error_log('[MakIA] Email restaurante a ' . $restaurant_email . ': ' . ($result ? 'OK' : 'FALLO'));
        return $result;
    }
    
    /**
     * Actualizar estado de reserva
     */
    public function update_booking_status() {
        global $wpdb;
        
        // Verificar nonce
        if (!isset($_POST['makia_booking_status_nonce']) || !wp_verify_nonce($_POST['makia_booking_status_nonce'], 'makia_booking_status_action')) {
            wp_die('Acción no autorizada');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        // Validar estado
        $valid_statuses = array('pending', 'approved', 'rejected', 'cancelled');
        if (!in_array($new_status, $valid_statuses)) {
            wp_die('Estado no válido');
        }
        
        // Obtener reserva antes de actualizar
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $booking_id
        ));
        
        // Actualizar estado
        $result = $wpdb->update(
            $this->table_name,
            array('status' => $new_status),
            array('id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Registrar en auditoría
            MakIA_Audit::log_action(
                'booking_status_updated',
                'booking',
                $booking_id,
                array(
                    'old_status' => $booking->status,
                    'new_status' => $new_status,
                    'method' => 'admin_panel'
                )
            );

            // Enviar email de notificación al cliente
            $this->send_status_update_email($booking_id, $new_status);
            
            add_settings_error('makia_bookings', 'status_updated', 'Estado actualizado correctamente', 'success');
        } else {
            add_settings_error('makia_bookings', 'status_error', 'Error al actualizar el estado', 'error');
        }
        
        set_transient('settings_errors', get_settings_errors(), 30);
        
        // Redirigir
        wp_redirect(admin_url('admin.php?page=makia&tab=bookings'));
        exit;
    }
    
    /**
     * Enviar email de actualización de estado
     */
    private function send_status_update_email($booking_id, $new_status) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) return;
        
        $restaurant_name = get_option('makia_restaurant_name', get_bloginfo('name'));
        $restaurant_email = get_option('makia_restaurant_email', get_option('admin_email'));
        
        $status_messages = array(
            'approved' => '¡Tu reserva ha sido CONFIRMADA! ✅',
            'rejected' => 'Lo sentimos, tu reserva ha sido rechazada ❌',
            'cancelled' => 'Tu reserva ha sido cancelada'
        );
        
        if (!isset($status_messages[$new_status])) return;
        
        $subject = $status_messages[$new_status] . " - $restaurant_name";
        
        $message = "Hola {$booking->name},\n\n";
        $message .= $status_messages[$new_status] . "\n\n";
        $message .= "DETALLES DE LA RESERVA:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Fecha: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
        $message .= "Hora: " . date('H:i', strtotime($booking->booking_time)) . "\n";
        $message .= "Comensales: {$booking->guests}\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        if ($new_status === 'approved') {
            $message .= "¡Te esperamos! Si necesitas hacer algún cambio, puedes gestionar tu reserva online:\n\n";
            
            // Añadir enlaces de gestión
            $manage_url = home_url('/gestionar-reserva/?token=' . $booking->edit_token);
            $modify_url = $manage_url . '&action=modify';
            $cancel_url = $manage_url . '&action=cancel';
            
            $message .= "GESTIONA TU RESERVA:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "<a href=\"$modify_url\">📝 Modificar mi reserva</a>\n";
            $message .= "<a href=\"$cancel_url\">❌ Cancelar mi reserva</a>\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            
        } elseif ($new_status === 'rejected') {
            $message .= "Puedes intentar reservar para otra fecha u hora.\n\n";
        }
        
        $message .= "Contacto:\n";
        $message .= "Email: $restaurant_email\n";
        $message .= "Teléfono: " . get_option('makia_restaurant_phone', '') . "\n\n";
        $message .= "Gracias,\n$restaurant_name\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Powered by MakIA Reservas - https://contacpro.app\n";
        
        $headers = array(
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>',
            'Reply-To: ' . $restaurant_email,
            'Content-Type: text/html; charset=UTF-8'
        );
        
        wp_mail($booking->email, $subject, $message, $headers);
    }
    
    /**
     * Eliminar reserva
     */
    public function delete_booking() {
        global $wpdb;
        
        // Verificar nonce
        if (!isset($_POST['makia_delete_booking_nonce']) || !wp_verify_nonce($_POST['makia_delete_booking_nonce'], 'makia_delete_booking_action')) {
            wp_die('Acción no autorizada');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $booking_id),
            array('%d')
        );
        
        if ($result !== false) {
            add_settings_error('makia_bookings', 'booking_deleted', 'Reserva eliminada', 'success');
        } else {
            add_settings_error('makia_bookings', 'delete_error', 'Error al eliminar la reserva', 'error');
        }
        
        set_transient('settings_errors', get_settings_errors(), 30);
        
        // Redirigir
        wp_redirect(admin_url('admin.php?page=makia&tab=bookings'));
        exit;
    }
    
    /**
     * Obtener todas las reservas
     */
    public function get_all_bookings($status = null, $limit = 100, $offset = 0) {
        global $wpdb;
        
        $where = '';
        if ($status) {
            $where = $wpdb->prepare("WHERE status = %s", $status);
        }
        
        $query = "SELECT * FROM {$this->table_name} $where ORDER BY booking_date DESC, booking_time DESC LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $limit, $offset));
    }
    
    /**
     * Obtener estadísticas
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total de reservas
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // Reservas pendientes
        $stats['pending'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
            'pending'
        ));
        
        // Reservas aprobadas
        $stats['approved'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
            'approved'
        ));
        
        // Reservas hoy
        $stats['today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE booking_date = %s AND status = 'approved'",
            date('Y-m-d')
        ));
        
        // Próximas reservas (próximos 7 días)
        $stats['upcoming'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE booking_date BETWEEN %s AND %s AND status = 'approved'",
            date('Y-m-d'),
            date('Y-m-d', strtotime('+7 days'))
        ));
        
        return $stats;
    }
    
    /**
     * Renderizar página de gestión de reservas
     */
    public static function render_page() {
        global $wpdb;
        $bookings_instance = new self();
        
        // Cargar CSS para móvil
        wp_enqueue_style('makia-admin-mobile', MAKIA_PLUGIN_URL . 'assets/css/makia-admin-mobile.css', array(), MAKIA_VERSION);
        wp_enqueue_style('makia-admin-notifications', MAKIA_PLUGIN_URL . 'assets/css/makia-admin-notifications.css', array(), MAKIA_VERSION);
        
        // Cargar JavaScript para AJAX
        wp_enqueue_script('makia-admin-js', MAKIA_PLUGIN_URL . 'assets/js/makia-admin.js', array('jquery'), MAKIA_VERSION, true);
        wp_enqueue_script('makia-booking-notes-js', MAKIA_PLUGIN_URL . 'assets/js/makia-booking-notes.js', array('jquery'), MAKIA_VERSION, true);
        wp_enqueue_script('makia-bulk-actions-js', MAKIA_PLUGIN_URL . 'assets/js/makia-bulk-actions.js', array('jquery'), MAKIA_VERSION, true);
        
        // Cargar CSS para notas
        wp_enqueue_style('makia-booking-notes-css', MAKIA_PLUGIN_URL . 'assets/css/makia-booking-notes.css', array(), MAKIA_VERSION);
        
        // Obtener filtro de estado
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        
        // Obtener reservas
        $bookings = $bookings_instance->get_all_bookings($filter_status);
        
        // Obtener estadísticas
        $stats = $bookings_instance->get_stats();
        
        ?>
        <div class="wrap">
            <?php settings_errors('makia_bookings'); ?>
            
            <!-- Estadísticas -->
            <div class="makia-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="makia-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;">Total Reservas</h3>
                    <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo $stats['total']; ?></p>
                </div>
                <div class="makia-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;">⏳ Pendientes</h3>
                    <p style="margin: 0; font-size: 32px; font-weight: bold; color: #f0b849;"><?php echo $stats['pending']; ?></p>
                </div>
                <div class="makia-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;">✅ Aprobadas</h3>
                    <p style="margin: 0; font-size: 32px; font-weight: bold; color: #46b450;"><?php echo $stats['approved']; ?></p>
                </div>
                <div class="makia-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;">📅 Próximas (7 días)</h3>
                    <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo $stats['upcoming']; ?></p>
                </div>
            </div>
            
            <!-- Filtros Avanzados -->
            <div class="makia-advanced-filters" style="background: #f9f9f9; border-radius: 8px; margin-bottom: 20px;">
                <!-- Botón Toggle -->
                <button type="button" id="toggle-filters" class="button" style="width: 100%; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: all 0.3s ease;">
                    <span>🔍 Filtros de Búsqueda</span>
                    <span id="filter-toggle-icon" style="font-size: 20px; transition: transform 0.3s ease;">▶</span>
                </button>
                
                <!-- Contenido de Filtros (Colapsado por defecto) -->
                <div id="filters-content" style="display: none; padding: 20px; padding-top: 15px;">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                    <!-- Estado -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Estado</label>
                        <select name="filter_status" id="filter-status" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            <option value="">Todos los estados</option>
                            <option value="pending" <?php selected($filter_status, 'pending'); ?>>⏳ Pendientes</option>
                            <option value="approved" <?php selected($filter_status, 'approved'); ?>>✅ Aprobadas</option>
                            <option value="rejected" <?php selected($filter_status, 'rejected'); ?>>❌ Rechazadas</option>
                            <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>🚫 Canceladas</option>
                        </select>
                    </div>
                    
                    <!-- Período -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Período</label>
                        <select id="filter-period" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            <option value="">Personalizado</option>
                            <option value="today">📅 Hoy</option>
                            <option value="tomorrow">📆 Mañana</option>
                            <option value="this-week">📅 Esta semana</option>
                            <option value="this-month">📅 Este mes</option>
                        </select>
                    </div>
                    
                    <!-- Fecha desde -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Desde</label>
                        <input type="date" id="filter-date-from" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    </div>
                    
                    <!-- Fecha hasta -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Hasta</label>
                        <input type="date" id="filter-date-to" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    </div>
                    
                    <!-- Rango de horas -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Horario</label>
                        <select id="filter-time-range" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            <option value="">Todas las horas</option>
                            <option value="lunch">🍽️ Almuerzo (12:00-15:00)</option>
                            <option value="dinner">🍷 Cena (19:00-23:00)</option>
                        </select>
                    </div>
                    
                    <!-- Número de comensales -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Comensales</label>
                        <select id="filter-guests" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            <option value="">Todos</option>
                            <option value="1-2">👥 1-2 personas</option>
                            <option value="3-4">👥 3-4 personas</option>
                            <option value="5-8">👥 5-8 personas</option>
                            <option value="9+">👥 9+ personas</option>
                        </select>
                    </div>
                </div>
                
                <!-- Búsqueda por texto -->
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">🔍 Buscar</label>
                    <input type="text" id="filter-search" placeholder="Buscar por nombre, email o teléfono..." style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd; font-size: 14px;">
                </div>
                
                <!-- Botones -->
                <div style="display: flex; gap: 10px;">
                    <button type="button" id="apply-filters" class="button button-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 10px 20px;">
                        🔍 Aplicar Filtros
                    </button>
                    <button type="button" id="clear-filters" class="button" style="padding: 10px 20px;">
                        🗑️ Limpiar Filtros
                    </button>
                </div>
                
                </div><!-- #filters-content -->
            </div><!-- .makia-advanced-filters -->
            
            <!-- Barra de acciones grupales -->
            <div class="makia-bulk-actions-bar" style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: none;">
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <strong style="color: #667eea;">
                            <span id="makia-selected-count">0</span> reservas seleccionadas
                        </strong>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="button makia-bulk-action" data-action="approve" style="background: #46b450; color: #fff; border: none;">
                            ✅ Aprobar
                        </button>
                        <button type="button" class="button makia-bulk-action" data-action="reject" style="background: #dc3232; color: #fff; border: none;">
                            ❌ Rechazar
                        </button>
                        <button type="button" class="button makia-bulk-action" data-action="cancel" style="background: #999; color: #fff; border: none;">
                            🚫 Cancelar
                        </button>
                        <button type="button" class="button makia-bulk-action" data-action="reminder" style="background: #667eea; color: #fff; border: none;">
                            📧 Enviar Recordatorio
                        </button>
                        <button type="button" class="button makia-bulk-action" data-action="noshow" style="background: #dc3232; color: #fff; border: none;">
                            🚫 Marcar No-Show
                        </button>
                        <button type="button" class="button" id="makia-clear-selection">
                            Limpiar Selección
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de reservas -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="makia-select-all" title="Seleccionar todas">
                        </th>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Comensales</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px;">
                                No hay reservas<?php echo $filter_status ? ' con ese estado' : ''; ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr data-booking-id="<?php echo esc_attr($booking->id); ?>">
                                <td>
                                    <input type="checkbox" class="makia-booking-checkbox" value="<?php echo esc_attr($booking->id); ?>">
                                </td>
                                <td><?php echo esc_attr($booking->id); ?></td>
                                <td><strong><?php echo esc_html($booking->name); ?></strong></td>
                                <td>
                                    <?php echo esc_html($booking->email); ?><br>
                                    <small><?php echo esc_html($booking->phone); ?></small>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($booking->booking_date)); ?></td>
                                <td><?php echo date('H:i', strtotime($booking->booking_time)); ?></td>
                                <td><?php echo esc_attr($booking->guests); ?></td>
                                <td><?php echo esc_html($booking->occasion ?: '-'); ?></td>
                                <td>
                                    <?php
                                    $status_labels = array(
                                        'pending' => '<span style="color: #f0b849;">⏳ Pendiente</span>',
                                        'approved' => '<span style="color: #46b450;">✅ Aprobada</span>',
                                        'rejected' => '<span style="color: #dc3232;">❌ Rechazada</span>',
                                        'cancelled' => '<span style="color: #999;">🚫 Cancelada</span>'
                                    );
                                    echo $status_labels[$booking->status] ?? $booking->status;
                                    ?>
                                </td>
                                <td>
                                    <?php if ($booking->status === 'pending'): ?>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" data-ajax-action="true" style="display: inline;">
                                            <input type="hidden" name="action" value="makia_update_booking_status">
                                            <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <?php wp_nonce_field('makia_booking_status_action', 'makia_booking_status_nonce'); ?>
                                            <button type="submit" class="button button-primary button-small">✅ Aprobar</button>
                                        </form>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" data-ajax-action="true" style="display: inline;">
                                            <input type="hidden" name="action" value="makia_update_booking_status">
                                            <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <?php wp_nonce_field('makia_booking_status_action', 'makia_booking_status_nonce'); ?>
                                            <button type="submit" class="button button-small">❌ Rechazar</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;" onsubmit="return confirm('¿Eliminar esta reserva?');">
                                        <input type="hidden" name="action" value="makia_delete_booking">
                                        <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">
                                        <?php wp_nonce_field('makia_delete_booking_action', 'makia_delete_booking_nonce'); ?>
                                        <button type="submit" class="button button-small">🗑️ Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php if ($booking->special_requests): ?>
                                <tr>
                                    <td colspan="9" style="background: #f9f9f9; padding: 10px;">
                                        <strong>Notas:</strong> <?php echo esc_html($booking->special_requests); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Versión móvil (tarjetas) -->
            <div class="makia-mobile-bookings" style="display: none;">
                <?php if (empty($bookings)): ?>
                    <div style="text-align: center; padding: 40px; background: #fff; border-radius: 8px;">
                        No hay reservas<?php echo $filter_status ? ' con ese estado' : ''; ?>.
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <div class="makia-booking-card" data-booking-id="<?php echo esc_attr($booking->id); ?>" onclick="makiaShowBookingDetail(<?php echo esc_attr($booking->id); ?>)">
                            <div class="makia-booking-header">
                                <div class="makia-booking-name"><?php echo esc_html($booking->name); ?></div>
                                <div class="makia-booking-status">
                                    <?php
                                    $status_labels = array(
                                        'pending' => '⏳',
                                        'approved' => '✅',
                                        'rejected' => '❌',
                                        'cancelled' => '🚫'
                                    );
                                    echo $status_labels[$booking->status] ?? '';
                                    ?>
                                </div>
                            </div>
                            
                            <div class="makia-booking-info">
                                <div class="makia-info-item">
                                    <div class="makia-info-label">📅 Fecha</div>
                                    <div class="makia-info-value"><?php echo date('d/m/Y', strtotime($booking->booking_date)); ?></div>
                                </div>
                                <div class="makia-info-item">
                                    <div class="makia-info-label">🕒 Hora</div>
                                    <div class="makia-info-value"><?php echo date('H:i', strtotime($booking->booking_time)); ?></div>
                                </div>
                                <div class="makia-info-item">
                                    <div class="makia-info-label">👥 Personas</div>
                                    <div class="makia-info-value"><?php echo esc_attr($booking->guests); ?></div>
                                </div>
                                <div class="makia-info-item">
                                    <div class="makia-info-label">📧 Email</div>
                                    <div class="makia-info-value" style="font-size: 11px;"><?php echo esc_html($booking->email); ?></div>
                                </div>
                                <div class="makia-info-item">
                                    <div class="makia-info-label">📞 Teléfono</div>
                                    <div class="makia-info-value"><?php echo esc_html($booking->phone); ?></div>
                                    <div class="makia-phone-actions">
                                        <a href="tel:<?php echo esc_attr($booking->phone); ?>" class="makia-phone-btn makia-phone-btn-call">
                                            📞 Llamar
                                        </a>
                                        <a href="sms:<?php echo esc_attr($booking->phone); ?>?body=<?php echo urlencode('Hola ' . $booking->name . ', tu reserva para el ' . date('d/m/Y', strtotime($booking->booking_date)) . ' a las ' . date('H:i', strtotime($booking->booking_time)) . ' ha sido confirmada. ¡Te esperamos! - ' . get_option('makia_restaurant_name', get_bloginfo('name')) . ''); ?>" class="makia-phone-btn makia-phone-btn-sms">
                                            💬 SMS
                                        </a>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $booking->phone); ?>?text=<?php echo urlencode('Hola ' . $booking->name . ', tu reserva para el ' . date('d/m/Y', strtotime($booking->booking_date)) . ' a las ' . date('H:i', strtotime($booking->booking_time)) . ' ha sido confirmada. ¡Te esperamos! - ' . get_option('makia_restaurant_name', get_bloginfo('name')) . ''); ?>" target="_blank" class="makia-phone-btn makia-phone-btn-whatsapp">
                                            📱 WhatsApp
                                        </a>
                                    </div>
                                </div>
                                <?php if ($booking->occasion): ?>
                                <div class="makia-info-item">
                                    <div class="makia-info-label">🎉 Motivo</div>
                                    <div class="makia-info-value"><?php echo esc_html($booking->occasion); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($booking->special_requests): ?>
                                <div class="makia-booking-notes">
                                    <strong>Notas:</strong>
                                    <?php echo esc_html($booking->special_requests); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="makia-booking-actions">
                                <?php if ($booking->status === 'pending'): ?>
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" data-ajax-action="true" style="display: inline;">
                        <input type="hidden" name="action" value="makia_update_booking_status">
                        <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">
                        <input type="hidden" name="status" value="approved">
                        <?php wp_nonce_field('makia_booking_status_action', 'makia_booking_status_nonce'); ?>
                                        <button type="submit" class="makia-btn-approve">✅ Aprobar</button>
                                    </form>
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" data-ajax-action="true" style="display: inline;">
                        <input type="hidden" name="action" value="makia_update_booking_status">
                        <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">
                        <input type="hidden" name="status" value="rejected">
                        <?php wp_nonce_field('makia_booking_status_action', 'makia_booking_status_nonce'); ?>
                                        <button type="submit" class="makia-btn-reject">❌ Rechazar</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                    <input type="hidden" name="action" value="makia_delete_booking">
                    <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">
                    <?php wp_nonce_field('makia_delete_booking_action', 'makia_delete_booking_nonce'); ?>
                                    <button type="submit" class="makia-btn-delete" onclick="return confirm('¿Eliminar esta reserva?');">🗑️ Eliminar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function makiaEscapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        function makiaShowBookingDetail(bookingId) {
            // Prevenir propagación del click en botones
            if (window.event && (window.event.target.tagName === 'BUTTON' || window.event.target.closest('button'))) {
                return;
            }
            
            // Obtener datos de la reserva
            const card = document.querySelector('[data-booking-id="' + bookingId + '"]');
            if (!card) return;
            
            const name = card.querySelector('.makia-booking-name').textContent;
            const status = card.querySelector('.makia-booking-status').textContent;
            const infoItems = card.querySelectorAll('.makia-info-value');
            const notes = card.querySelector('.makia-booking-notes');
            
            // Crear modal
            const modal = document.createElement('div');
            modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px;';
            
            const modalContent = document.createElement('div');
            modalContent.style.cssText = 'background: #fff; border-radius: 12px; padding: 20px; max-width: 500px; width: 100%; max-height: 80vh; overflow-y: auto;';
            
            modalContent.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 15px;">
                    <h2 style="margin: 0; font-size: 20px; color: #2271b1;">${makiaEscapeHtml(name)}</h2>
                    <button onclick="this.closest('[style*=\"position: fixed\"]').remove()" style="background: none; border: none; font-size: 28px; cursor: pointer; color: #666;">&times;</button>
                </div>
                <div style="display: grid; gap: 15px;">
                    <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                        <div style="color: #666; font-size: 12px; margin-bottom: 5px;">ESTADO</div>
                        <div style="font-size: 18px;">${makiaEscapeHtml(status)}</div>
                    </div>
                    ${Array.from(card.querySelectorAll('.makia-info-item')).map(item => {
                        const label = item.querySelector('.makia-info-label').textContent;
                        const value = item.querySelector('.makia-info-value').textContent;
                        return `
                            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                                <div style="color: #666; font-size: 12px; margin-bottom: 5px;">${makiaEscapeHtml(label)}</div>
                                <div style="font-size: 16px; font-weight: 500;">${makiaEscapeHtml(value)}</div>
                            </div>
                        `;
                    }).join('')}
                    ${notes ? `
                        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border: 1px solid #ffc107;">
                            ${makiaEscapeHtml(notes.textContent)}
                        </div>
                    ` : ''}
                </div>
                
                <!-- Botones de Recordatorio -->
                <div class="makia-reminder-buttons">
                    <button class="makia-send-reminder makia-send-reminder-sms" data-booking-id="${bookingId}" data-method="sms">
                        💬 Enviar SMS
                    </button>
                    <button class="makia-send-reminder makia-send-reminder-whatsapp" data-booking-id="${bookingId}" data-method="whatsapp">
                        📱 Enviar WhatsApp
                    </button>
                </div>
                
                <!-- Notas Internas -->
                <div class="makia-internal-notes-section">
                    <h4>📝 Notas Internas (Solo Operarios)</h4>
                    <div id="makia-notes-list"></div>
                    <form id="makia-add-note-form">
                        <input type="hidden" name="booking_id" value="${bookingId}">
                        <textarea name="note" placeholder="Escribe una nota interna..." rows="3"></textarea>
                        <button type="submit">💾 Guardar Nota</button>
                    </form>
                </div>
            `;
            
            // Cargar notas internas
            setTimeout(function() {
                if (typeof loadBookingNotes === 'function') {
                    loadBookingNotes(bookingId);
                }
            }, 100);
            
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
            
            // Cerrar al hacer click fuera
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }
        
        // ============================================
        // ACCIONES GRUPALES
        // ============================================
        
        // Elementos
        const $selectAll = jQuery('#makia-select-all');
        const $checkboxes = jQuery('.makia-booking-checkbox');
        const $bulkBar = jQuery('.makia-bulk-actions-bar');
        const $selectedCount = jQuery('#makia-selected-count');
        const $clearSelection = jQuery('#makia-clear-selection');
        const $bulkActions = jQuery('.makia-bulk-action');
        
        // Actualizar contador y mostrar/ocultar barra
        function updateBulkBar() {
            const selectedCount = $checkboxes.filter(':checked').length;
            $selectedCount.text(selectedCount);
            
            if (selectedCount > 0) {
                $bulkBar.slideDown(200);
            } else {
                $bulkBar.slideUp(200);
            }
            
            // Actualizar estado de "seleccionar todas"
            $selectAll.prop('checked', selectedCount === $checkboxes.length && selectedCount > 0);
        }
        
        // Seleccionar/deseleccionar todas
        $selectAll.on('change', function() {
            $checkboxes.prop('checked', jQuery(this).is(':checked'));
            updateBulkBar();
        });
        
        // Seleccionar individual
        $checkboxes.on('change', function() {
            updateBulkBar();
        });
        
        // Limpiar selección
        $clearSelection.on('click', function() {
            $checkboxes.prop('checked', false);
            $selectAll.prop('checked', false);
            updateBulkBar();
        });
        
        // Acciones grupales
        $bulkActions.on('click', function() {
            const action = jQuery(this).data('action');
            const selectedIds = $checkboxes.filter(':checked').map(function() {
                return jQuery(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                alert('Por favor, selecciona al menos una reserva');
                return;
            }
            
            // Confirmación
            let confirmMessage = '';
            switch(action) {
                case 'approve':
                    confirmMessage = `¿Aprobar ${selectedIds.length} reservas seleccionadas?`;
                    break;
                case 'reject':
                    confirmMessage = `¿Rechazar ${selectedIds.length} reservas seleccionadas?`;
                    break;
                case 'cancel':
                    confirmMessage = `¿Cancelar ${selectedIds.length} reservas seleccionadas?`;
                    break;
                case 'reminder':
                    confirmMessage = `¿Enviar recordatorio a ${selectedIds.length} reservas seleccionadas?`;
                    break;
                case 'noshow':
                    confirmMessage = `¿Marcar ${selectedIds.length} reservas como No-Show y añadir a lista negra?`;
                    break;
            }
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Deshabilitar botones
            const $btn = jQuery(this);
            const originalText = $btn.text();
            $bulkActions.prop('disabled', true);
            $btn.text('Procesando...');
            
            // Enviar petición AJAX
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'makia_bulk_action',
                    bulk_action: action,
                    booking_ids: selectedIds,
                    nonce: '<?php echo wp_create_nonce("makia_bulk_action"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message || 'Acción completada correctamente', 'success');
                        // Recargar página después de 1 segundo
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification(response.data || 'Error al procesar la acción', 'error');
                        $bulkActions.prop('disabled', false);
                        $btn.text(originalText);
                    }
                },
                error: function() {
                    showNotification('Error de conexión', 'error');
                    $bulkActions.prop('disabled', false);
                    $btn.text(originalText);
                }
            });
        });
        
        // ============================================
        // FILTROS AVANZADOS
        // ============================================
        
        // Toggle de filtros (expandir/colapsar)
        jQuery('#toggle-filters').on('click', function() {
            var $content = jQuery('#filters-content');
            var $icon = jQuery('#filter-toggle-icon');
            
            if ($content.is(':visible')) {
                // Colapsar
                $content.slideUp(300);
                $icon.text('▶');
                localStorage.setItem('makia_filters_expanded', 'false');
            } else {
                // Expandir
                $content.slideDown(300);
                $icon.text('▼');
                localStorage.setItem('makia_filters_expanded', 'true');
            }
        });
        
        // Restaurar estado de filtros al cargar
        jQuery(document).ready(function() {
            var filtersExpanded = localStorage.getItem('makia_filters_expanded');
            if (filtersExpanded === 'true') {
                jQuery('#filters-content').show();
                jQuery('#filter-toggle-icon').text('▼');
            }
        });
        
        // Función global para aplicar filtros (usada por tarjetas interactivas)
        window.applyAdvancedFilters = function() {
            var status = jQuery('#filter-status').val();
            var period = jQuery('#filter-period').val();
            var dateFrom = jQuery('#filter-date-from').val();
            var dateTo = jQuery('#filter-date-to').val();
            var timeRange = jQuery('#filter-time-range').val();
            var guests = jQuery('#filter-guests').val();
            var search = jQuery('#filter-search').val();
            
            // Construir URL con parámetros
            var params = [];
            if (status) params.push('filter_status=' + status);
            if (period) params.push('filter_period=' + period);
            if (dateFrom) params.push('filter_date_from=' + dateFrom);
            if (dateTo) params.push('filter_date_to=' + dateTo);
            if (timeRange) params.push('filter_time_range=' + timeRange);
            if (guests) params.push('filter_guests=' + guests);
            if (search) params.push('filter_search=' + encodeURIComponent(search));
            
            var url = '?page=makia';
            if (params.length > 0) {
                url += '&' + params.join('&');
            }
            
            window.location.href = url;
        };
        
        // Botón aplicar filtros
        jQuery('#apply-filters').on('click', function() {
            applyAdvancedFilters();
        });
        
        // Botón limpiar filtros
        jQuery('#clear-filters').on('click', function() {
            jQuery('#filter-status').val('');
            jQuery('#filter-period').val('');
            jQuery('#filter-date-from').val('');
            jQuery('#filter-date-to').val('');
            jQuery('#filter-time-range').val('');
            jQuery('#filter-guests').val('');
            jQuery('#filter-search').val('');
            window.location.href = '?page=makia';
        });
        
        // Período predefinido
        jQuery('#filter-period').on('change', function() {
            var period = jQuery(this).val();
            var today = new Date();
            var dateFrom, dateTo;
            
            switch(period) {
                case 'today':
                    dateFrom = dateTo = today.toISOString().split('T')[0];
                    break;
                case 'tomorrow':
                    var tomorrow = new Date(today);
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    dateFrom = dateTo = tomorrow.toISOString().split('T')[0];
                    break;
                case 'this-week':
                    dateFrom = today.toISOString().split('T')[0];
                    var nextWeek = new Date(today);
                    nextWeek.setDate(nextWeek.getDate() + 7);
                    dateTo = nextWeek.toISOString().split('T')[0];
                    break;
                case 'this-month':
                    var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                    var lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    dateFrom = firstDay.toISOString().split('T')[0];
                    dateTo = lastDay.toISOString().split('T')[0];
                    break;
                default:
                    dateFrom = dateTo = '';
            }
            
            jQuery('#filter-date-from').val(dateFrom);
            jQuery('#filter-date-to').val(dateTo);
        });
        
        // Enter en campo de búsqueda
        jQuery('#filter-search').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                applyAdvancedFilters();
            }
        });
        
        </script>
        <?php
    }
    
    /**
     * Actualizar estado de reserva vía AJAX
     */
    public function update_booking_status_ajax() {
        global $wpdb;
        
        // Verificar nonce
        if (!isset($_POST['makia_booking_status_nonce']) || !wp_verify_nonce($_POST['makia_booking_status_nonce'], 'makia_booking_status_action')) {
            wp_send_json_error('Acción no autorizada');
            return;
        }

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
            return;
        }

        $booking_id = intval($_POST['booking_id']);
        $new_status = sanitize_text_field($_POST['status']);

        // Validar estado
        $valid_statuses = array('pending', 'approved', 'rejected', 'cancelled');
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error('Estado no válido');
            return;
        }

        // Obtener reserva antes de actualizar
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $booking_id
        ));

        if (!$booking) {
            wp_send_json_error('Reserva no encontrada');
            return;
        }
        
        // Actualizar estado
        $result = $wpdb->update(
            $this->table_name,
            array('status' => $new_status),
            array('id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Registrar en auditoría
            MakIA_Audit::log_action(
                'booking_status_updated',
                'booking',
                $booking_id,
                array(
                    'old_status' => $booking->status,
                    'new_status' => $new_status,
                    'method' => 'admin_panel'
                )
            );

            // Enviar email de notificación al cliente
            $this->send_status_update_email($booking_id, $new_status);
            
            // Obtener estadísticas actualizadas
            $stats = $this->get_stats();
            
            $message = 'Estado actualizado correctamente';
            if ($new_status === 'approved') {
                $message = 'Reserva aprobada correctamente';
            } elseif ($new_status === 'rejected') {
                $message = 'Reserva rechazada';
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'booking_id' => $booking_id,
                'new_status' => $new_status,
                'stats' => $stats
            ));
        } else {
            wp_send_json_error('Error al actualizar el estado');
        }
    }
    
    /**
     * Agregar nota interna a una reserva (AJAX)
     */
    public function add_booking_note_ajax() {
        // Verificar nonce
        if (!isset($_POST['makia_note_nonce']) || !wp_verify_nonce($_POST['makia_note_nonce'], 'makia_add_note_action')) {
            wp_send_json_error('Acción no autorizada');
            return;
        }

        global $wpdb;

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
            return;
        }

        $booking_id = intval($_POST['booking_id']);
        $note = sanitize_textarea_field($_POST['note']);

        if (empty($note)) {
            wp_send_json_error('La nota no puede estar vacía');
            return;
        }
        
        $notes_table = $wpdb->prefix . 'makia_booking_notes';
        $current_user_id = get_current_user_id();
        $result = $wpdb->insert(
            $notes_table,
            array(
                'booking_id' => $booking_id,
                'user_id' => $current_user_id,
                'note' => $note,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        if ($result) {
            $note_id = $wpdb->insert_id;
            $user = wp_get_current_user();

            // Registrar en auditoría
            MakIA_Audit::log_action(
                'note_added',
                'note',
                $note_id,
                array(
                    'booking_id' => $booking_id,
                    'note_preview' => mb_strimwidth($note, 0, 50, '...')
                )
            );

            wp_send_json_success(array(
                'message' => 'Nota agregada correctamente',
                'note' => array(
                    'id' => $note_id,
                    'note' => $note,
                    'user_name' => $user->display_name,
                    'created_at' => current_time('mysql')
                )
            ));
        } else {
            wp_send_json_error('Error al guardar la nota');
        }
    }
    
    /**
     * Obtener notas de una reserva (AJAX)
     */
    public function get_booking_notes_ajax() {
        // Verificar nonce
        if (!isset($_POST['makia_note_nonce']) || !wp_verify_nonce($_POST['makia_note_nonce'], 'makia_get_notes_action')) {
            wp_send_json_error('Acción no autorizada');
            return;
        }

        global $wpdb;

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        $notes_table = $wpdb->prefix . 'makia_booking_notes';
        
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as user_name 
             FROM {$notes_table} n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE n.booking_id = %d
             ORDER BY n.created_at DESC",
            $booking_id
        ));
        
        wp_send_json_success(array('notes' => $notes));
    }
    
    /**
     * Enviar recordatorio al cliente (AJAX)
     */
    public function send_reminder_ajax() {
        global $wpdb;

        check_ajax_referer('makia_admin_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        $method = sanitize_text_field($_POST['method']); // 'sms' o 'whatsapp'
        
        // Obtener reserva
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            wp_send_json_error('Reserva no encontrada');
            return;
        }

        // Generar mensaje de recordatorio
        $fecha = date('d/m/Y', strtotime($booking->booking_date));
        $hora = date('H:i', strtotime($booking->booking_time));
        $restaurant_name = get_option('makia_restaurant_name', get_bloginfo('name'));
        $message = "Hola {$booking->name}, te recordamos tu reserva para el {$fecha} a las {$hora}. ¡Te esperamos! - {$restaurant_name}";
        
        // Generar URL según el método
        if ($method === 'whatsapp') {
            $phone = preg_replace('/[^0-9]/', '', $booking->phone);
            $url = 'https://wa.me/' . $phone . '?text=' . urlencode($message);
        } else {
            $url = 'sms:' . $booking->phone . '?body=' . urlencode($message);
        }
        
        // Registrar en notas internas
        $notes_table = $wpdb->prefix . 'makia_booking_notes';
        $wpdb->insert(
            $notes_table,
            array(
                'booking_id' => $booking_id,
                'user_id' => get_current_user_id(),
                'note' => "Recordatorio enviado vía {$method}",
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        wp_send_json_success(array(
            'message' => 'Recordatorio preparado',
            'url' => $url
        ));
    }
    
    /**
     * Handler para acciones grupales
     */
    public function handle_bulk_action() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'makia_bulk_action')) {
            wp_send_json_error('Nonce inválido');
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
            return;
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);

        if (!isset($_POST['booking_ids']) || !is_array($_POST['booking_ids'])) {
            wp_send_json_error('Invalid booking IDs');
            return;
        }
        $booking_ids = array_slice(array_map('intval', $_POST['booking_ids']), 0, 100);

        if (empty($booking_ids)) {
            wp_send_json_error('No se seleccionaron reservas');
            return;
        }
        
        global $wpdb;
        $success_count = 0;
        
        switch ($action) {
            case 'approve':
                foreach ($booking_ids as $id) {
                    $result = $wpdb->update(
                        $this->table_name,
                        array('status' => 'approved'),
                        array('id' => $id),
                        array('%s'),
                        array('%d')
                    );
                    if ($result !== false) {
                        $success_count++;
                        MakIA_Audit::log_action('booking_status_changed', $id, array('new_status' => 'approved'));
                    }
                }
                wp_send_json_success(array('message' => "$success_count reservas aprobadas correctamente"));
                break;
                
            case 'reject':
                foreach ($booking_ids as $id) {
                    $result = $wpdb->update(
                        $this->table_name,
                        array('status' => 'rejected'),
                        array('id' => $id),
                        array('%s'),
                        array('%d')
                    );
                    if ($result !== false) {
                        $success_count++;
                        MakIA_Audit::log_action('booking_status_changed', $id, array('new_status' => 'rejected'));
                    }
                }
                wp_send_json_success(array('message' => "$success_count reservas rechazadas correctamente"));
                break;
                
            case 'cancel':
                foreach ($booking_ids as $id) {
                    $result = $wpdb->update(
                        $this->table_name,
                        array('status' => 'cancelled'),
                        array('id' => $id),
                        array('%s'),
                        array('%d')
                    );
                    if ($result !== false) {
                        $success_count++;
                        MakIA_Audit::log_action('booking_status_changed', $id, array('new_status' => 'cancelled'));
                    }
                }
                wp_send_json_success(array('message' => "$success_count reservas canceladas correctamente"));
                break;
                
            case 'reminder':
                foreach ($booking_ids as $id) {
                    $booking = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$this->table_name} WHERE id = %d",
                        $id
                    ));
                    
                    if ($booking && $booking->status === 'approved') {
                        // Enviar recordatorio
                        $restaurant_name = get_option('makia_restaurant_name', get_bloginfo('name'));
                        $restaurant_email = get_option('makia_restaurant_email', get_option('admin_email'));
                        $restaurant_phone = get_option('makia_restaurant_phone', '');
                        $restaurant_address = get_option('makia_restaurant_address', '');
                        $subject = "Recordatorio de reserva - $restaurant_name";
                        
                        $message = "Hola {$booking->name},\n\n";
                        $message .= "Te recordamos tu reserva en $restaurant_name:\n\n";
                        $message .= "DETALLES DE LA RESERVA:\n";
                        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                        $message .= "Fecha: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
                        $message .= "Hora: " . date('H:i', strtotime($booking->booking_time)) . "\n";
                        $message .= "Comensales: {$booking->guests}\n";
                        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                        
                        // Enlace de gestión de reserva
                        if (!empty($booking->edit_token)) {
                            $manage_url = home_url('/gestionar-reserva/?token=' . $booking->edit_token);
                            $modify_url = $manage_url . '&action=modify';
                            $cancel_url = $manage_url . '&action=cancel';
                            
                            $message .= "GESTIONA TU RESERVA:
";
                            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
";
                            $message .= "<a href=\"$modify_url\">📝 Modificar mi reserva</a>
";
                            $message .= "<a href=\"$cancel_url\">❌ Cancelar mi reserva</a>
";
                            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

";
                        }
                        
                        // Ubicación del restaurante
                        if (!empty($restaurant_address)) {
                            $message .= "UBICACIÓN:\n";
                            $message .= $restaurant_address . "\n\n";
                        }
                        
                        // Contacto del restaurante
                        $message .= "CONTACTO:\n";
                        if (!empty($restaurant_phone)) {
                            $message .= "Teléfono: $restaurant_phone\n";
                        }
                        $message .= "Email: $restaurant_email\n\n";
                        
                        $message .= "¡Te esperamos!\n\n";
                        $message .= "Saludos,\n$restaurant_name\n\n";
                        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                        $message .= "Powered by MakIA Reservas - https://contacpro.app\n";
                        
                        $headers = array(
                            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>',
                            'Reply-To: ' . $restaurant_email,
                            'Content-Type: text/html; charset=UTF-8'
                        );
                        
                        if (wp_mail($booking->email, $subject, $message, $headers)) {
                            $success_count++;
                        }
                    }
                }
                wp_send_json_success(array('message' => "Recordatorio enviado a $success_count reservas"));
                break;
                
            case 'noshow':
                if (class_exists('MakIA_Blacklist')) {
                    $blacklist = new MakIA_Blacklist();
                    foreach ($booking_ids as $id) {
                        $booking = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$this->table_name} WHERE id = %d",
                            $id
                        ));
                        
                        if ($booking) {
                            // Marcar como cancelada
                            $wpdb->update(
                                $this->table_name,
                                array('status' => 'cancelled'),
                                array('id' => $id),
                                array('%s'),
                                array('%d')
                            );
                            
                            // Incrementar no-show en lista negra
                            $blacklist->increment_no_show($booking->email, $booking->phone);
                            $success_count++;
                            MakIA_Audit::log_action('booking_status_changed', $id, array('new_status' => 'noshow'));
                        }
                    }
                    wp_send_json_success(array('message' => "$success_count reservas marcadas como No-Show y añadidas a lista negra"));
                } else {
                    wp_send_json_error('Funcionalidad de lista negra no disponible');
                }
                break;
                
            default:
                wp_send_json_error('Acción no válida');
        }
    }
    
    /**
     * Modificar reserva desde el frontend (pública)
     */
    public function modify_booking_public() {
        global $wpdb;
        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'makia_modify_booking_nonce')) {
            wp_send_json_error(array('message' => 'Seguridad: Solicitud no válida'));
            return;
        }
        
        // Obtener y validar token
        $token = sanitize_text_field($_POST['token'] ?? '');
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Token no válido'));
            return;
        }
        
        // Buscar reserva por token
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE edit_token = %s",
            $token
        ));
        
        if (!$booking) {
            wp_send_json_error(array('message' => 'Reserva no encontrada'));
            return;
        }
        
        // No permitir modificar reservas canceladas
        if ($booking->status === 'cancelled') {
            wp_send_json_error(array('message' => 'No se puede modificar una reserva cancelada'));
            return;
        }
        
        // Validar nuevos datos
        $new_date = sanitize_text_field($_POST['date'] ?? '');
        $new_time = sanitize_text_field($_POST['time'] ?? '');
        $new_guests = intval($_POST['guests'] ?? 0);

        if (empty($new_date) || empty($new_time) || $new_guests < 1) {
            wp_send_json_error(array('message' => 'Datos incompletos'));
            return;
        }

        // Validar que comensales esté dentro de rango permitido
        $max_per_reservation = intval(get_option('makia_max_guests_per_reservation', 20));
        if ($new_guests < 1 || $new_guests > $max_per_reservation) {
            wp_send_json_error(array('message' => "El número de comensales debe ser entre 1 y {$max_per_reservation}"));
            return;
        }

        // Validar formato de fecha (Y-m-d)
        $date_obj = DateTime::createFromFormat('Y-m-d', $new_date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $new_date) {
            wp_send_json_error(array('message' => 'Formato de fecha inválido'));
            return;
        }

        // Validar que la fecha no esté en el pasado
        $today = new DateTime('today');
        if ($date_obj < $today) {
            wp_send_json_error(array('message' => 'No se pueden hacer reservas para fechas pasadas'));
            return;
        }

        // Validar formato de hora (H:i)
        $time_obj = DateTime::createFromFormat('H:i', $new_time);
        if (!$time_obj || $time_obj->format('H:i') !== $new_time) {
            wp_send_json_error(array('message' => 'Formato de hora inválido'));
            return;
        }
        
        // Guardar datos antiguos para notificación
        $old_date = $booking->booking_date;
        $old_time = $booking->booking_time;
        $old_guests = $booking->guests;
        
        // Actualizar reserva
        $result = $wpdb->update(
            $this->table_name,
            array(
                'booking_date' => $new_date,
                'booking_time' => $new_time,
                'guests' => $new_guests,
                'status' => 'pending' // Volver a pendiente para revisión
            ),
            array('id' => $booking->id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Error al modificar la reserva'));
            return;
        }
        
        // Notificar al restaurante del cambio
        $this->notify_restaurant_modification($booking->id, $old_date, $old_time, $old_guests, $new_date, $new_time, $new_guests);
        
        // Enviar confirmación al cliente
        $this->send_modification_confirmation($booking->id);
        
        wp_send_json_success(array('message' => 'Reserva modificada correctamente. El restaurante revisará los cambios.'));
    }
    
    /**
     * Cancelar reserva desde el frontend (pública)
     */
    public function cancel_booking_public() {
        global $wpdb;
        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'makia_cancel_booking_nonce')) {
            wp_send_json_error(array('message' => 'Seguridad: Solicitud no válida'));
            return;
        }
        
        // Obtener y validar token
        $token = sanitize_text_field($_POST['token'] ?? '');
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Token no válido'));
            return;
        }
        
        // Buscar reserva por token
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE edit_token = %s",
            $token
        ));
        
        if (!$booking) {
            wp_send_json_error(array('message' => 'Reserva no encontrada'));
            return;
        }
        
        // Verificar si ya está cancelada
        if ($booking->status === 'cancelled') {
            wp_send_json_error(array('message' => 'Esta reserva ya está cancelada'));
            return;
        }
        
        // Cancelar reserva
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'cancelled'),
            array('id' => $booking->id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Error al cancelar la reserva'));
            return;
        }
        
        // Notificar al restaurante de la cancelación
        $this->notify_restaurant_cancellation($booking->id);
        
        // Enviar confirmación al cliente
        $this->send_cancellation_confirmation($booking->id);
        
        wp_send_json_success(array('message' => 'Reserva cancelada correctamente'));
    }
    
    /**
     * Notificar al restaurante de una modificación
     */
    private function notify_restaurant_modification($booking_id, $old_date, $old_time, $old_guests, $new_date, $new_time, $new_guests) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) return;
        
        $restaurant_name = get_option('makia_restaurant_name', get_bloginfo('name'));
        $restaurant_email = get_option('makia_restaurant_email', get_option('admin_email'));
        
        $subject = "⚠️ Reserva Modificada por el Cliente - $restaurant_name";
        
        $message = "Se ha modificado una reserva:\n\n";
        $message .= "CLIENTE: {$booking->name}\n";
        $message .= "EMAIL: {$booking->email}\n";
        $message .= "TELÉFONO: {$booking->phone}\n\n";
        $message .= "DATOS ANTERIORES:\n";
        $message .= "Fecha: " . date('d/m/Y', strtotime($old_date)) . "\n";
        $message .= "Hora: " . date('H:i', strtotime($old_time)) . "\n";
        $message .= "Comensales: $old_guests\n\n";
        $message .= "NUEVOS DATOS:\n";
        $message .= "Fecha: " . date('d/m/Y', strtotime($new_date)) . "\n";
        $message .= "Hora: " . date('H:i', strtotime($new_time)) . "\n";
        $message .= "Comensales: $new_guests\n\n";
        $message .= "La reserva ha vuelto a estado PENDIENTE para tu revisión.\n\n";
        $message .= "Gestionar en: " . admin_url('admin.php?page=makia');
        
        $headers = array(
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>',
            'Reply-To: ' . $restaurant_email
        );
        
        wp_mail($restaurant_email, $subject, $message, $headers);
    }
    
    /**
     * Notificar al restaurante de una cancelación
     */
    private function notify_restaurant_cancellation($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) return;
        
        $restaurant_name = get_option('makia_restaurant_name', get_bloginfo('name'));
        $restaurant_email = get_option('makia_restaurant_email', get_option('admin_email'));
        
        $subject = "❌ Reserva Cancelada por el Cliente - $restaurant_name";
        
        $message = "Un cliente ha cancelado su reserva:\n\n";
        $message .= "CLIENTE: {$booking->name}\n";
        $message .= "EMAIL: {$booking->email}\n";
        $message .= "TELÉFONO: {$booking->phone}\n\n";
        $message .= "DATOS DE LA RESERVA:\n";
        $message .= "Fecha: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
        $message .= "Hora: " . date('H:i', strtotime($booking->booking_time)) . "\n";
        $message .= "Comensales: {$booking->guests}\n\n";
        $message .= "Gestionar en: " . admin_url('admin.php?page=makia');
        
        $headers = array(
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>',
            'Reply-To: ' . $restaurant_email
        );
        
        wp_mail($restaurant_email, $subject, $message, $headers);
    }
    
    /**
     * Enviar confirmación de modificación al cliente
     */
    private function send_modification_confirmation($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) return;
        
        $restaurant_name = get_option('makia_restaurant_name', get_bloginfo('name'));
        $restaurant_email = get_option('makia_restaurant_email', get_option('admin_email'));
        $subject = "Reserva Modificada - $restaurant_name";
        
        $message = "Hola {$booking->name},\n\n";
        $message .= "Tu reserva ha sido modificada correctamente.\n\n";
        $message .= "NUEVOS DATOS:\n";
        $message .= "Fecha: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
        $message .= "Hora: " . date('H:i', strtotime($booking->booking_time)) . "\n";
        $message .= "Comensales: {$booking->guests}\n\n";
        $message .= "El restaurante revisará los cambios y te confirmará la disponibilidad.\n\n";
        
        // Añadir enlaces de gestión
        $manage_url = home_url('/gestionar-reserva/?token=' . $booking->edit_token);
        $modify_url = $manage_url . '&action=modify';
        $cancel_url = $manage_url . '&action=cancel';
        
        $message .= "GESTIONA TU RESERVA:
";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
";
        $message .= "<a href=\"$modify_url\">📝 Modificar mi reserva</a>
";
        $message .= "<a href=\"$cancel_url\">❌ Cancelar mi reserva</a>
";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

";
        
        $message .= "Saludos,\n$restaurant_name";
        
        $headers = array(
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>',
            'Reply-To: ' . $restaurant_email,
            'Content-Type: text/html; charset=UTF-8'
        );
        
        wp_mail($booking->email, $subject, $message, $headers);
    }
    
    /**
     * Enviar confirmación de cancelación al cliente
     */
    private function send_cancellation_confirmation($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) return;
        
        $restaurant_name = get_option('makia_restaurant_name', get_bloginfo('name'));
        $restaurant_email = get_option('makia_restaurant_email', get_option('admin_email'));
        $subject = "Reserva Cancelada - $restaurant_name";
        
        $message = "Hola {$booking->name},\n\n";
        $message .= "Tu reserva ha sido cancelada correctamente.\n\n";
        $message .= "DATOS DE LA RESERVA CANCELADA:\n";
        $message .= "Fecha: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
        $message .= "Hora: " . date('H:i', strtotime($booking->booking_time)) . "\n";
        $message .= "Comensales: {$booking->guests}\n\n";
        $message .= "Esperamos verte en otra ocasión.\n\n";
        $message .= "Saludos,\n$restaurant_name";

        $headers = array(
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>',
            'Reply-To: ' . $restaurant_email,
            'Content-Type: text/plain; charset=UTF-8'
        );

        wp_mail($booking->email, $subject, $message, $headers);
    }
}

// Inicializar
if (!isset($GLOBALS['makia_bookings'])) {
    $GLOBALS['makia_bookings'] = new MakIA_Bookings();
}
