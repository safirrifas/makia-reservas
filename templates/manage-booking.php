<?php
/**
 * Template para gestionar reservas (shortcode)
 * Se integra dentro de WordPress como shortcode
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'makia_bookings';

// Obtener token de la URL
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

// Estilos inline para el contenedor
?>
<style>
    .makia-manage-container {
        max-width: 600px;
        margin: 30px auto;
        padding: 30px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .makia-manage-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .makia-manage-header h2 {
        color: #333;
        font-size: 24px;
        margin-bottom: 10px;
    }
    .makia-booking-details {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
    }
    .makia-booking-details h3 {
        margin-top: 0;
        color: #555;
    }
    .makia-detail-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    .makia-detail-row:last-child {
        border-bottom: none;
    }
    .makia-detail-label {
        font-weight: 600;
        color: #666;
    }
    .makia-detail-value {
        color: #333;
    }
    .makia-status-badge {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }
    .makia-status-pending {
        background: #fff3cd;
        color: #856404;
    }
    .makia-status-approved {
        background: #d4edda;
        color: #155724;
    }
    .makia-status-cancelled {
        background: #f8d7da;
        color: #721c24;
    }
    .makia-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }
    .makia-btn {
        flex: 1;
        padding: 15px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
    }
    .makia-btn-modify {
        background: #007bff;
        color: white;
    }
    .makia-btn-modify:hover {
        background: #0056b3;
    }
    .makia-btn-cancel {
        background: #dc3545;
        color: white;
    }
    .makia-btn-cancel:hover {
        background: #c82333;
    }
    .makia-error {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .makia-success {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .makia-modify-form {
        display: none;
        margin-top: 30px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .makia-form-group {
        margin-bottom: 20px;
    }
    .makia-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #555;
    }
    .makia-form-group input,
    .makia-form-group select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        box-sizing: border-box;
    }
    .makia-form-actions {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }
    .makia-info-box {
        background: #e7f3ff;
        border: 1px solid #b3d7ff;
        color: #004085;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
    }
    .makia-info-box a {
        color: #004085;
        font-weight: 600;
    }
</style>

<div class="makia-manage-container">
    <?php
    if (empty($token)) {
        ?>
        <div class="makia-manage-header">
            <h2>Gestionar tu Reserva</h2>
            <p>Accede a tu reserva usando el enlace que recibiste en tu email</p>
        </div>
        <div class="makia-info-box">
            <p>Para gestionar tu reserva, necesitas usar el enlace personalizado que te enviamos por email cuando realizaste tu reserva.</p>
            <p>Si no encuentras el email, revisa tu carpeta de spam o <a href="<?php echo esc_url(home_url('/contactar/')); ?>">contáctanos</a>.</p>
        </div>
        <?php
        return;
    }
    
    // Buscar reserva por token
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE edit_token = %s",
        $token
    ));
    
    if (!$booking) {
        echo '<div class="makia-error">Reserva no encontrada. El enlace puede haber expirado o ser incorrecto.</div>';
        return;
    }
    
    // Formatear fecha y hora
    $date_formatted = date('d/m/Y', strtotime($booking->booking_date));
    $time_formatted = date('H:i', strtotime($booking->booking_time));
    
    // Traducir estado
    $status_labels = array(
        'pending' => 'Pendiente',
        'approved' => 'Confirmada',
        'cancelled' => 'Cancelada',
        'rejected' => 'Rechazada',
        'no_show' => 'No asistió'
    );
    $status_text = isset($status_labels[$booking->status]) ? $status_labels[$booking->status] : $booking->status;
    ?>
    
    <div class="makia-manage-header">
        <h2>Gestionar tu Reserva</h2>
        <p>Aquí puedes ver, modificar o cancelar tu reserva</p>
    </div>
    
    <div id="makia-message-container"></div>
    
    <div class="makia-booking-details">
        <h3>Detalles de la Reserva</h3>
        <div class="makia-detail-row">
            <span class="makia-detail-label">Nombre:</span>
            <span class="makia-detail-value"><?php echo esc_html($booking->name); ?></span>
        </div>
        <div class="makia-detail-row">
            <span class="makia-detail-label">Email:</span>
            <span class="makia-detail-value"><?php echo esc_html($booking->email); ?></span>
        </div>
        <div class="makia-detail-row">
            <span class="makia-detail-label">Teléfono:</span>
            <span class="makia-detail-value"><?php echo esc_html($booking->phone); ?></span>
        </div>
        <div class="makia-detail-row">
            <span class="makia-detail-label">Fecha:</span>
            <span class="makia-detail-value"><?php echo esc_html($date_formatted); ?></span>
        </div>
        <div class="makia-detail-row">
            <span class="makia-detail-label">Hora:</span>
            <span class="makia-detail-value"><?php echo esc_html($time_formatted); ?></span>
        </div>
        <div class="makia-detail-row">
            <span class="makia-detail-label">Personas:</span>
            <span class="makia-detail-value"><?php echo esc_html($booking->guests); ?></span>
        </div>
        <?php if (!empty($booking->occasion)): ?>
        <div class="makia-detail-row">
            <span class="makia-detail-label">Motivo:</span>
            <span class="makia-detail-value"><?php echo esc_html($booking->occasion); ?></span>
        </div>
        <?php endif; ?>
        <div class="makia-detail-row">
            <span class="makia-detail-label">Estado:</span>
            <span class="makia-detail-value">
                <span class="makia-status-badge makia-status-<?php echo esc_attr($booking->status); ?>">
                    <?php echo esc_html($status_text); ?>
                </span>
            </span>
        </div>
    </div>
    
    <?php if ($booking->status !== 'cancelled' && $booking->status !== 'no_show'): ?>
    <div class="makia-actions">
        <button type="button" class="makia-btn makia-btn-modify" onclick="makiaShowModifyForm()">
            Modificar Reserva
        </button>
        <button type="button" class="makia-btn makia-btn-cancel" onclick="makiaCancelBooking()">
            Cancelar Reserva
        </button>
    </div>
    
    <div class="makia-modify-form" id="makia-modify-form">
        <h3>Modificar Reserva</h3>
        <form id="makia-modify-booking-form">
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="action" value="makia_modify_booking">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('makia_modify_booking_nonce'); ?>">
            
            <div class="makia-form-group">
                <label for="makia-modify-date">Nueva Fecha *</label>
                <input type="date" id="makia-modify-date" name="date" value="<?php echo esc_attr($booking->booking_date); ?>" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="makia-form-group">
                <label for="makia-modify-time">Nueva Hora *</label>
                <select id="makia-modify-time" name="time" required>
                    <option value="">Selecciona una hora...</option>
                </select>
            </div>
            
            <div class="makia-form-group">
                <label for="makia-modify-guests">Número de Personas *</label>
                <input type="number" id="makia-modify-guests" name="guests" min="1" max="20" value="<?php echo esc_attr($booking->guests); ?>" required>
            </div>
            
            <div class="makia-form-actions">
                <button type="submit" class="makia-btn makia-btn-modify">Guardar Cambios</button>
                <button type="button" class="makia-btn" onclick="makiaHideModifyForm()" style="background: #6c757d; color: white;">Cancelar</button>
            </div>
        </form>
    </div>
    <?php elseif ($booking->status === 'cancelled'): ?>
    <div class="makia-info-box" style="background: #f8d7da; border-color: #f5c6cb; color: #721c24;">
        Esta reserva ha sido cancelada.
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var makiaAjaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var makiaToken = '<?php echo esc_js($token); ?>';
    var makiaCancelNonce = '<?php echo wp_create_nonce('makia_cancel_booking_nonce'); ?>';
    var makiaCurrentTime = '<?php echo isset($booking) ? esc_js($booking->booking_time) : ''; ?>';
    
    window.makiaShowModifyForm = function() {
        document.getElementById('makia-modify-form').style.display = 'block';
        makiaLoadAvailableHours();
    };
    
    window.makiaHideModifyForm = function() {
        document.getElementById('makia-modify-form').style.display = 'none';
    };
    
    function makiaLoadAvailableHours() {
        var timeSelect = document.getElementById('makia-modify-time');
        
        // Horarios típicos de restaurante
        var hours = ['13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '20:00', '20:30', '21:00', '21:30', '22:00', '22:30'];
        
        timeSelect.innerHTML = '<option value="">Selecciona una hora...</option>';
        hours.forEach(function(hour) {
            var option = document.createElement('option');
            option.value = hour + ':00';
            option.textContent = hour;
            if (hour + ':00' === makiaCurrentTime) {
                option.selected = true;
            }
            timeSelect.appendChild(option);
        });
    }
    
    window.makiaCancelBooking = function() {
        if (!confirm('¿Estás seguro de que deseas cancelar esta reserva? Esta acción no se puede deshacer.')) {
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'makia_cancel_booking');
        formData.append('token', makiaToken);
        formData.append('nonce', makiaCancelNonce);
        
        fetch(makiaAjaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                makiaShowMessage(data.data.message, 'success');
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                makiaShowMessage(data.data.message || 'Error al cancelar la reserva', 'error');
            }
        })
        .catch(function(error) {
            makiaShowMessage('Error al cancelar la reserva. Por favor, inténtalo de nuevo.', 'error');
        });
    };
    
    var modifyForm = document.getElementById('makia-modify-booking-form');
    if (modifyForm) {
        modifyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            
            fetch(makiaAjaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    makiaShowMessage(data.data.message, 'success');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    makiaShowMessage(data.data.message || 'Error al modificar la reserva', 'error');
                }
            })
            .catch(function(error) {
                makiaShowMessage('Error al modificar la reserva. Por favor, inténtalo de nuevo.', 'error');
            });
        });
    }
    
    function makiaShowMessage(message, type) {
        var container = document.getElementById('makia-message-container');
        var className = type === 'success' ? 'makia-success' : 'makia-error';
        container.innerHTML = '<div class="' + className + '">' + message + '</div>';
        container.scrollIntoView({ behavior: 'smooth' });
    }
})();
</script>
