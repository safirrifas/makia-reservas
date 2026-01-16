<?php
/**
 * UI de Configuración de Capacidad por Franjas Horarias
 */

class MakIA_Capacity_UI {
    
    public static function render_page() {
        global $wpdb, $makia_capacity;
        
        // Obtener configuración actual
        $enable_restrictions = $makia_capacity->get_config('enable_capacity_restrictions', '0');
        $default_block_length = $makia_capacity->get_config('default_dining_block_length', '120');
        
        // Guardar configuración si se envió el formulario
        if (isset($_POST['save_capacity_config']) && check_admin_referer('makia_capacity_config', 'makia_capacity_nonce')) {
            $enable = isset($_POST['enable_capacity_restrictions']) ? '1' : '0';
            $default_block = intval($_POST['default_dining_block_length']);
            
            $makia_capacity->save_config('enable_capacity_restrictions', $enable);
            $makia_capacity->save_config('default_dining_block_length', $default_block);
            
            echo '<div class="notice notice-success"><p>✅ Configuración guardada correctamente</p></div>';
            
            $enable_restrictions = $enable;
            $default_block_length = $default_block;
        }
        
        ?>
        <div style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px;">
            <h2 style="margin: 0 0 10px 0; color: #667eea;">🕐 Gestión de Capacidad por Franjas Horarias</h2>
            <p style="color: #666; margin: 0 0 20px 0;">
                Controla el número máximo de reservas o personas por franja horaria. Similar a Five Star Restaurant Reservations.
            </p>
            
            <!-- Configuración Global -->
            <form method="post" action="" style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <?php wp_nonce_field('makia_capacity_config', 'makia_capacity_nonce'); ?>
                
                <h3 style="margin: 0 0 15px 0; color: #667eea; font-size: 16px;">⚙️ Configuración Global</h3>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="enable_capacity_restrictions" value="1" <?php checked($enable_restrictions, '1'); ?> style="width: 20px; height: 20px;">
                        <span style="font-weight: 600; color: #333;">Habilitar restricciones de capacidad</span>
                    </label>
                    <p style="margin: 5px 0 0 30px; color: #666; font-size: 13px;">
                        Cuando está habilitado, el sistema validará automáticamente la capacidad disponible al crear reservas.
                    </p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                        Duración del bloque de tiempo por defecto (minutos)
                    </label>
                    <input type="number" name="default_dining_block_length" value="<?php echo esc_attr($default_block_length); ?>" min="30" max="300" step="15" style="width: 150px; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                        ℹ️ Tiempo promedio que dura una reserva. Ejemplo: 120 minutos = 2 horas.
                    </p>
                </div>
                
                <button type="submit" name="save_capacity_config" class="button button-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 10px 20px;">
                    💾 Guardar Configuración
                </button>
            </form>
            
            <!-- Franjas Horarias -->
            <div id="makia-time-slots-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; color: #667eea; font-size: 16px;">📋 Franjas Horarias</h3>
                    <button type="button" id="add-time-slot" class="button button-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        ➕ Agregar Franja Horaria
                    </button>
                </div>
                
                <!-- Lista de franjas horarias -->
                <div id="time-slots-list" style="display: grid; gap: 15px;">
                    <!-- Se cargará vía AJAX -->
                </div>
            </div>
        </div>
        
        <!-- Modal para agregar/editar franja horaria -->
        <div id="time-slot-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000; align-items: center; justify-content: center;">
            <div style="background: #fff; padding: 30px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <h2 style="margin: 0 0 20px 0; color: #667eea;" id="modal-title">Agregar Franja Horaria</h2>
                
                <form id="time-slot-form">
                    <input type="hidden" id="slot-id" value="0">
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Nombre</label>
                        <input type="text" id="slot-name" required placeholder="Ej: Almuerzo, Cena" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Día de la semana</label>
                        <select id="slot-day" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                            <option value="all">Todos los días</option>
                            <option value="monday">Lunes</option>
                            <option value="tuesday">Martes</option>
                            <option value="wednesday">Miércoles</option>
                            <option value="thursday">Jueves</option>
                            <option value="friday">Viernes</option>
                            <option value="saturday">Sábado</option>
                            <option value="sunday">Domingo</option>
                        </select>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Hora inicio</label>
                            <input type="time" id="slot-start-time" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Hora fin</label>
                            <input type="time" id="slot-end-time" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Duración del bloque (minutos)</label>
                        <input type="number" id="slot-block-length" value="120" min="30" max="300" step="15" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Tiempo que ocupa cada reserva</p>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Máximo de reservas</label>
                            <input type="number" id="slot-max-reservations" placeholder="Sin límite" min="1" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Dejar vacío = sin límite</p>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Máximo de personas</label>
                            <input type="number" id="slot-max-people" placeholder="Sin límite" min="1" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Dejar vacío = sin límite</p>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="slot-is-active" checked style="width: 20px; height: 20px;">
                            <span style="font-weight: 600; color: #333;">Activa</span>
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="cancel-slot" class="button">Cancelar</button>
                        <button type="submit" class="button button-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                            💾 Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Cargar franjas horarias
            function loadTimeSlots() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'makia_get_time_slots',
                        nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            renderTimeSlots(response.data);
                        }
                    }
                });
            }
            
            // Renderizar franjas horarias
            function renderTimeSlots(slots) {
                var html = '';
                
                if (slots.length === 0) {
                    html = '<p style="text-align: center; color: #666; padding: 40px;">No hay franjas horarias configuradas. Haz click en "Agregar Franja Horaria" para crear una.</p>';
                } else {
                    slots.forEach(function(slot) {
                        var dayLabel = {
                            'all': 'Todos los días',
                            'monday': 'Lunes',
                            'tuesday': 'Martes',
                            'wednesday': 'Miércoles',
                            'thursday': 'Jueves',
                            'friday': 'Viernes',
                            'saturday': 'Sábado',
                            'sunday': 'Domingo'
                        }[slot.day_of_week] || slot.day_of_week;
                        
                        var maxReservations = slot.max_reservations ? slot.max_reservations + ' reservas' : 'Sin límite';
                        var maxPeople = slot.max_people ? slot.max_people + ' personas' : 'Sin límite';
                        var statusBadge = slot.is_active == 1 ? '<span style="background: #46b450; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 12px;">✓ Activa</span>' : '<span style="background: #dc3232; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 12px;">✗ Inactiva</span>';
                        
                        html += '<div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea;">';
                        html += '<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">';
                        html += '<div>';
                        html += '<h4 style="margin: 0 0 5px 0; color: #667eea; font-size: 16px;">' + slot.name + ' ' + statusBadge + '</h4>';
                        html += '<p style="margin: 0; color: #666; font-size: 14px;">' + dayLabel + ' • ' + slot.start_time.substring(0, 5) + ' - ' + slot.end_time.substring(0, 5) + '</p>';
                        html += '</div>';
                        html += '<div style="display: flex; gap: 10px;">';
                        html += '<button class="button edit-slot" data-id="' + slot.id + '">✏️ Editar</button>';
                        html += '<button class="button delete-slot" data-id="' + slot.id + '" style="color: #dc3232;">🗑️ Eliminar</button>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-top: 10px;">';
                        html += '<div style="background: #fff; padding: 10px; border-radius: 4px;">';
                        html += '<p style="margin: 0; color: #666; font-size: 12px;">Duración bloque</p>';
                        html += '<p style="margin: 5px 0 0 0; font-weight: 600; color: #333;">' + slot.dining_block_length + ' min</p>';
                        html += '</div>';
                        html += '<div style="background: #fff; padding: 10px; border-radius: 4px;">';
                        html += '<p style="margin: 0; color: #666; font-size: 12px;">Máx. reservas</p>';
                        html += '<p style="margin: 5px 0 0 0; font-weight: 600; color: #333;">' + maxReservations + '</p>';
                        html += '</div>';
                        html += '<div style="background: #fff; padding: 10px; border-radius: 4px;">';
                        html += '<p style="margin: 0; color: #666; font-size: 12px;">Máx. personas</p>';
                        html += '<p style="margin: 5px 0 0 0; font-weight: 600; color: #333;">' + maxPeople + '</p>';
                        html += '</div>';
                        html += '</div>';
                        html += '</div>';
                    });
                }
                
                $('#time-slots-list').html(html);
            }
            
            // Abrir modal para agregar
            $('#add-time-slot').on('click', function() {
                $('#modal-title').text('Agregar Franja Horaria');
                $('#time-slot-form')[0].reset();
                $('#slot-id').val('0');
                $('#slot-is-active').prop('checked', true);
                $('#slot-block-length').val('120');
                $('#time-slot-modal').css('display', 'flex');
            });
            
            // Abrir modal para editar
            $(document).on('click', '.edit-slot', function() {
                var id = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'makia_get_time_slots',
                        nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var slot = response.data.find(function(s) { return s.id == id; });
                            if (slot) {
                                $('#modal-title').text('Editar Franja Horaria');
                                $('#slot-id').val(slot.id);
                                $('#slot-name').val(slot.name);
                                $('#slot-day').val(slot.day_of_week);
                                $('#slot-start-time').val(slot.start_time);
                                $('#slot-end-time').val(slot.end_time);
                                $('#slot-block-length').val(slot.dining_block_length);
                                $('#slot-max-reservations').val(slot.max_reservations || '');
                                $('#slot-max-people').val(slot.max_people || '');
                                $('#slot-is-active').prop('checked', slot.is_active == 1);
                                $('#time-slot-modal').css('display', 'flex');
                            }
                        }
                    }
                });
            });
            
            // Cerrar modal
            $('#cancel-slot, #time-slot-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#time-slot-modal').hide();
                }
            });
            
            // Guardar franja horaria
            $('#time-slot-form').on('submit', function(e) {
                e.preventDefault();
                
                var data = {
                    action: 'makia_save_time_slot',
                    nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>',
                    id: $('#slot-id').val(),
                    name: $('#slot-name').val(),
                    day_of_week: $('#slot-day').val(),
                    start_time: $('#slot-start-time').val(),
                    end_time: $('#slot-end-time').val(),
                    dining_block_length: $('#slot-block-length').val(),
                    max_reservations: $('#slot-max-reservations').val(),
                    max_people: $('#slot-max-people').val(),
                    is_active: $('#slot-is-active').is(':checked') ? 1 : 0
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            $('#time-slot-modal').hide();
                            loadTimeSlots();
                            alert('✅ ' + response.data.message);
                        } else {
                            alert('❌ ' + response.data);
                        }
                    }
                });
            });
            
            // Eliminar franja horaria
            $(document).on('click', '.delete-slot', function() {
                if (!confirm('¿Estás seguro de eliminar esta franja horaria?')) {
                    return;
                }
                
                var id = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'makia_delete_time_slot',
                        nonce: '<?php echo wp_create_nonce('makia_admin_nonce'); ?>',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            loadTimeSlots();
                            alert('✅ ' + response.data);
                        } else {
                            alert('❌ ' + response.data);
                        }
                    }
                });
            });
            
            // Cargar franjas al inicio
            loadTimeSlots();
        });
        </script>
        <?php
    }
}
