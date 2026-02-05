/**
 * MakIA Restaurante - Acciones en Lote (Bulk Actions)
 * Permite seleccionar múltiples reservas y aplicar acciones masivas
 */

jQuery(document).ready(function($) {
    console.log('[MakIA Bulk] Script cargado');
    
    // Variables
    let selectedBookings = [];
    const $bulkBar = $('.makia-bulk-actions-bar');
    const $selectAll = $('#makia-select-all');
    const $selectedCount = $('#makia-selected-count');
    
    // Inicializar checkboxes en las tarjetas móviles
    initMobileCheckboxes();
    
    /**
     * Inicializar checkboxes en tarjetas móviles
     */
    function initMobileCheckboxes() {
        // Añadir checkboxes a las tarjetas móviles si no existen
        $('.makia-booking-card').each(function() {
            const $card = $(this);
            const bookingId = $card.data('booking-id');
            
            if (!$card.find('.makia-booking-checkbox').length) {
                const $checkbox = $('<input type="checkbox" class="makia-booking-checkbox" data-booking-id="' + bookingId + '" style="position: absolute; top: 15px; right: 15px; width: 20px; height: 20px; cursor: pointer;">');
                $card.css('position', 'relative').prepend($checkbox);
            }
        });
    }
    
    /**
     * Manejar selección de checkbox individual (tabla)
     */
    $(document).on('change', '.booking-checkbox', function() {
        const bookingId = $(this).data('booking-id');
        
        if ($(this).is(':checked')) {
            if (!selectedBookings.includes(bookingId)) {
                selectedBookings.push(bookingId);
            }
        } else {
            selectedBookings = selectedBookings.filter(id => id !== bookingId);
        }
        
        updateBulkBar();
    });
    
    /**
     * Manejar selección de checkbox individual (tarjetas móviles)
     */
    $(document).on('change', '.makia-booking-checkbox', function() {
        const bookingId = $(this).data('booking-id');
        
        if ($(this).is(':checked')) {
            if (!selectedBookings.includes(bookingId)) {
                selectedBookings.push(bookingId);
            }
            $(this).closest('.makia-booking-card').addClass('selected');
        } else {
            selectedBookings = selectedBookings.filter(id => id !== bookingId);
            $(this).closest('.makia-booking-card').removeClass('selected');
        }
        
        updateBulkBar();
    });
    
    /**
     * Seleccionar/deseleccionar todas las reservas
     */
    $selectAll.on('change', function() {
        const isChecked = $(this).is(':checked');
        
        // Tabla
        $('.booking-checkbox').prop('checked', isChecked).trigger('change');
        
        // Tarjetas móviles
        $('.makia-booking-checkbox').prop('checked', isChecked);
        if (isChecked) {
            $('.makia-booking-card').addClass('selected');
            $('.makia-booking-checkbox').each(function() {
                const bookingId = $(this).data('booking-id');
                if (!selectedBookings.includes(bookingId)) {
                    selectedBookings.push(bookingId);
                }
            });
        } else {
            $('.makia-booking-card').removeClass('selected');
            selectedBookings = [];
        }
        
        updateBulkBar();
    });
    
    /**
     * Actualizar barra de acciones en lote
     */
    function updateBulkBar() {
        $selectedCount.text(selectedBookings.length);
        
        if (selectedBookings.length > 0) {
            $bulkBar.slideDown(200);
        } else {
            $bulkBar.slideUp(200);
        }
        
        // Actualizar estado del checkbox "seleccionar todas"
        const totalCheckboxes = $('.booking-checkbox, .makia-booking-checkbox').length;
        const checkedCheckboxes = $('.booking-checkbox:checked, .makia-booking-checkbox:checked').length;
        
        $selectAll.prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        $selectAll.prop('checked', checkedCheckboxes === totalCheckboxes && totalCheckboxes > 0);
    }
    
    /**
     * Limpiar selección
     */
    $('#makia-clear-selection').on('click', function() {
        selectedBookings = [];
        $('.booking-checkbox, .makia-booking-checkbox').prop('checked', false);
        $('.makia-booking-card').removeClass('selected');
        $selectAll.prop('checked', false).prop('indeterminate', false);
        updateBulkBar();
    });
    
    /**
     * Ejecutar acción en lote
     */
    $('.makia-bulk-action').on('click', function() {
        const action = $(this).data('action');
        const $button = $(this);
        
        if (selectedBookings.length === 0) {
            showNotification('Selecciona al menos una reserva', 'error');
            return;
        }
        
        // Confirmar acción
        const actionNames = {
            'approve': 'aprobar',
            'reject': 'rechazar',
            'cancel': 'cancelar',
            'reminder': 'enviar recordatorio a',
            'noshow': 'marcar como no-show'
        };
        
        const confirmMessage = '¿Estás seguro de que quieres ' + actionNames[action] + ' ' + selectedBookings.length + ' reserva(s)?';
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        // Deshabilitar botón
        $button.prop('disabled', true);
        const originalText = $button.html();
        $button.html('⏳ Procesando...');
        
        // Enviar petición AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'makia_bulk_action',
                bulk_action: action,
                booking_ids: selectedBookings,
                nonce: $('#makia_bulk_nonce').val() || ''
            },
            success: function(response) {
                if (response.success) {
                    showNotification('✅ ' + response.data.message, 'success');
                    
                    // Actualizar UI según la acción
                    if (action === 'approve' || action === 'reject' || action === 'cancel' || action === 'noshow') {
                        // Recargar página para mostrar cambios
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else if (action === 'reminder') {
                        // Limpiar selección
                        $('#makia-clear-selection').trigger('click');
                    }
                    
                    // Actualizar estadísticas si están disponibles
                    if (response.data.stats) {
                        updateStats(response.data.stats);
                    }
                } else {
                    showNotification('❌ ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('[MakIA Bulk] Error:', error);
                showNotification('❌ Error al ejecutar la acción', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.html(originalText);
            }
        });
    });
    
    /**
     * Actualizar estadísticas
     */
    function updateStats(stats) {
        if (!stats) return;
        
        $('.makia-stat-card').each(function() {
            const $card = $(this);
            const title = $card.find('h3').text();
            
            if (title.includes('Total')) {
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
        
        const $notification = $('<div class="makia-notification makia-notification-' + type + '">' + message + '</div>');
        
        $notification.css({
            'position': 'fixed',
            'top': '20px',
            'right': '20px',
            'padding': '15px 25px',
            'border-radius': '8px',
            'z-index': '99999',
            'font-weight': '600',
            'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
            'background': type === 'success' ? '#46b450' : '#dc3232',
            'color': '#fff'
        });
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Añadir estilos CSS para tarjetas seleccionadas
    $('<style>')
        .text(`
            .makia-booking-card.selected {
                border: 2px solid #667eea !important;
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%) !important;
            }
            .makia-booking-checkbox {
                accent-color: #667eea;
            }
            .makia-bulk-actions-bar {
                position: sticky;
                top: 32px;
                z-index: 100;
            }
            @media (max-width: 782px) {
                .makia-bulk-actions-bar {
                    top: 0;
                    padding: 10px !important;
                }
                .makia-bulk-actions-bar .button {
                    padding: 8px 12px !important;
                    font-size: 12px !important;
                }
            }
        `)
        .appendTo('head');
});
