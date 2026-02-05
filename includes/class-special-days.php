<?php
/**
 * Gestión de Días Especiales
 * Permite marcar días como cerrados o con horarios especiales
 */

class MakIA_Special_Days {
    
    public function __construct() {
        add_action('admin_post_makia_save_special_day', array($this, 'save_special_day'));
        add_action('admin_post_makia_delete_special_day', array($this, 'delete_special_day'));
    }
    
    /**
     * Renderizar página de días especiales
     */
    public static function render_page() {
        $special_days = get_option('makia_special_days', array());
        
        // Ordenar por fecha
        usort($special_days, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        ?>
        <div class="wrap">
            <h1>Días Especiales</h1>
            <p>Gestiona días con horarios especiales o cerrados (festivos, eventos privados, etc.)</p>
            
            <?php settings_errors('makia_special_days'); ?>
            
            <!-- Formulario para agregar día especial -->
            <div class="card" style="max-width: 600px; margin-bottom: 20px;">
                <h2>Agregar Día Especial</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="makia_save_special_day">
                    <?php wp_nonce_field('makia_special_day_action', 'makia_special_day_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="special_date">Fecha</label></th>
                            <td>
                                <input type="date" name="special_date" id="special_date" required min="<?php echo date('Y-m-d'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="special_type">Tipo</label></th>
                            <td>
                                <select name="special_type" id="special_type" required onchange="toggleHoursFields(this.value)">
                                    <option value="">Selecciona...</option>
                                    <option value="closed">⛔ Cerrado</option>
                                    <option value="full">📅 Completo (sin disponibilidad)</option>
                                    <option value="holiday">🎉 Festivo</option>
                                    <option value="special_hours">🕐 Horario Especial</option>
                                    <option value="exceptional_opening">✅ Apertura Excepcional (día normalmente cerrado)</option>
                                </select>
                            </td>
                        </tr>
                        <tr id="hours_row" style="display: none;">
                            <th scope="row"><label>Horarios</label></th>
                            <td>
                                <div style="margin-bottom: 10px;">
                                    <label>Apertura: <input type="time" name="special_start" id="special_start"></label>
                                </div>
                                <div>
                                    <label>Cierre: <input type="time" name="special_end" id="special_end"></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="special_reason">Motivo</label></th>
                            <td>
                                <input type="text" name="special_reason" id="special_reason" class="regular-text" placeholder="Ej: Navidad, Evento privado, etc.">
                                <p class="description">Opcional: Describe el motivo del día especial</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Agregar Día Especial'); ?>
                </form>
            </div>
            
            <script>
            function toggleHoursFields(type) {
                const hoursRow = document.getElementById('hours_row');
                const startInput = document.getElementById('special_start');
                const endInput = document.getElementById('special_end');
                
                if (type === 'special_hours' || type === 'exceptional_opening') {
                    hoursRow.style.display = 'table-row';
                    startInput.required = true;
                    endInput.required = true;
                } else {
                    hoursRow.style.display = 'none';
                    startInput.required = false;
                    endInput.required = false;
                }
            }
            </script>
            
            <!-- Lista de días especiales -->
            <h2>Días Especiales Configurados</h2>
            
            <?php if (empty($special_days)): ?>
                <p>No hay días especiales configurados.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Día de la Semana</th>
                            <th>Tipo</th>
                            <th>Horario</th>
                            <th>Motivo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($special_days as $index => $day): ?>
                            <?php
                            $date = new DateTime($day['date']);
                            $day_name = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado')[$date->format('w')];
                            $formatted_date = $date->format('d/m/Y');
                            $is_past = $date < new DateTime();
                            ?>
                            <tr <?php echo $is_past ? 'style="opacity: 0.5;"' : ''; ?>>
                                <td data-label="Fecha"><strong><?php echo esc_html($formatted_date); ?></strong></td>
                                <td data-label="Día"><?php echo esc_html($day_name); ?></td>
                                <td data-label="Tipo">
                                    <?php if ($day['type'] === 'closed'): ?>
                                        <span style="color: #d63638;">⛔ Cerrado</span>
                                    <?php elseif ($day['type'] === 'full'): ?>
                                        <span style="color: #f0b849;">📅 Completo</span>
                                    <?php elseif ($day['type'] === 'holiday'): ?>
                                        <span style="color: #3498db;">🎉 Festivo</span>
                                    <?php elseif ($day['type'] === 'exceptional_opening'): ?>
                                        <span style="color: #00a32a;">✅ Apertura Excepcional</span>
                                    <?php else: ?>
                                        <span style="color: #2271b1;">🕐 Horario Especial</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Horario">
                                    <?php if (($day['type'] === 'special_hours' || $day['type'] === 'exceptional_opening') && isset($day['start']) && isset($day['end'])): ?>
                                        <?php echo esc_html($day['start']) . ' - ' . esc_html($day['end']); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td data-label="Motivo"><?php echo esc_html($day['reason'] ?? '—'); ?></td>
                                <td data-label="Acciones">
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                        <input type="hidden" name="action" value="makia_delete_special_day">
                                        <input type="hidden" name="day_index" value="<?php echo $index; ?>">
                                        <?php wp_nonce_field('makia_delete_special_day_action', 'makia_delete_special_day_nonce'); ?>
                                        <button type="submit" class="button button-small" onclick="return confirm('¿Eliminar este día especial?')">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Guardar día especial
     */
    public function save_special_day() {
        // Verificar nonce
        if (!isset($_POST['makia_special_day_nonce']) || !wp_verify_nonce($_POST['makia_special_day_nonce'], 'makia_special_day_action')) {
            wp_die('Acción no autorizada');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        // Obtener datos
        $date = sanitize_text_field($_POST['special_date']);
        $type = sanitize_text_field($_POST['special_type']);
        $reason = sanitize_text_field($_POST['special_reason'] ?? '');
        
        // Validar fecha
        if (strtotime($date) < strtotime('today')) {
            add_settings_error('makia_special_days', 'invalid_date', 'No puedes agregar días en el pasado', 'error');
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_redirect(admin_url('admin.php?page=makia-special-days'));
            exit;
        }
        
        // Crear día especial
        $special_day = array(
            'date' => $date,
            'type' => $type,
            'reason' => $reason
        );
        
        // Agregar horarios si es horario especial o apertura excepcional
        if ($type === 'special_hours' || $type === 'exceptional_opening') {
            $special_day['start_time'] = sanitize_text_field($_POST['special_start']);
            $special_day['end_time'] = sanitize_text_field($_POST['special_end']);
            // Mantener compatibilidad con start/end
            $special_day['start'] = $special_day['start_time'];
            $special_day['end'] = $special_day['end_time'];
        }
        
        // Obtener días especiales existentes
        $special_days = get_option('makia_special_days', array());
        
        // Verificar si ya existe
        $exists = false;
        foreach ($special_days as $index => $day) {
            if ($day['date'] === $date) {
                $special_days[$index] = $special_day;
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $special_days[] = $special_day;
        }
        
        // Guardar
        update_option('makia_special_days', $special_days);
        
        // Mensaje de éxito
        add_settings_error('makia_special_days', 'day_saved', 'Día especial guardado correctamente', 'success');
        set_transient('settings_errors', get_settings_errors(), 30);
        
        // Redirigir
        wp_redirect(admin_url('admin.php?page=makia&tab=special-days'));
        exit;
    }
    
    /**
     * Eliminar día especial
     */
    public function delete_special_day() {
        // Verificar nonce
        if (!isset($_POST['makia_delete_special_day_nonce']) || !wp_verify_nonce($_POST['makia_delete_special_day_nonce'], 'makia_delete_special_day_action')) {
            wp_die('Acción no autorizada');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        // Obtener índice
        $index = intval($_POST['day_index']);
        
        // Obtener días especiales
        $special_days = get_option('makia_special_days', array());
        
        // Eliminar
        if (isset($special_days[$index])) {
            array_splice($special_days, $index, 1);
            update_option('makia_special_days', $special_days);
            
            add_settings_error('makia_special_days', 'day_deleted', 'Día especial eliminado', 'success');
        }
        
        set_transient('settings_errors', get_settings_errors(), 30);
        
        // Redirigir
        wp_redirect(admin_url('admin.php?page=makia&tab=special-days'));
        exit;
    }
}

// Inicializar solo si no está ya inicializada
if (!isset($GLOBALS['makia_special_days'])) {
    $GLOBALS['makia_special_days'] = new MakIA_Special_Days();
}
