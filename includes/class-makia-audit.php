<?php
/**
 * MakIA Audit System
 * Sistema completo de auditoría para registrar todas las acciones
 */

class MakIA_Audit {
    
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'makia_audit_log';
        
        // Crear tabla si no existe
        add_action('plugins_loaded', array($this, 'create_table'));
        
        // AJAX para consultar auditoría
        add_action('wp_ajax_makia_get_audit_log', array($this, 'get_audit_log_ajax'));
        add_action('wp_ajax_makia_get_booking_audit', array($this, 'get_booking_audit_ajax'));
    }
    
    /**
     * Crear tabla de auditoría
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action_type varchar(50) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            user_name varchar(255) NOT NULL,
            user_email varchar(255) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            action_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action_type (action_type),
            KEY entity_type (entity_type),
            KEY entity_id (entity_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Registrar acción en el log de auditoría
     */
    public static function log_action($action_type, $entity_type, $entity_id, $action_data = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'makia_audit_log';
        
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $user_name = $current_user->display_name ?: $current_user->user_login;
        $user_email = $current_user->user_email;
        
        // Obtener IP
        $ip_address = self::get_client_ip();
        
        // Obtener User Agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'action_type' => sanitize_text_field($action_type),
                'entity_type' => sanitize_text_field($entity_type),
                'entity_id' => (int) $entity_id,
                'user_id' => $user_id,
                'user_name' => sanitize_text_field($user_name),
                'user_email' => sanitize_email($user_email),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'action_data' => json_encode($action_data),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Obtener IP del cliente
     */
    private static function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Obtener log completo con filtros
     */
    public static function get_audit_log($filters = array(), $limit = 100, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'makia_audit_log';
        
        $where = array('1=1');
        $params = array();
        
        // Filtro por tipo de acción
        if (!empty($filters['action_type'])) {
            $where[] = 'action_type = %s';
            $params[] = $filters['action_type'];
        }
        
        // Filtro por tipo de entidad
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = %s';
            $params[] = $filters['entity_type'];
        }
        
        // Filtro por usuario
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }
        
        // Filtro por fecha desde
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }
        
        // Filtro por fecha hasta
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['date_to'];
        }
        
        $where_sql = implode(' AND ', $where);
        
        $params[] = (int) $limit;
        $params[] = (int) $offset;
        
        if (empty($params) || count($params) === 2) {
            // Solo limit y offset
            $query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $logs = $wpdb->get_results($wpdb->prepare($query, $limit, $offset));
        } else {
            $query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $logs = $wpdb->get_results($wpdb->prepare($query, $params));
        }
        
        return $logs;
    }
    
    /**
     * Obtener auditoría de una reserva específica
     */
    public static function get_booking_audit($booking_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'makia_audit_log';
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE (entity_type = 'booking' AND entity_id = %d) 
             OR (entity_type = 'note' AND JSON_EXTRACT(action_data, '$.booking_id') = %d)
             ORDER BY created_at DESC",
            $booking_id,
            $booking_id
        ));
        
        return $logs;
    }
    
    /**
     * Contar registros de auditoría
     */
    public static function count_audit_logs($filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'makia_audit_log';
        
        $where = array('1=1');
        $params = array();
        
        if (!empty($filters['action_type'])) {
            $where[] = 'action_type = %s';
            $params[] = $filters['action_type'];
        }
        
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = %s';
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }
        
        $where_sql = implode(' AND ', $where);
        
        if (empty($params)) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}");
        } else {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}", $params));
        }
    }
    
    /**
     * Obtener estadísticas de auditoría
     */
    public static function get_stats($days = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'makia_audit_log';
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array();
        
        // Total de acciones
        $stats['total_actions'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
            $date_from
        ));
        
        // Acciones por tipo
        $stats['by_action'] = $wpdb->get_results($wpdb->prepare(
            "SELECT action_type, COUNT(*) as count 
             FROM {$table_name} 
             WHERE created_at >= %s 
             GROUP BY action_type 
             ORDER BY count DESC",
            $date_from
        ), ARRAY_A);
        
        // Acciones por usuario
        $stats['by_user'] = $wpdb->get_results($wpdb->prepare(
            "SELECT user_name, user_email, COUNT(*) as count 
             FROM {$table_name} 
             WHERE created_at >= %s 
             GROUP BY user_id, user_name, user_email 
             ORDER BY count DESC 
             LIMIT 10",
            $date_from
        ), ARRAY_A);
        
        // Actividad por día
        $stats['by_day'] = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$table_name} 
             WHERE created_at >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date ASC",
            $date_from
        ), ARRAY_A);
        
        return $stats;
    }
    
    /**
     * Renderizar widget de auditoría en detalle de reserva
     */
    public static function render_audit_widget($booking_id) {
        $audit_logs = self::get_booking_audit($booking_id);
        ?>
        
        <div class="makia-audit-widget" style="margin-top: 20px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px;">
            <div class="makia-audit-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                <h3 style="margin: 0; color: #667eea; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 22px;">📊</span>
                    Historial de Auditoría
                    <span style="background: #667eea; color: #fff; padding: 2px 10px; border-radius: 12px; font-size: 14px; font-weight: 600;"><?php echo count($audit_logs); ?></span>
                </h3>
            </div>
            
            <div class="makia-audit-timeline" style="position: relative; padding-left: 30px;">
                <?php if (empty($audit_logs)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #999;">
                        <div style="font-size: 48px; margin-bottom: 10px;">📋</div>
                        <p style="margin: 0; font-size: 16px;">No hay registros de auditoría</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $action_labels = array(
                        'booking_created' => array('icon' => '✅', 'text' => 'Reserva creada', 'color' => '#46b450'),
                        'booking_updated' => array('icon' => '✏️', 'text' => 'Reserva actualizada', 'color' => '#2271b1'),
                        'booking_deleted' => array('icon' => '🗑️', 'text' => 'Reserva eliminada', 'color' => '#d63638'),
                        'status_changed' => array('icon' => '🔄', 'text' => 'Estado cambiado', 'color' => '#dba617'),
                        'note_added' => array('icon' => '📝', 'text' => 'Nota añadida', 'color' => '#667eea'),
                        'note_deleted' => array('icon' => '🗑️', 'text' => 'Nota eliminada', 'color' => '#d63638'),
                        'booking_viewed' => array('icon' => '👁️', 'text' => 'Reserva visualizada', 'color' => '#999')
                    );
                    
                    foreach ($audit_logs as $log): 
                        $action_info = isset($action_labels[$log->action_type]) ? $action_labels[$log->action_type] : array('icon' => '📌', 'text' => $log->action_type, 'color' => '#666');
                        $action_data = json_decode($log->action_data, true);
                    ?>
                        <div class="makia-audit-item" style="position: relative; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #f0f0f0;">
                            <!-- Línea vertical de timeline -->
                            <div style="position: absolute; left: -30px; top: 0; bottom: 0; width: 2px; background: #e0e0e0;"></div>
                            
                            <!-- Punto en timeline -->
                            <div style="position: absolute; left: -36px; top: 5px; width: 14px; height: 14px; background: <?php echo $action_info['color']; ?>; border-radius: 50%; border: 3px solid #fff;"></div>
                            
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <span style="font-size: 24px;"><?php echo $action_info['icon']; ?></span>
                                <div style="flex: 1;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 5px;">
                                        <strong style="color: #333; font-size: 15px;"><?php echo $action_info['text']; ?></strong>
                                        <span style="font-size: 12px; color: #999;">
                                            <?php echo date('d/m/Y H:i', strtotime($log->created_at)); ?>
                                        </span>
                                    </div>
                                    
                                    <div style="font-size: 13px; color: #666; margin-bottom: 5px;">
                                        👤 <?php echo esc_html($log->user_name); ?>
                                        <?php if ($log->ip_address): ?>
                                            <span style="color: #999;"> • 🌐 <?php echo esc_html($log->ip_address); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($action_data)): ?>
                                        <div style="background: #f9f9f9; padding: 10px; border-radius: 6px; font-size: 12px; margin-top: 8px;">
                                            <?php 
                                            if ($log->action_type === 'status_changed' && isset($action_data['from_status']) && isset($action_data['to_status'])) {
                                                echo '<span style="color: #999;">Estado:</span> ';
                                                echo '<span style="background: #ddd; padding: 2px 6px; border-radius: 3px;">' . esc_html($action_data['from_status']) . '</span>';
                                                echo ' → ';
                                                echo '<span style="background: ' . $action_info['color'] . '; color: #fff; padding: 2px 6px; border-radius: 3px;">' . esc_html($action_data['to_status']) . '</span>';
                                            } else {
                                                foreach ($action_data as $key => $value) {
                                                    if (is_scalar($value)) {
                                                        echo '<div><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</div>';
                                                    }
                                                }
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * Renderizar página de auditoría completa
     */
    public static function render_audit_page() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para acceder a esta página');
        }
        
        $stats = self::get_stats(30);
        $logs = self::get_audit_log(array(), 50);
        ?>
        
        <div class="wrap">
            <h1 style="display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 36px;">📊</span>
                Auditoría del Sistema
            </h1>
            
            <!-- Estadísticas -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Total de Acciones (30 días)</div>
                    <div style="font-size: 36px; font-weight: 700;"><?php echo number_format($stats['total_actions']); ?></div>
                </div>
                
                <div style="background: #fff; border: 2px solid #46b450; padding: 25px; border-radius: 12px;">
                    <div style="font-size: 14px; color: #666; margin-bottom: 8px;">Reservas Creadas</div>
                    <div style="font-size: 36px; font-weight: 700; color: #46b450;">
                        <?php 
                        $created = array_filter($stats['by_action'], function($item) {
                            return $item['action_type'] === 'booking_created';
                        });
                        echo $created ? $created[0]['count'] : 0;
                        ?>
                    </div>
                </div>
                
                <div style="background: #fff; border: 2px solid #2271b1; padding: 25px; border-radius: 12px;">
                    <div style="font-size: 14px; color: #666; margin-bottom: 8px;">Notas Añadidas</div>
                    <div style="font-size: 36px; font-weight: 700; color: #2271b1;">
                        <?php 
                        $notes = array_filter($stats['by_action'], function($item) {
                            return $item['action_type'] === 'note_added';
                        });
                        echo $notes ? $notes[0]['count'] : 0;
                        ?>
                    </div>
                </div>
                
                <div style="background: #fff; border: 2px solid #dba617; padding: 25px; border-radius: 12px;">
                    <div style="font-size: 14px; color: #666; margin-bottom: 8px;">Cambios de Estado</div>
                    <div style="font-size: 36px; font-weight: 700; color: #dba617;">
                        <?php 
                        $status = array_filter($stats['by_action'], function($item) {
                            return $item['action_type'] === 'status_changed';
                        });
                        echo $status ? $status[0]['count'] : 0;
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Usuarios más activos -->
            <?php if (!empty($stats['by_user'])): ?>
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 25px; margin-bottom: 30px;">
                <h2 style="margin: 0 0 20px 0; color: #667eea; font-size: 20px;">👥 Usuarios Más Activos (últimos 30 días)</h2>
                <table class="wp-list-table widefat striped" style="border: none;">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['by_user'] as $user): ?>
                        <tr>
                            <td><strong><?php echo esc_html($user['user_name']); ?></strong></td>
                            <td><?php echo esc_html($user['user_email']); ?></td>
                            <td style="text-align: right;">
                                <span style="background: #667eea; color: #fff; padding: 4px 12px; border-radius: 12px; font-weight: 600;">
                                    <?php echo number_format($user['count']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Log reciente -->
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 25px;">
                <h2 style="margin: 0 0 20px 0; color: #667eea; font-size: 20px;">📋 Actividad Reciente</h2>
                
                <?php if (empty($logs)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #999;">
                        <div style="font-size: 64px; margin-bottom: 15px;">📊</div>
                        <p style="margin: 0; font-size: 18px;">No hay registros de auditoría todavía</p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Acción</th>
                                <th>Usuario</th>
                                <th>Entidad</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $action_labels = array(
                                'booking_created' => array('icon' => '✅', 'text' => 'Reserva creada'),
                                'booking_updated' => array('icon' => '✏️', 'text' => 'Reserva actualizada'),
                                'booking_deleted' => array('icon' => '🗑️', 'text' => 'Reserva eliminada'),
                                'status_changed' => array('icon' => '🔄', 'text' => 'Estado cambiado'),
                                'note_added' => array('icon' => '📝', 'text' => 'Nota añadida'),
                                'note_deleted' => array('icon' => '🗑️', 'text' => 'Nota eliminada')
                            );
                            
                            foreach ($logs as $log): 
                                $action_info = isset($action_labels[$log->action_type]) ? $action_labels[$log->action_type] : array('icon' => '📌', 'text' => $log->action_type);
                            ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($log->created_at)); ?></td>
                                    <td>
                                        <span style="font-size: 18px;"><?php echo $action_info['icon']; ?></span>
                                        <?php echo $action_info['text']; ?>
                                    </td>
                                    <td><strong><?php echo esc_html($log->user_name); ?></strong></td>
                                    <td><?php echo esc_html($log->entity_type); ?> #<?php echo $log->entity_id; ?></td>
                                    <td><code><?php echo esc_html($log->ip_address); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * Handler AJAX para obtener log de auditoría
     */
    public function get_audit_log_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            return;
        }
        
        $filters = array();
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        if (isset($_POST['action_type'])) {
            $filters['action_type'] = sanitize_text_field($_POST['action_type']);
        }
        
        if (isset($_POST['user_id'])) {
            $filters['user_id'] = intval($_POST['user_id']);
        }
        
        $logs = self::get_audit_log($filters, $limit, $offset);
        $total = self::count_audit_logs($filters);
        
        wp_send_json_success(array(
            'logs' => $logs,
            'total' => $total
        ));
    }
    
    /**
     * Handler AJAX para obtener auditoría de una reserva
     */
    public function get_booking_audit_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('makia_manage_bookings')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        $logs = self::get_booking_audit($booking_id);
        
        wp_send_json_success(array('logs' => $logs));
    }
}

// Inicializar
new MakIA_Audit();
