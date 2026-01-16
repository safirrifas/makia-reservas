/**
 * MakIA Reservas - Gestión de Notas Internas y Recordatorios
 */

jQuery(document).ready(function($) {
    console.log('[MakIA Notes] Script cargado');
    
    // Agregar nota interna
    $(document).on('submit', '#makia-add-note-form', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const bookingId = form.find('input[name="booking_id"]').val();
        const note = form.find('textarea[name="note"]').val().trim();
        const button = form.find('button[type="submit"]');
        
        if (!note) {
            showNotification('La nota no puede estar vacía', 'error');
            return;
        }
        
        button.prop('disabled', true).text('Guardando...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'makia_add_booking_note',
                booking_id: bookingId,
                note: note
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    form.find('textarea').val('');
                    loadBookingNotes(bookingId);
                } else {
                    showNotification(response.data, 'error');
                }
            },
            error: function() {
                showNotification('Error al guardar la nota', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text('💾 Guardar Nota');
            }
        });
    });
    
    // Enviar recordatorio
    $(document).on('click', '.makia-send-reminder', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const bookingId = button.data('booking-id');
        const method = button.data('method'); // 'sms' o 'whatsapp'
        
        button.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'makia_send_reminder',
                booking_id: bookingId,
                method: method
            },
            success: function(response) {
                if (response.success) {
                    // Abrir URL para enviar el mensaje
                    window.open(response.data.url, '_blank');
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data, 'error');
                }
            },
            error: function() {
                showNotification('Error al preparar el recordatorio', 'error');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Función para cargar notas de una reserva
    window.loadBookingNotes = function(bookingId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'makia_get_booking_notes',
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    renderBookingNotes(response.data.notes);
                }
            }
        });
    };
    
    // Función para renderizar notas
    function renderBookingNotes(notes) {
        const container = $('#makia-notes-list');
        
        if (!notes || notes.length === 0) {
            container.html('<div class="makia-no-notes">No hay notas internas</div>');
            return;
        }
        
        let html = '';
        notes.forEach(function(note) {
            const date = new Date(note.created_at);
            const dateStr = date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'});
            
            html += `
                <div class="makia-note-item">
                    <div class="makia-note-header">
                        <strong>${note.user_name}</strong>
                        <span class="makia-note-date">${dateStr}</span>
                    </div>
                    <div class="makia-note-content">${escapeHtml(note.note)}</div>
                </div>
            `;
        });
        
        container.html(html);
    }
    
    // Función para escapar HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Función para mostrar notificaciones
    function showNotification(message, type) {
        const notification = $('<div class="makia-notification makia-notification-' + type + '">' + message + '</div>');
        $('body').append(notification);
        
        setTimeout(function() {
            notification.addClass('makia-notification-show');
        }, 10);
        
        setTimeout(function() {
            notification.removeClass('makia-notification-show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }
});
