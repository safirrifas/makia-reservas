<?php
/**
 * Gestión de Horarios Semanales
 * Permite configurar los días y horarios de apertura del restaurante
 */

class Makia_Schedule {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('wp_ajax_makia_save_schedule', array($this, 'save_schedule_ajax'));
    }
    
    /**
     * Renderizar la página de horarios
     */
    public function render_page() {
        $business_hours = get_option('makia_business_hours', $this->get_default_hours());
        $days = array(
            'monday' => 'Lunes',
            'tuesday' => 'Martes',
            'wednesday' => 'Miércoles',
            'thursday' => 'Jueves',
            'friday' => 'Viernes',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo'
        );
        ?>
        <div class="wrap" style="max-width: 900px;">
            <h1 style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                <span style="font-size: 28px;">🕐</span>
                Horarios Semanales
            </h1>
            <p style="color: #666; margin-bottom: 25px;">
                Configura los días y horarios de apertura de tu restaurante. Los días marcados como cerrados no permitirán reservas.
            </p>
            
            <?php settings_errors('makia_schedule'); ?>
            
            <form id="makia-schedule-form" method="post">
                <?php wp_nonce_field('makia_save_schedule', 'makia_schedule_nonce'); ?>
                
                <div style="background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden;">
                    
                    <!-- Cabecera -->
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px 25px;">
                        <h2 style="margin: 0; font-size: 18px; font-weight: 600;">
                            📅 Configuración de Días de Apertura
                        </h2>
                        <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">
                            Activa o desactiva cada día de la semana y configura las franjas horarias
                        </p>
                    </div>
                    
                    <!-- Días de la semana -->
                    <div style="padding: 25px;">
                        <?php foreach ($days as $day_key => $day_name): 
                            $day_data = isset($business_hours[$day_key]) ? $business_hours[$day_key] : array('enabled' => true, 'slots' => array());
                            $is_enabled = isset($day_data['enabled']) ? $day_data['enabled'] : true;
                            $slots = isset($day_data['slots']) ? $day_data['slots'] : array();
                        ?>
                        <div class="makia-day-row" style="border: 1px solid #e0e0e0; border-radius: 10px; margin-bottom: 15px; overflow: hidden; <?php echo !$is_enabled ? 'opacity: 0.7;' : ''; ?>">
                            
                            <!-- Cabecera del día -->
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: <?php echo $is_enabled ? '#f8f9fa' : '#ffebee'; ?>; border-bottom: 1px solid #e0e0e0;">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <label class="makia-switch" style="position: relative; display: inline-block; width: 50px; height: 26px;">
                                        <input type="checkbox" name="days[<?php echo $day_key; ?>][enabled]" value="1" <?php checked($is_enabled); ?> class="day-toggle" data-day="<?php echo $day_key; ?>">
                                        <span class="makia-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 26px;"></span>
                                    </label>
                                    <span style="font-weight: 600; font-size: 16px; color: #333;">
                                        <?php echo $day_name; ?>
                                    </span>
                                    <span class="day-status" style="font-size: 13px; padding: 3px 10px; border-radius: 12px; <?php echo $is_enabled ? 'background: #e8f5e9; color: #2e7d32;' : 'background: #ffebee; color: #c62828;'; ?>">
                                        <?php echo $is_enabled ? '✓ Abierto' : '✕ Cerrado'; ?>
                                    </span>
                                </div>
                                <button type="button" class="add-slot-btn" data-day="<?php echo $day_key; ?>" style="background: #667eea; color: #fff; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 13px; <?php echo !$is_enabled ? 'display: none;' : ''; ?>">
                                    + Añadir Franja
                                </button>
                            </div>
                            
                            <!-- Franjas horarias -->
                            <div class="slots-container" data-day="<?php echo $day_key; ?>" style="padding: 15px 20px; <?php echo !$is_enabled ? 'display: none;' : ''; ?>">
                                <?php if (empty($slots)): ?>
                                <p class="no-slots-msg" style="color: #999; font-style: italic; margin: 0;">
                                    No hay franjas horarias configuradas. Haz clic en "Añadir Franja" para crear una.
                                </p>
                                <?php else: ?>
                                    <?php foreach ($slots as $index => $slot): ?>
                                    <div class="slot-row" style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px; padding: 10px 15px; background: #f5f5f5; border-radius: 8px;">
                                        <span style="color: #666;">Desde</span>
                                        <input type="time" name="days[<?php echo $day_key; ?>][slots][<?php echo $index; ?>][open]" value="<?php echo esc_attr($slot['open']); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                                        <span style="color: #666;">hasta</span>
                                        <input type="time" name="days[<?php echo $day_key; ?>][slots][<?php echo $index; ?>][close]" value="<?php echo esc_attr($slot['close']); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                                        <button type="button" class="remove-slot-btn" style="background: #f44336; color: #fff; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px;">
                                            🗑️
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Botón guardar -->
                    <div style="padding: 20px 25px; background: #f8f9fa; border-top: 1px solid #e0e0e0; text-align: right;">
                        <button type="submit" class="button button-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 12px 30px; font-size: 15px; border-radius: 8px; cursor: pointer;">
                            💾 Guardar Horarios
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Información -->
            <div style="margin-top: 25px; padding: 20px; background: #e3f2fd; border-radius: 10px; border-left: 4px solid #2196f3;">
                <h3 style="margin: 0 0 10px 0; color: #1565c0; font-size: 16px;">💡 Información Importante</h3>
                <ul style="margin: 0; padding-left: 20px; color: #1976d2;">
                    <li>Los días marcados como <strong>Cerrado</strong> no mostrarán horarios disponibles en el formulario de reservas.</li>
                    <li>Puedes configurar múltiples franjas horarias por día (ej: almuerzo y cena).</li>
                    <li>Para abrir un día que normalmente está cerrado, usa la sección de <strong>Días Especiales</strong>.</li>
                </ul>
            </div>
        </div>
        
        <style>
            .makia-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .makia-switch .makia-slider:before {
                position: absolute;
                content: "";
                height: 20px;
                width: 20px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .3s;
                border-radius: 50%;
            }
            .makia-switch input:checked + .makia-slider {
                background-color: #4caf50;
            }
            .makia-switch input:checked + .makia-slider:before {
                transform: translateX(24px);
            }
            .add-slot-btn:hover {
                background: #5a6fd6 !important;
            }
            .remove-slot-btn:hover {
                background: #d32f2f !important;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle día abierto/cerrado
            $('.day-toggle').on('change', function() {
                var day = $(this).data('day');
                var isEnabled = $(this).is(':checked');
                var row = $(this).closest('.makia-day-row');
                var slotsContainer = row.find('.slots-container');
                var addBtn = row.find('.add-slot-btn');
                var status = row.find('.day-status');
                
                if (isEnabled) {
                    row.css('opacity', '1');
                    row.find('.makia-day-row > div:first-child').css('background', '#f8f9fa');
                    slotsContainer.show();
                    addBtn.show();
                    status.html('✓ Abierto').css({'background': '#e8f5e9', 'color': '#2e7d32'});
                } else {
                    row.css('opacity', '0.7');
                    row.find('.makia-day-row > div:first-child').css('background', '#ffebee');
                    slotsContainer.hide();
                    addBtn.hide();
                    status.html('✕ Cerrado').css({'background': '#ffebee', 'color': '#c62828'});
                }
            });
            
            // Añadir franja horaria
            $('.add-slot-btn').on('click', function() {
                var day = $(this).data('day');
                var container = $('.slots-container[data-day="' + day + '"]');
                var slotCount = container.find('.slot-row').length;
                
                container.find('.no-slots-msg').remove();
                
                var html = '<div class="slot-row" style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px; padding: 10px 15px; background: #f5f5f5; border-radius: 8px;">' +
                    '<span style="color: #666;">Desde</span>' +
                    '<input type="time" name="days[' + day + '][slots][' + slotCount + '][open]" value="12:00" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">' +
                    '<span style="color: #666;">hasta</span>' +
                    '<input type="time" name="days[' + day + '][slots][' + slotCount + '][close]" value="16:00" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">' +
                    '<button type="button" class="remove-slot-btn" style="background: #f44336; color: #fff; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px;">🗑️</button>' +
                    '</div>';
                
                container.append(html);
            });
            
            // Eliminar franja horaria
            $(document).on('click', '.remove-slot-btn', function() {
                $(this).closest('.slot-row').remove();
            });
            
            // Guardar formulario via AJAX
            $('#makia-schedule-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=makia_save_schedule';
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    beforeSend: function() {
                        $('button[type="submit"]').prop('disabled', true).text('Guardando...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ Horarios guardados correctamente');
                        } else {
                            alert('❌ Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('❌ Error de conexión');
                    },
                    complete: function() {
                        $('button[type="submit"]').prop('disabled', false).html('💾 Guardar Horarios');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Obtener horarios por defecto
     */
    private function get_default_hours() {
        return array(
            'monday' => array('enabled' => true, 'slots' => array(
                array('open' => '12:00', 'close' => '16:00'),
                array('open' => '20:00', 'close' => '23:00')
            )),
            'tuesday' => array('enabled' => true, 'slots' => array(
                array('open' => '12:00', 'close' => '16:00'),
                array('open' => '20:00', 'close' => '23:00')
            )),
            'wednesday' => array('enabled' => true, 'slots' => array(
                array('open' => '12:00', 'close' => '16:00'),
                array('open' => '20:00', 'close' => '23:00')
            )),
            'thursday' => array('enabled' => true, 'slots' => array(
                array('open' => '12:00', 'close' => '16:00'),
                array('open' => '20:00', 'close' => '23:00')
            )),
            'friday' => array('enabled' => true, 'slots' => array(
                array('open' => '12:00', 'close' => '16:00'),
                array('open' => '20:00', 'close' => '23:00')
            )),
            'saturday' => array('enabled' => true, 'slots' => array(
                array('open' => '12:00', 'close' => '16:00'),
                array('open' => '20:00', 'close' => '23:00')
            )),
            'sunday' => array('enabled' => true, 'slots' => array(
                array('open' => '12:00', 'close' => '16:00'),
                array('open' => '20:00', 'close' => '23:00')
            ))
        );
    }
    
    /**
     * Guardar horarios via AJAX
     */
    public function save_schedule_ajax() {
        check_ajax_referer('makia_save_schedule', 'makia_schedule_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
        }
        
        $days_data = isset($_POST['days']) ? $_POST['days'] : array();
        $business_hours = array();
        
        $valid_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        
        foreach ($valid_days as $day) {
            if (isset($days_data[$day])) {
                $enabled = isset($days_data[$day]['enabled']) && $days_data[$day]['enabled'] == '1';
                $slots = array();
                
                if (isset($days_data[$day]['slots']) && is_array($days_data[$day]['slots'])) {
                    foreach ($days_data[$day]['slots'] as $slot) {
                        if (!empty($slot['open']) && !empty($slot['close'])) {
                            $slots[] = array(
                                'open' => sanitize_text_field($slot['open']),
                                'close' => sanitize_text_field($slot['close'])
                            );
                        }
                    }
                }
                
                $business_hours[$day] = array(
                    'enabled' => $enabled,
                    'slots' => $slots
                );
            } else {
                // Día no marcado = cerrado
                $business_hours[$day] = array(
                    'enabled' => false,
                    'slots' => array()
                );
            }
        }
        
        update_option('makia_business_hours', $business_hours);
        
        wp_send_json_success('Horarios guardados correctamente');
    }
    
    /**
     * Verificar si un día está abierto
     */
    public static function is_day_open($date) {
        $business_hours = get_option('makia_business_hours', array());
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        if (isset($business_hours[$day_of_week])) {
            return $business_hours[$day_of_week]['enabled'];
        }
        
        return true; // Por defecto abierto
    }
    
    /**
     * Obtener franjas horarias de un día
     */
    public static function get_day_slots($date) {
        $business_hours = get_option('makia_business_hours', array());
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        if (isset($business_hours[$day_of_week]) && $business_hours[$day_of_week]['enabled']) {
            return $business_hours[$day_of_week]['slots'];
        }
        
        return array();
    }
}

// Inicializar
Makia_Schedule::get_instance();
