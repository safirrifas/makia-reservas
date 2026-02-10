<?php
if (!defined('ABSPATH')) { exit; }
/**
 * MakIA Notes Manager
 * Sistema de notas internas para reservas
 */

class MakIA_Notes {
    
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'makia_notes';

        // Crear tabla si no existe (con verificación de versión)
        $this->maybe_create_table();

        // AJAX para gestión de notas
        add_action('wp_ajax_makia_add_note', array($this, 'add_note_ajax'));
        add_action('wp_ajax_makia_get_notes', array($this, 'get_notes_ajax'));
        add_action('wp_ajax_makia_delete_note', array($this, 'delete_note_ajax'));
    }

    /**
     * Crear tabla solo si la versión ha cambiado
     */
    private function maybe_create_table() {
        if (get_option('makia_notes_db_version') !== MAKIA_VERSION) {
            $this->create_table();
            update_option('makia_notes_db_version', MAKIA_VERSION);
        }
    }
    
    /**
     * Crear tabla de notas
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            note_text text NOT NULL,
            note_type varchar(50) DEFAULT 'general',
            is_important tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Añadir nota a una reserva
     */
    public static function add_note($booking_id, $note_text, $note_type = 'general', $is_important = false) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'makia_notes';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'booking_id' => $booking_id,
                'user_id' => get_current_user_id(),
                'note_text' => sanitize_textarea_field($note_text),
                'note_type' => sanitize_text_field($note_type),
                'is_important' => $is_important ? 1 : 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s')
        );
        
        if ($result) {
            // Registrar en auditoría
            MakIA_Audit::log_action(
                'note_added',
                'note',
                $wpdb->insert_id,
                array(
                    'booking_id' => $booking_id,
                    'note_type' => $note_type,
                    'is_important' => $is_important
                )
            );
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Obtener notas de una reserva
     */
    public static function get_notes($booking_id, $limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'makia_notes';
        
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as author_name, u.user_email as author_email
             FROM {$table_name} n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE n.booking_id = %d
             ORDER BY n.created_at DESC
             LIMIT %d",
            $booking_id,
            $limit
        ));
        
        return $notes;
    }
    
    /**
     * Eliminar nota
     */
    public static function delete_note($note_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'makia_notes';
        
        // Obtener info de la nota antes de eliminar
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $note_id
        ));
        
        if (!$note) {
            return false;
        }
        
        // Solo el autor o un admin puede eliminar
        $current_user_id = get_current_user_id();
        if ($note->user_id != $current_user_id && !current_user_can('manage_options')) {
            return false;
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $note_id),
            array('%d')
        );
        
        if ($result) {
            // Registrar en auditoría
            MakIA_Audit::log_action(
                'note_deleted',
                'note',
                $note_id,
                array(
                    'booking_id' => $note->booking_id,
                    'note_text' => substr($note->note_text, 0, 50) . '...'
                )
            );
        }
        
        return $result;
    }
    
    /**
     * Contar notas de una reserva
     */
    public static function count_notes($booking_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'makia_notes';
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE booking_id = %d",
            $booking_id
        ));
    }
    
    /**
     * Handler AJAX para añadir nota
     */
    public function add_note_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('makia_manage_bookings')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        $note_text = sanitize_textarea_field($_POST['note_text']);
        $note_type = isset($_POST['note_type']) ? sanitize_text_field($_POST['note_type']) : 'general';
        $is_important = isset($_POST['is_important']) ? (bool) $_POST['is_important'] : false;
        
        if (empty($note_text)) {
            wp_send_json_error(array('message' => 'La nota no puede estar vacía'));
            return;
        }
        
        $note_id = self::add_note($booking_id, $note_text, $note_type, $is_important);
        
        if ($note_id) {
            $notes = self::get_notes($booking_id);
            wp_send_json_success(array(
                'message' => 'Nota añadida correctamente',
                'note_id' => $note_id,
                'notes' => $notes
            ));
        } else {
            wp_send_json_error(array('message' => 'Error al añadir la nota'));
        }
    }
    
    /**
     * Handler AJAX para obtener notas
     */
    public function get_notes_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('makia_manage_bookings')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        $notes = self::get_notes($booking_id);
        
        wp_send_json_success(array('notes' => $notes));
    }
    
    /**
     * Handler AJAX para eliminar nota
     */
    public function delete_note_ajax() {
        check_ajax_referer('makia_admin_nonce', 'nonce');
        
        if (!current_user_can('makia_manage_bookings')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            return;
        }
        
        $note_id = intval($_POST['note_id']);
        
        if (self::delete_note($note_id)) {
            wp_send_json_success(array('message' => 'Nota eliminada correctamente'));
        } else {
            wp_send_json_error(array('message' => 'Error al eliminar la nota'));
        }
    }
    
    /**
     * Renderizar widget de notas en el detalle de reserva
     */
    public static function render_notes_widget($booking_id) {
        $notes = self::get_notes($booking_id);
        $count = count($notes);
        ?>
        
        <div class="makia-notes-widget" style="margin-top: 20px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px;">
            <div class="makia-notes-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                <h3 style="margin: 0; color: #667eea; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 22px;">📝</span>
                    Notas Internas
                    <span class="makia-notes-count" style="background: #667eea; color: #fff; padding: 2px 10px; border-radius: 12px; font-size: 14px; font-weight: 600;"><?php echo $count; ?></span>
                </h3>
                <button type="button" class="button button-primary" id="makia-add-note-btn" style="background: #667eea; border-color: #667eea;">
                    ➕ Añadir Nota
                </button>
            </div>
            
            <!-- Formulario de nueva nota -->
            <div id="makia-note-form" style="display: none; background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Tipo de Nota</label>
                    <select id="makia-note-type" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="general">📌 General</option>
                        <option value="important">⚠️ Importante</option>
                        <option value="reminder">🔔 Recordatorio</option>
                        <option value="customer">👤 Info Cliente</option>
                        <option value="kitchen">🍳 Cocina</option>
                        <option value="service">🍽️ Servicio</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Texto de la Nota</label>
                    <textarea id="makia-note-text" rows="4" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; resize: vertical;" placeholder="Escribe tu nota aquí..."></textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="makia-note-important" style="width: 18px; height: 18px;">
                        <span style="font-weight: 600; color: #d63638;">Marcar como importante</span>
                    </label>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="button button-primary" id="makia-save-note-btn" style="background: #46b450; border-color: #46b450;">
                        💾 Guardar Nota
                    </button>
                    <button type="button" class="button" id="makia-cancel-note-btn">
                        ✖️ Cancelar
                    </button>
                </div>
            </div>
            
            <!-- Lista de notas -->
            <div id="makia-notes-list" class="makia-notes-list">
                <?php if (empty($notes)): ?>
                    <div class="makia-no-notes" style="text-align: center; padding: 40px 20px; color: #999;">
                        <div style="font-size: 48px; margin-bottom: 10px;">📋</div>
                        <p style="margin: 0; font-size: 16px;">No hay notas todavía</p>
                        <p style="margin: 5px 0 0 0; font-size: 14px;">Haz clic en "Añadir Nota" para crear la primera</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                        <?php self::render_note_item($note); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var bookingId = <?php echo intval($booking_id); ?>;
            
            // Mostrar formulario
            $('#makia-add-note-btn').on('click', function() {
                $('#makia-note-form').slideDown(300);
                $('#makia-note-text').focus();
            });
            
            // Ocultar formulario
            $('#makia-cancel-note-btn').on('click', function() {
                $('#makia-note-form').slideUp(300);
                $('#makia-note-text').val('');
                $('#makia-note-type').val('general');
                $('#makia-note-important').prop('checked', false);
            });
            
            // Guardar nota
            $('#makia-save-note-btn').on('click', function() {
                var $btn = $(this);
                var noteText = $('#makia-note-text').val().trim();
                var noteType = $('#makia-note-type').val();
                var isImportant = $('#makia-note-important').is(':checked');
                
                if (!noteText) {
                    alert('Por favor, escribe una nota');
                    return;
                }
                
                $btn.prop('disabled', true).text('Guardando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'makia_add_note',
                        booking_id: bookingId,
                        note_text: noteText,
                        note_type: noteType,
                        is_important: isImportant,
                        nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Limpiar formulario
                            $('#makia-note-text').val('');
                            $('#makia-note-type').val('general');
                            $('#makia-note-important').prop('checked', false);
                            $('#makia-note-form').slideUp(300);
                            
                            // Recargar notas
                            loadNotes();
                            
                            // Mostrar mensaje
                            showNotification('Nota añadida correctamente', 'success');
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Error al guardar la nota');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('💾 Guardar Nota');
                    }
                });
            });
            
            // Eliminar nota
            $(document).on('click', '.makia-delete-note', function() {
                if (!confirm('¿Estás seguro de eliminar esta nota?')) {
                    return;
                }
                
                var noteId = $(this).data('note-id');
                var $noteItem = $(this).closest('.makia-note-item');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'makia_delete_note',
                        note_id: noteId,
                        nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $noteItem.fadeOut(300, function() {
                                $(this).remove();
                                updateNotesCount();
                                
                                // Si no hay más notas, mostrar mensaje
                                if ($('.makia-note-item').length === 0) {
                                    $('#makia-notes-list').html('<div class="makia-no-notes" style="text-align: center; padding: 40px 20px; color: #999;"><div style="font-size: 48px; margin-bottom: 10px;">📋</div><p style="margin: 0; font-size: 16px;">No hay notas todavía</p></div>');
                                }
                            });
                            showNotification('Nota eliminada correctamente', 'success');
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Error al eliminar la nota');
                    }
                });
            });
            
            // Cargar notas
            function loadNotes() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'makia_get_notes',
                        booking_id: bookingId,
                        nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            renderNotes(response.data.notes);
                        }
                    }
                });
            }
            
            // Renderizar notas
            function renderNotes(notes) {
                if (notes.length === 0) {
                    $('#makia-notes-list').html('<div class="makia-no-notes" style="text-align: center; padding: 40px 20px; color: #999;"><div style="font-size: 48px; margin-bottom: 10px;">📋</div><p style="margin: 0;">No hay notas todavía</p></div>');
                    updateNotesCount();
                    return;
                }
                
                var html = '';
                notes.forEach(function(note) {
                    html += renderNoteHtml(note);
                });
                
                $('#makia-notes-list').html(html);
                updateNotesCount();
            }
            
            // Renderizar HTML de una nota
            function renderNoteHtml(note) {
                var typeIcons = {
                    'general': '📌',
                    'important': '⚠️',
                    'reminder': '🔔',
                    'customer': '👤',
                    'kitchen': '🍳',
                    'service': '🍽️'
                };
                
                var icon = typeIcons[note.note_type] || '📌';
                var importantClass = note.is_important == 1 ? ' makia-note-important' : '';
                var importantBadge = note.is_important == 1 ? '<span style="background: #d63638; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">IMPORTANTE</span>' : '';
                
                return '<div class="makia-note-item' + importantClass + '" data-note-id="' + note.id + '" style="background: ' + (note.is_important == 1 ? '#fff4f4' : '#fff') + '; border: 1px solid ' + (note.is_important == 1 ? '#d63638' : '#e0e0e0') + '; border-left: 4px solid ' + (note.is_important == 1 ? '#d63638' : '#667eea') + '; border-radius: 8px; padding: 15px; margin-bottom: 12px;">' +
                    '<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">' +
                        '<div style="display: flex; align-items: center; gap: 8px;">' +
                            '<span style="font-size: 20px;">' + icon + '</span>' +
                            '<strong style="color: #333;">' + escapeHtml(note.author_name) + '</strong>' +
                            importantBadge +
                        '</div>' +
                        '<button type="button" class="makia-delete-note" data-note-id="' + note.id + '" style="background: none; border: none; color: #d63638; cursor: pointer; font-size: 18px; padding: 0; line-height: 1;" title="Eliminar nota">🗑️</button>' +
                    '</div>' +
                    '<p style="margin: 0 0 10px 0; color: #555; line-height: 1.6; white-space: pre-wrap;">' + escapeHtml(note.note_text) + '</p>' +
                    '<div style="font-size: 12px; color: #999;">' +
                        '<span>📅 ' + formatDate(note.created_at) + '</span>' +
                    '</div>' +
                '</div>';
            }
            
            // Actualizar contador
            function updateNotesCount() {
                var count = $('.makia-note-item').length;
                $('.makia-notes-count').text(count);
            }
            
            // Formatear fecha
            function formatDate(dateString) {
                var date = new Date(dateString);
                return date.toLocaleString('es-ES', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            // Escape HTML
            function escapeHtml(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }
            
            // Sistema de notificaciones
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
                        'font-weight': '600'
                    })
                    .text(message)
                    .appendTo('body');
                
                setTimeout(function() {
                    $notification.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        });
        </script>
        
        <style>
        .makia-note-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transform: translateY(-1px);
            transition: all 0.2s ease;
        }
        
        .makia-delete-note:hover {
            transform: scale(1.2);
            transition: transform 0.2s ease;
        }
        </style>
        
        <?php
    }
    
    /**
     * Renderizar item de nota individual
     */
    private static function render_note_item($note) {
        $type_icons = array(
            'general' => '📌',
            'important' => '⚠️',
            'reminder' => '🔔',
            'customer' => '👤',
            'kitchen' => '🍳',
            'service' => '🍽️'
        );
        
        $icon = isset($type_icons[$note->note_type]) ? $type_icons[$note->note_type] : '📌';
        $important_class = $note->is_important ? ' makia-note-important' : '';
        $bg_color = $note->is_important ? '#fff4f4' : '#fff';
        $border_color = $note->is_important ? '#d63638' : '#e0e0e0';
        $border_left = $note->is_important ? '#d63638' : '#667eea';
        ?>
        
        <div class="makia-note-item<?php echo $important_class; ?>" data-note-id="<?php echo esc_attr($note->id); ?>" style="background: <?php echo $bg_color; ?>; border: 1px solid <?php echo $border_color; ?>; border-left: 4px solid <?php echo $border_left; ?>; border-radius: 8px; padding: 15px; margin-bottom: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;"><?php echo $icon; ?></span>
                    <strong style="color: #333;"><?php echo esc_html($note->author_name); ?></strong>
                    <?php if ($note->is_important): ?>
                        <span style="background: #d63638; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">IMPORTANTE</span>
                    <?php endif; ?>
                </div>
                <button type="button" class="makia-delete-note" data-note-id="<?php echo esc_attr($note->id); ?>" style="background: none; border: none; color: #d63638; cursor: pointer; font-size: 18px; padding: 0; line-height: 1;" title="Eliminar nota">🗑️</button>
            </div>
            <p style="margin: 0 0 10px 0; color: #555; line-height: 1.6; white-space: pre-wrap;"><?php echo esc_html($note->note_text); ?></p>
            <div style="font-size: 12px; color: #999;">
                <span>📅 <?php echo date('d/m/Y H:i', strtotime($note->created_at)); ?></span>
            </div>
        </div>
        
        <?php
    }
}

// Inicializar
new MakIA_Notes();
