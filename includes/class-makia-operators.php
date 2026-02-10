<?php
if (!defined('ABSPATH')) { exit; }
/**
 * MakIA Operators Manager
 * Sistema de gestión de operarios para el restaurante
 */

class MakIA_Operators {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Crear rol de operario al activar
        add_action('init', array($this, 'create_operator_role'));
        
        // AJAX para gestión de operarios
        add_action('wp_ajax_makia_add_operator', array($this, 'add_operator_ajax'));
        add_action('wp_ajax_makia_remove_operator', array($this, 'remove_operator_ajax'));
        add_action('wp_ajax_makia_get_operators', array($this, 'get_operators_ajax'));
        
        // Modificar columnas en lista de usuarios
        add_filter('manage_users_columns', array($this, 'add_operator_column'));
        add_filter('manage_users_custom_column', array($this, 'show_operator_column'), 10, 3);
    }
    
    /**
     * Crear rol de operario
     */
    public function create_operator_role() {
        // Verificar si el rol ya existe
        if (get_role('makia_operator')) {
            return;
        }
        
        // Capacidades del operario
        $capabilities = array(
            'read' => true,
            'makia_manage_bookings' => true, // Capacidad base para acceder al menú
            'makia_view_bookings' => true,
            'makia_edit_bookings' => true,
            'makia_add_notes' => true,
            'makia_view_notes' => true,
            'makia_view_audit' => true,
            'upload_files' => true, // Por si necesitan subir imágenes en el futuro
        );
        
        // Crear el rol
        add_role(
            'makia_operator',
            'Operario MakIA',
            $capabilities
        );
        
        // Añadir capacidades a administrador
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $cap => $granted) {
                $admin_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Eliminar rol de operario (al desactivar plugin)
     */
    public static function remove_operator_role() {
        remove_role('makia_operator');
    }
    
    /**
     * Obtener todos los operarios
     */
    public static function get_operators() {
        $operators = get_users(array(
            'role' => 'makia_operator',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        return $operators;
    }
    
    /**
     * Añadir operario (promover usuario existente o crear nuevo)
     */
    public static function add_operator($user_id_or_email, $user_data = array()) {
        // Si es un ID, promover usuario existente
        if (is_numeric($user_id_or_email)) {
            $user = get_user_by('ID', $user_id_or_email);
            if (!$user) {
                return new WP_Error('user_not_found', 'Usuario no encontrado');
            }
            
            $user->set_role('makia_operator');
            
            // Registrar en auditoría
            MakIA_Audit::log_action(
                'operator_added',
                'user',
                $user->ID,
                array('user_login' => $user->user_login)
            );
            
            return $user->ID;
        }
        
        // Si es un email, crear nuevo usuario
        if (is_email($user_id_or_email)) {
            // Verificar si el email ya existe
            if (email_exists($user_id_or_email)) {
                return new WP_Error('email_exists', 'El email ya está registrado');
            }
            
            // Generar username desde el email
            $username = sanitize_user(current(explode('@', $user_id_or_email)));
            
            // Si el username existe, añadir número
            $original_username = $username;
            $counter = 1;
            while (username_exists($username)) {
                $username = $original_username . $counter;
                $counter++;
            }
            
            // Usar contraseña proporcionada o generar una aleatoria
            $password = isset($user_data['user_pass']) ? $user_data['user_pass'] : wp_generate_password(12, true, true);
            
            // Datos del usuario
            $userdata = array(
                'user_login' => $username,
                'user_email' => $user_id_or_email,
                'user_pass' => $password,
                'display_name' => isset($user_data['display_name']) ? $user_data['display_name'] : $username,
                'first_name' => isset($user_data['first_name']) ? $user_data['first_name'] : '',
                'last_name' => isset($user_data['last_name']) ? $user_data['last_name'] : '',
                'role' => 'makia_operator'
            );
            
            $user_id = wp_insert_user($userdata);
            
            if (is_wp_error($user_id)) {
                return $user_id;
            }
            
            // Enviar email de bienvenida
            wp_new_user_notification($user_id, null, 'both');
            
            // Registrar en auditoría
            MakIA_Audit::log_action(
                'operator_created',
                'user',
                $user_id,
                array(
                    'user_login' => $username,
                    'user_email' => $user_id_or_email
                )
            );
            
            return $user_id;
        }
        
        return new WP_Error('invalid_input', 'Debes proporcionar un ID de usuario o email válido');
    }
    
    /**
     * Eliminar operario (degradar a suscriptor)
     */
    public static function remove_operator($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return new WP_Error('user_not_found', 'Usuario no encontrado');
        }
        
        // No permitir eliminar administradores
        if (user_can($user_id, 'manage_options')) {
            return new WP_Error('cannot_remove_admin', 'No se puede eliminar el rol de un administrador');
        }
        
        // Cambiar a suscriptor
        $user->set_role('subscriber');
        
        // Registrar en auditoría
        MakIA_Audit::log_action(
            'operator_removed',
            'user',
            $user_id,
            array('user_login' => $user->user_login)
        );
        
        return true;
    }
    
    /**
     * Verificar si un usuario es operario
     */
    public static function is_operator($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'makia_manage_bookings');
    }
    
    /**
     * Obtener estadísticas de un operario
     */
    public static function get_operator_stats($user_id, $days = 30) {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'makia_audit_log';
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array();
        
        // Total de acciones
        $stats['total_actions'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$audit_table} WHERE user_id = %d AND created_at >= %s",
            $user_id,
            $date_from
        ));
        
        // Reservas creadas
        $stats['bookings_created'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$audit_table} 
             WHERE user_id = %d AND action_type = 'booking_created' AND created_at >= %s",
            $user_id,
            $date_from
        ));
        
        // Reservas actualizadas
        $stats['bookings_updated'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$audit_table} 
             WHERE user_id = %d AND action_type = 'booking_updated' AND created_at >= %s",
            $user_id,
            $date_from
        ));
        
        // Notas añadidas
        $stats['notes_added'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$audit_table} 
             WHERE user_id = %d AND action_type = 'note_added' AND created_at >= %s",
            $user_id,
            $date_from
        ));
        
        return $stats;
    }
    
    /**
     * Handler AJAX para añadir operario
     */
    public function add_operator_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            return;
        }
        
        $email = sanitize_email($_POST['email']);
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        if (strlen($password) < 8) {
            wp_send_json_error('La contraseña debe tener al menos 8 caracteres');
            return;
        }
        $display_name = sanitize_text_field($_POST['display_name']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        
        $user_data = array(
            'user_pass' => $password,
            'display_name' => $display_name,
            'first_name' => $first_name,
            'last_name' => $last_name
        );
        
        $result = self::add_operator($email, $user_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message' => 'Operario añadido correctamente',
                'user_id' => $result
            ));
        }
    }
    
    /**
     * Handler AJAX para eliminar operario
     */
    public function remove_operator_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        
        $result = self::remove_operator($user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('message' => 'Operario eliminado correctamente'));
        }
    }
    
    /**
     * Handler AJAX para obtener operarios
     */
    public function get_operators_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            return;
        }
        
        $operators = self::get_operators();
        
        $operators_data = array();
        foreach ($operators as $operator) {
            $stats = self::get_operator_stats($operator->ID);
            $operators_data[] = array(
                'ID' => $operator->ID,
                'display_name' => $operator->display_name,
                'user_email' => $operator->user_email,
                'user_registered' => $operator->user_registered,
                'stats' => $stats
            );
        }
        
        wp_send_json_success(array('operators' => $operators_data));
    }
    
    /**
     * Añadir columna en lista de usuarios
     */
    public function add_operator_column($columns) {
        $columns['makia_operator'] = 'Operario MakIA';
        return $columns;
    }
    
    /**
     * Mostrar contenido de columna
     */
    public function show_operator_column($value, $column_name, $user_id) {
        if ($column_name === 'makia_operator') {
            if (self::is_operator($user_id)) {
                return '<span style="background: #667eea; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">✓ OPERARIO</span>';
            }
        }
        return $value;
    }
    
    /**
     * Renderizar página de gestión de operarios
     */
    public static function render_operators_page() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para acceder a esta página');
        }
        
        $operators = self::get_operators();
        ?>
        
        <div class="wrap">
            <h1 style="display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 36px;">👥</span>
                Gestión de Operarios
                <button type="button" class="button button-primary" id="makia-add-operator-btn" style="background: #667eea; border-color: #667eea; margin-left: auto;">
                    ➕ Añadir Operario
                </button>
            </h1>
            
            <p style="font-size: 16px; color: #666; margin: 20px 0 30px 0;">
                Los operarios pueden gestionar reservas, añadir notas y ver la auditoría, pero no tienen acceso a la configuración del plugin.
            </p>
            
            <!-- Formulario para añadir operario -->
            <div id="makia-operator-form" style="display: none; background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 25px; margin-bottom: 30px;">
                <h2 style="margin: 0 0 20px 0; color: #667eea;">Nuevo Operario</h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Nombre *</label>
                        <input type="text" id="makia-operator-first-name" class="regular-text" placeholder="Nombre">
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Apellidos *</label>
                        <input type="text" id="makia-operator-last-name" class="regular-text" placeholder="Apellidos">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Email *</label>
                        <input type="email" id="makia-operator-email" class="regular-text" placeholder="operario@restaurante.com" style="width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Contraseña *</label>
                        <input type="password" id="makia-operator-password" class="regular-text" placeholder="Mínimo 8 caracteres" style="width: 100%;">
                    </div>
                </div>
                <p style="margin: -10px 0 20px 0; font-size: 13px; color: #666;">
                    El operario usará este email y contraseña para acceder al panel de reservas.
                </p>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="button button-primary" id="makia-save-operator-btn" style="background: #46b450; border-color: #46b450;">
                        💾 Crear Operario
                    </button>
                    <button type="button" class="button" id="makia-cancel-operator-btn">
                        ✖️ Cancelar
                    </button>
                </div>
            </div>
            
            <!-- Lista de operarios -->
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 25px;">
                <h2 style="margin: 0 0 20px 0; color: #333;">
                    Operarios Activos
                    <span style="background: #667eea; color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 16px; font-weight: 600; margin-left: 10px;"><?php echo count($operators); ?></span>
                </h2>
                
                <?php if (empty($operators)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #999;">
                        <div style="font-size: 64px; margin-bottom: 15px;">👥</div>
                        <p style="margin: 0; font-size: 18px;">No hay operarios todavía</p>
                        <p style="margin: 10px 0 0 0; font-size: 14px;">Haz clic en "Añadir Operario" para crear el primero</p>
                    </div>
                <?php else: ?>
                    <div id="makia-operators-list" style="display: grid; gap: 15px;">
                        <?php foreach ($operators as $operator): 
                            $stats = self::get_operator_stats($operator->ID);
                        ?>
                            <div class="makia-operator-card" data-user-id="<?php echo esc_attr($operator->ID); ?>" style="background: #f9f9f9; border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px; transition: all 0.3s;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                                            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24px; font-weight: 700;">
                                                <?php echo strtoupper(substr($operator->display_name, 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h3 style="margin: 0; font-size: 18px; color: #333;"><?php echo esc_html($operator->display_name); ?></h3>
                                                <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">📧 <?php echo esc_html($operator->user_email); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; gap: 20px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                                            <div>
                                                <div style="font-size: 12px; color: #999; margin-bottom: 4px;">Acciones (30 días)</div>
                                                <div style="font-size: 24px; font-weight: 700; color: #667eea;"><?php echo $stats['total_actions']; ?></div>
                                            </div>
                                            <div>
                                                <div style="font-size: 12px; color: #999; margin-bottom: 4px;">Reservas creadas</div>
                                                <div style="font-size: 24px; font-weight: 700; color: #46b450;"><?php echo $stats['bookings_created']; ?></div>
                                            </div>
                                            <div>
                                                <div style="font-size: 12px; color: #999; margin-bottom: 4px;">Notas añadidas</div>
                                                <div style="font-size: 24px; font-weight: 700; color: #2271b1;"><?php echo $stats['notes_added']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <span style="background: #46b450; color: #fff; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-align: center;">✓ ACTIVO</span>
                                        <button type="button" class="button makia-remove-operator" data-user-id="<?php echo esc_attr($operator->ID); ?>" style="background: #d63638; color: #fff; border-color: #d63638;">
                                            🗑️ Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Mostrar formulario
            $('#makia-add-operator-btn').on('click', function() {
                $('#makia-operator-form').slideDown(300);
                $('#makia-operator-first-name').focus();
            });
            
            // Ocultar formulario
            $('#makia-cancel-operator-btn').on('click', function() {
                $('#makia-operator-form').slideUp(300);
                clearForm();
            });
            
            // Guardar operario
            $('#makia-save-operator-btn').on('click', function() {
                var $btn = $(this);
                var firstName = $('#makia-operator-first-name').val().trim();
                var lastName = $('#makia-operator-last-name').val().trim();
                var email = $('#makia-operator-email').val().trim();
                var password = $('#makia-operator-password').val().trim();
                
                if (!firstName || !lastName || !email || !password) {
                    alert('Por favor, completa todos los campos');
                    return;
                }
                
                if (!isValidEmail(email)) {
                    alert('Por favor, ingresa un email válido');
                    return;
                }
                
                if (password.length < 8) {
                    alert('La contraseña debe tener al menos 8 caracteres');
                    return;
                }
                
                $btn.prop('disabled', true).text('Creando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'makia_add_operator',
                        email: email,
                        password: password,
                        display_name: firstName + ' ' + lastName,
                        first_name: firstName,
                        last_name: lastName,
                        nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('Operario creado correctamente. Se ha enviado un email con las credenciales.', 'success');
                            $('#makia-operator-form').slideUp(300);
                            clearForm();
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Error al crear el operario');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('💾 Crear Operario');
                    }
                });
            });
            
            // Eliminar operario
            $(document).on('click', '.makia-remove-operator', function() {
                if (!confirm('¿Estás seguro de eliminar este operario?\n\nLa cuenta de usuario permanecerá, pero perderá los permisos de operario.')) {
                    return;
                }
                
                var userId = $(this).data('user-id');
                var $card = $(this).closest('.makia-operator-card');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'makia_remove_operator',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $card.fadeOut(300, function() {
                                $(this).remove();
                                if ($('.makia-operator-card').length === 0) {
                                    location.reload();
                                }
                            });
                            showNotification('Operario eliminado correctamente', 'success');
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Error al eliminar el operario');
                    }
                });
            });
            
            // Hover effect en cards
            $('.makia-operator-card').hover(
                function() {
                    $(this).css({
                        'border-color': '#667eea',
                        'transform': 'translateY(-3px)',
                        'box-shadow': '0 6px 20px rgba(0,0,0,0.1)'
                    });
                },
                function() {
                    $(this).css({
                        'border-color': '#e0e0e0',
                        'transform': 'translateY(0)',
                        'box-shadow': 'none'
                    });
                }
            );
            
            function clearForm() {
                $('#makia-operator-first-name').val('');
                $('#makia-operator-last-name').val('');
                $('#makia-operator-email').val('');
                $('#makia-operator-password').val('');
            }
            
            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }
            
            function showNotification(message, type) {
                var bgColor = type === 'success' ? '#46b450' : '#dc3232';
                var $notification = $('<div>')
                    .css({
                        'position': 'fixed',
                        'top': '20px',
                        'right': '20px',
                        'background': bgColor,
                        'color': '#fff',
                        'padding': '15px 25px',
                        'border-radius': '8px',
                        'box-shadow': '0 4px 15px rgba(0,0,0,0.2)',
                        'z-index': '999999',
                        'font-weight': '600',
                        'max-width': '400px'
                    })
                    .text(message)
                    .appendTo('body');
                
                setTimeout(function() {
                    $notification.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        });
        </script>
        
        <style>
        .makia-operator-card {
            transition: all 0.3s ease !important;
        }
        </style>
        
        <?php
    }
}

// Inicializar
new MakIA_Operators();
