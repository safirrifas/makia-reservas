/**
 * MakIA Admin Panel JavaScript
 * Manejo de acciones AJAX para aprobar/rechazar reservas sin recargar
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Manejar aprobar/rechazar con AJAX
        $(document).on('submit', 'form[data-ajax-action]', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $button = $form.find('button[type="submit"]');
            const bookingId = $form.find('input[name="booking_id"]').val();
            const newStatus = $form.find('input[name="status"]').val();
            const nonce = $form.find('input[name="makia_booking_status_nonce"]').val();
            
            // Deshabilitar botón
            $button.prop('disabled', true);
            const originalText = $button.html();
            $button.html('⏳ Procesando...');
            
            // Enviar petición AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'makia_update_booking_status_ajax',
                    booking_id: bookingId,
                    status: newStatus,
                    makia_booking_status_nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('[MakIA Admin] Reserva actualizada:', response.data);
                        
                        // Actualizar UI
                        updateBookingCard(bookingId, newStatus, response.data);
                        
                        // Mostrar mensaje de éxito
                        showNotification('✅ ' + response.data.message, 'success');
                        
                        // Actualizar estadísticas
                        updateStats(response.data.stats);
                    } else {
                        console.error('[MakIA Admin] Error:', response.data);
                        showNotification('❌ ' + response.data, 'error');
                        $button.prop('disabled', false);
                        $button.html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[MakIA Admin] Error AJAX:', error);
                    showNotification('❌ Error al actualizar la reserva', 'error');
                    $button.prop('disabled', false);
                    $button.html(originalText);
                }
            });
        });
        
        /**
         * Actualizar tarjeta de reserva después de cambiar estado
         */
        function updateBookingCard(bookingId, newStatus, data) {
            // Buscar tarjeta (versión móvil)
            const $mobileCard = $('.makia-booking-card[data-booking-id="' + bookingId + '"]');
            if ($mobileCard.length) {
                const $badge = $mobileCard.find('.makia-booking-status');
                const $actions = $mobileCard.find('.makia-booking-actions');
                
                // Actualizar badge de estado
                $badge.removeClass('status-pending status-approved status-rejected');
                $badge.addClass('status-' + newStatus);
                
                if (newStatus === 'approved') {
                    $badge.text('✅ Aprobada');
                } else if (newStatus === 'rejected') {
                    $badge.text('❌ Rechazada');
                }
                
                // Ocultar botones de aprobar/rechazar
                $actions.find('form[data-ajax-action]').fadeOut(300, function() {
                    $(this).remove();
                });
            }
            
            // Buscar fila (versión desktop)
            const $row = $('tr[data-booking-id="' + bookingId + '"]');
            if ($row.length) {
                const $statusCell = $row.find('.booking-status');
                const $actionsCell = $row.find('.booking-actions');
                
                // Actualizar estado
                $statusCell.removeClass('status-pending status-approved status-rejected');
                $statusCell.addClass('status-' + newStatus);
                
                if (newStatus === 'approved') {
                    $statusCell.html('<span class="status-badge status-approved">✅ Aprobada</span>');
                } else if (newStatus === 'rejected') {
                    $statusCell.html('<span class="status-badge status-rejected">❌ Rechazada</span>');
                }
                
                // Ocultar botones
                $actionsCell.find('form[data-ajax-action]').fadeOut(300, function() {
                    $(this).remove();
                });
            }
        }
        
        /**
         * Actualizar estadísticas
         */
        function updateStats(stats) {
            if (!stats) return;
            
            // Actualizar contadores
            $('.makia-stat-card').each(function() {
                const $card = $(this);
                const title = $card.find('h3').text();
                
                if (title.includes('Total Reservas')) {
                    $card.find('p').text(stats.total);
                } else if (title.includes('Pendientes')) {
                    $card.find('p').text(stats.pending);
                } else if (title.includes('Aprobadas')) {
                    $card.find('p').text(stats.approved);
                } else if (title.includes('Próximas')) {
                    $card.find('p').text(stats.upcoming);
                }
            });
        }
        
        /**
         * Mostrar notificación
         */
        function showNotification(message, type) {
            // Eliminar notificaciones anteriores
            $('.makia-notification').remove();

            type = type || 'success';
            var validTypes = ['success', 'error', 'warning', 'info'];
            if (validTypes.indexOf(type) === -1) type = 'info';

            // Crear notificación
            const $notification = $('<div class="makia-notification"></div>')
                .addClass('makia-notification-' + type)
                .text(message);

            // Agregar al DOM
            $('.wrap').prepend($notification);
            
            // Animar entrada
            $notification.fadeIn(300);
            
            // Auto-ocultar después de 3 segundos
            setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    });
    
})(jQuery);
