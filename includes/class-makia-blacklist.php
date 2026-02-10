<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Gestión de Lista Negra MakIA
 * Maneja el baneo y desbaneo de usuarios por no-show u otros motivos
 */

class MakIA_Blacklist {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'makia_blacklist';
        
        // Hooks AJAX
        add_action('wp_ajax_makia_ban_user', array($this, 'ban_user_ajax'));
        add_action('wp_ajax_makia_unban_user', array($this, 'unban_user_ajax'));
        add_action('wp_ajax_makia_increment_noshow', array($this, 'increment_noshow_ajax'));
    }
    
    /**
     * Verificar si un usuario está baneado
     */
    public function is_banned($email = null, $phone = null) {
        global $wpdb;
        
        if (!$email && !$phone) {
            return false;
        }
        
        $where_clauses = array();
        $where_values = array();
        
        if ($email) {
            $where_clauses[] = "email = %s";
            $where_values[] = $email;
        }
        
        if ($phone) {
            $where_clauses[] = "phone = %s";
            $where_values[] = $phone;
        }
        
        $where_sql = implode(' OR ', $where_clauses);
        
        $query = "SELECT * FROM {$this->table_name} WHERE ({$where_sql}) AND is_active = 1 LIMIT 1";
        $banned = $wpdb->get_row($wpdb->prepare($query, $where_values));
        
        return $banned ? $banned : false;
    }
    
    /**
     * Banear usuario
     */
    public function ban_user($email, $phone, $reason, $notes = '') {
        global $wpdb;
        
        // Verificar si ya está baneado
        $existing = $this->is_banned($email, $phone);
        if ($existing) {
            return array('success' => false, 'message' => 'Este usuario ya está en la lista negra');
        }
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'email' => $email,
                'phone' => $phone,
                'reason' => $reason,
                'no_show_count' => 0,
                'banned_by' => get_current_user_id(),
                'banned_at' => current_time('mysql'),
                'notes' => $notes,
                'is_active' => 1
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d')
        );
        
        if ($result) {
            return array('success' => true, 'message' => 'Usuario añadido a la lista negra correctamente');
        } else {
            return array('success' => false, 'message' => 'Error al añadir usuario a la lista negra');
        }
    }
    
    /**
     * Desbanear usuario
     */
    public function unban_user($id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            array(
                'is_active' => 0,
                'unbanned_at' => current_time('mysql'),
                'unbanned_by' => get_current_user_id()
            ),
            array('id' => $id),
            array('%d', '%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            return array('success' => true, 'message' => 'Usuario desbaneado correctamente');
        } else {
            return array('success' => false, 'message' => 'Error al desbanear usuario');
        }
    }
    
    /**
     * Incrementar contador de no-shows
     */
    public function increment_noshow($email, $phone) {
        global $wpdb;
        
        $banned = $this->is_banned($email, $phone);
        
        if ($banned) {
            // Incrementar contador
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name} SET no_show_count = no_show_count + 1 WHERE id = %d",
                $banned->id
            ));
            return array('success' => true, 'message' => 'Contador de no-shows actualizado');
        } else {
            // Banear automáticamente por no-show
            return $this->ban_user($email, $phone, 'No-show', 'Baneado automáticamente por no presentarse');
        }
    }
    
    /**
     * Obtener todos los usuarios baneados
     */
    public function get_all_banned() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT b.*, u.display_name as banned_by_name 
             FROM {$this->table_name} b 
             LEFT JOIN {$wpdb->users} u ON b.banned_by = u.ID 
             WHERE b.is_active = 1 
             ORDER BY b.banned_at DESC"
        );
    }
    
    /**
     * Banear usuario vía AJAX
     */
    public function ban_user_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
            return;
        }
        
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $reason = sanitize_text_field($_POST['reason']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        if (empty($email) && empty($phone)) {
            wp_send_json_error('Debes proporcionar al menos un email o teléfono');
            return;
        }

        if (empty($reason)) {
            wp_send_json_error('Debes proporcionar una razón');
            return;
        }

        $result = $this->ban_user($email, $phone, $reason, $notes);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Desbanear usuario vía AJAX
     */
    public function unban_user_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
            return;
        }
        
        $id = intval($_POST['id']);
        
        if (empty($id)) {
            wp_send_json_error('ID no válido');
            return;
        }
        
        $result = $this->unban_user($id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Incrementar no-show vía AJAX
     */
    public function increment_noshow_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
            return;
        }
        
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        
        if (empty($email) && empty($phone)) {
            wp_send_json_error('Debes proporcionar al menos un email o teléfono');
            return;
        }

        $result = $this->increment_noshow($email, $phone);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Renderizar página de gestión de lista negra
     */
    public static function render_page() {
        $blacklist_instance = new self();
        
        // Procesar formulario de baneo
        if (isset($_POST['ban_user']) && check_admin_referer('makia_ban_user', 'makia_ban_nonce')) {
            $email = sanitize_email($_POST['ban_email']);
            $phone = sanitize_text_field($_POST['ban_phone']);
            $reason = sanitize_text_field($_POST['ban_reason']);
            $notes = sanitize_textarea_field($_POST['ban_notes']);
            
            $result = $blacklist_instance->ban_user($email, $phone, $reason, $notes);
            
            if ($result['success']) {
                echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            }
        }
        
        // Procesar desbaneo
        if (isset($_GET['action']) && $_GET['action'] === 'unban' && isset($_GET['id']) && check_admin_referer('makia_unban_' . $_GET['id'])) {
            $result = $blacklist_instance->unban_user(intval($_GET['id']));
            
            if ($result['success']) {
                echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            }
        }
        
        $banned_users = $blacklist_instance->get_all_banned();
        
        ?>
        <div class="wrap">
            <!-- Header -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;">
                <h2 style="margin: 0 0 10px 0; color: #dc3232;">🚫 Lista Negra de Usuarios</h2>
                <p style="margin: 0; color: #666;">Gestiona los usuarios que no pueden realizar reservas por no-show u otros motivos.</p>
            </div>
            
            <!-- Formulario para banear usuario -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;">
                <h3 style="margin: 0 0 20px 0; color: #dc3232;">➕ Añadir Usuario a Lista Negra</h3>
                
                <form method="post" action="">
                    <?php wp_nonce_field('makia_ban_user', 'makia_ban_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="ban_email">Email</label></th>
                            <td>
                                <input type="email" name="ban_email" id="ban_email" class="regular-text" 
                                       style="border-radius: 8px; border: 2px solid #e0e0e0;">
                                <p class="description">Email del usuario (opcional si proporcionas teléfono)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ban_phone">Teléfono</label></th>
                            <td>
                                <input type="tel" name="ban_phone" id="ban_phone" class="regular-text" 
                                       style="border-radius: 8px; border: 2px solid #e0e0e0;">
                                <p class="description">Teléfono del usuario (opcional si proporcionas email)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ban_reason">Razón *</label></th>
                            <td>
                                <select name="ban_reason" id="ban_reason" required 
                                        style="border-radius: 8px; border: 2px solid #e0e0e0; padding: 8px;">
                                    <option value="">Selecciona una razón...</option>
                                    <option value="No-show">No-show (No se presentó)</option>
                                    <option value="Cancelación reiterada">Cancelación reiterada</option>
                                    <option value="Comportamiento inapropiado">Comportamiento inapropiado</option>
                                    <option value="Reservas falsas">Reservas falsas</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ban_notes">Notas</label></th>
                            <td>
                                <textarea name="ban_notes" id="ban_notes" rows="3" class="large-text" 
                                          style="border-radius: 8px; border: 2px solid #e0e0e0;"></textarea>
                                <p class="description">Información adicional sobre el baneo</p>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="submit" name="ban_user" class="button button-primary" 
                            style="padding: 12px 30px; border-radius: 8px; font-size: 14px; background: #dc3232; border-color: #dc3232;">
                        🚫 Añadir a Lista Negra
                    </button>
                </form>
            </div>
            
            <!-- Lista de usuarios baneados -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h3 style="margin: 0 0 20px 0; color: #dc3232;">📋 Usuarios Baneados (<?php echo count($banned_users); ?>)</h3>
                
                <?php if (empty($banned_users)): ?>
                    <p style="text-align: center; padding: 40px; color: #999;">No hay usuarios en la lista negra.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" style="border-radius: 12px; overflow: hidden;">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #dc3232 0%, #b32d2e 100%);">
                                <th style="color: #fff; padding: 15px;">Email</th>
                                <th style="color: #fff; padding: 15px;">Teléfono</th>
                                <th style="color: #fff; padding: 15px;">Razón</th>
                                <th style="color: #fff; padding: 15px;">No-shows</th>
                                <th style="color: #fff; padding: 15px;">Baneado por</th>
                                <th style="color: #fff; padding: 15px;">Fecha</th>
                                <th style="color: #fff; padding: 15px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($banned_users as $banned): ?>
                                <tr>
                                    <td><?php echo esc_html($banned->email ?: '-'); ?></td>
                                    <td><?php echo esc_html($banned->phone ?: '-'); ?></td>
                                    <td>
                                        <strong style="color: #dc3232;"><?php echo esc_html($banned->reason); ?></strong>
                                        <?php if ($banned->notes): ?>
                                            <br><small style="color: #999;"><?php echo esc_html($banned->notes); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="background: #dc3232; color: #fff; padding: 4px 10px; border-radius: 12px; font-weight: 600;">
                                            <?php echo intval($banned->no_show_count); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($banned->banned_by_name ?: 'Desconocido'); ?></td>
                                    <td><?php echo esc_html(date('d/m/Y H:i', strtotime($banned->banned_at))); ?></td>
                                    <td>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=makia&tab=blacklist&action=unban&id=' . $banned->id), 'makia_unban_' . $banned->id); ?>" 
                                           class="button button-small" 
                                           onclick="return confirm('¿Desbanear a este usuario?');"
                                           style="background: #46b450; color: #fff; border: none;">
                                            ✅ Desbanear
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
