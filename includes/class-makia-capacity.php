<?php
/**
 * Gestión de Capacidad por Franjas Horarias
 * Similar a Five Star Restaurant Reservations
 */

class MakIA_Capacity {
    
    private $table_time_slots;
    private $table_config;
    private $table_bookings;
    
    public function __construct() {
        global $wpdb;
        $this->table_time_slots = $wpdb->prefix . 'makia_time_slots';
        $this->table_config = $wpdb->prefix . 'makia_capacity_config';
        $this->table_bookings = $wpdb->prefix . 'makia_bookings';
        
        // Hooks AJAX
        add_action('wp_ajax_makia_get_time_slots', array($this, 'get_time_slots_ajax'));
        add_action('wp_ajax_makia_save_time_slot', array($this, 'save_time_slot_ajax'));
        add_action('wp_ajax_makia_delete_time_slot', array($this, 'delete_time_slot_ajax'));
        add_action('wp_ajax_makia_save_capacity_config', array($this, 'save_capacity_config_ajax'));
    }
    
    /**
     * Obtener configuración de capacidad
     */
    public function get_config($key, $default = null) {
        global $wpdb;
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT config_value FROM {$this->table_config} WHERE config_key = %s",
            $key
        ));
        
        return $value !== null ? $value : $default;
    }
    
    /**
     * Guardar configuración de capacidad
     */
    public function save_config($key, $value) {
        global $wpdb;
        
        return $wpdb->replace(
            $this->table_config,
            array(
                'config_key' => $key,
                'config_value' => $value
            ),
            array('%s', '%s')
        );
    }
    
    /**
     * Obtener franja horaria para una fecha/hora específica
     */
    public function get_time_slot_for_datetime($date, $time) {
        global $wpdb;
        
        $day_of_week = strtolower(date('l', strtotime($date)));
        $time_formatted = date('H:i:s', strtotime($time));
        
        // Buscar franja específica para ese día de la semana
        $slot = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_time_slots} 
            WHERE is_active = 1 
            AND day_of_week = %s
            AND %s >= start_time 
            AND %s <= end_time
            ORDER BY id DESC
            LIMIT 1",
            $day_of_week,
            $time_formatted,
            $time_formatted
        ));
        
        // Si no hay franja específica, buscar franja para 'all'
        if (!$slot) {
            $slot = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_time_slots} 
                WHERE is_active = 1 
                AND day_of_week = 'all'
                AND %s >= start_time 
                AND %s <= end_time
                ORDER BY id DESC
                LIMIT 1",
                $time_formatted,
                $time_formatted
            ));
        }
        
        return $slot;
    }
    
    /**
     * Validar capacidad disponible para una reserva
     */
    public function validate_capacity($date, $time, $guests, $exclude_booking_id = null) {
        // Verificar si las restricciones están habilitadas
        if ($this->get_config('enable_capacity_restrictions') != '1') {
            return array('valid' => true);
        }
        
        // Obtener configuración de franja horaria
        $time_slot = $this->get_time_slot_for_datetime($date, $time);
        
        if (!$time_slot) {
            // No hay franja horaria configurada, permitir reserva
            return array('valid' => true);
        }
        
        // Calcular ventana de tiempo (dining block)
        $block_length = $time_slot->dining_block_length;
        $booking_datetime = strtotime("$date $time");
        $block_start = date('Y-m-d H:i:s', $booking_datetime);
        $block_end = date('Y-m-d H:i:s', $booking_datetime + ($block_length * 60));
        
        // Obtener reservas activas en esa ventana de tiempo
        global $wpdb;
        
        $query = "SELECT * FROM {$this->table_bookings} 
                  WHERE status IN ('pending', 'approved')
                  AND booking_date = %s
                  AND (
                      (booking_time >= %s AND booking_time < %s)
                      OR (DATE_ADD(CONCAT(booking_date, ' ', booking_time), INTERVAL %d MINUTE) > %s 
                          AND booking_time < %s)
                  )";
        
        $params = array(
            $date,
            date('H:i:s', strtotime($time)),
            date('H:i:s', strtotime($block_end)),
            $block_length,
            $block_start,
            date('H:i:s', strtotime($block_end))
        );
        
        if ($exclude_booking_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_booking_id;
        }
        
        $active_bookings = $wpdb->get_results($wpdb->prepare($query, $params));
        
        // Calcular totales actuales
        $current_reservations = count($active_bookings);
        $current_people = 0;
        foreach ($active_bookings as $booking) {
            $current_people += $booking->guests;
        }
        
        // Validar contra límites
        if ($time_slot->max_reservations && ($current_reservations + 1) > $time_slot->max_reservations) {
            return array(
                'valid' => false,
                'message' => sprintf(
                    'No hay disponibilidad. Máximo de reservas alcanzado (%d/%d).',
                    $current_reservations,
                    $time_slot->max_reservations
                )
            );
        }
        
        if ($time_slot->max_people && ($current_people + $guests) > $time_slot->max_people) {
            return array(
                'valid' => false,
                'message' => sprintf(
                    'No hay disponibilidad. Capacidad máxima alcanzada (%d/%d personas).',
                    $current_people,
                    $time_slot->max_people
                )
            );
        }
        
        return array(
            'valid' => true,
            'current_reservations' => $current_reservations,
            'max_reservations' => $time_slot->max_reservations,
            'current_people' => $current_people,
            'max_people' => $time_slot->max_people
        );
    }
    
    /**
     * Obtener todas las franjas horarias (AJAX)
     */
    public function get_time_slots_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
            return;
        }
        
        global $wpdb;
        
        $slots = $wpdb->get_results(
            "SELECT * FROM {$this->table_time_slots} ORDER BY day_of_week, start_time"
        );
        
        wp_send_json_success($slots);
    }
    
    /**
     * Guardar franja horaria (AJAX)
     */
    public function save_time_slot_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = sanitize_text_field($_POST['name']);
        $day_of_week = sanitize_text_field($_POST['day_of_week']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        $max_reservations = isset($_POST['max_reservations']) && $_POST['max_reservations'] !== '' ? intval($_POST['max_reservations']) : null;
        $max_people = isset($_POST['max_people']) && $_POST['max_people'] !== '' ? intval($_POST['max_people']) : null;
        $dining_block_length = intval($_POST['dining_block_length']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        global $wpdb;
        
        $data = array(
            'name' => $name,
            'day_of_week' => $day_of_week,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'max_reservations' => $max_reservations,
            'max_people' => $max_people,
            'dining_block_length' => $dining_block_length,
            'is_active' => $is_active
        );
        
        if ($id > 0) {
            // Actualizar
            $result = $wpdb->update(
                $this->table_time_slots,
                $data,
                array('id' => $id),
                array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d'),
                array('%d')
            );
        } else {
            // Insertar
            $result = $wpdb->insert(
                $this->table_time_slots,
                $data,
                array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
            );
            $id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            wp_send_json_success(array('id' => $id, 'message' => 'Franja horaria guardada correctamente'));
        } else {
            wp_send_json_error('Error al guardar franja horaria');
        }
    }
    
    /**
     * Eliminar franja horaria (AJAX)
     */
    public function delete_time_slot_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
            return;
        }
        
        $id = intval($_POST['id']);
        
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_time_slots,
            array('id' => $id),
            array('%d')
        );
        
        if ($result) {
            wp_send_json_success('Franja horaria eliminada correctamente');
        } else {
            wp_send_json_error('Error al eliminar franja horaria');
        }
    }
    
    /**
     * Guardar configuración general (AJAX)
     */
    public function save_capacity_config_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
            return;
        }
        
        $enable = isset($_POST['enable_capacity_restrictions']) ? '1' : '0';
        $default_block = intval($_POST['default_dining_block_length']);
        
        $this->save_config('enable_capacity_restrictions', $enable);
        $this->save_config('default_dining_block_length', $default_block);
        
        wp_send_json_success('Configuración guardada correctamente');
    }
}

// Inicializar
if (!isset($GLOBALS['makia_capacity'])) {
    $GLOBALS['makia_capacity'] = new MakIA_Capacity();
}
