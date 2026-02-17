/**
 * MakIA Admin Panel JavaScript
 * Manejo de acciones AJAX para aprobar/rechazar reservas sin recargar
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {

        // ========================================
        // FILTROS DE RESERVAS
        // ========================================

        let filterTimeout = null;

        // Búsqueda por texto
        $('#filter-search').on('input', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(function() {
                applyFilters();
            }, 300);
        });

        // Filtro por estado
        $('#filter-status').on('change', function() {
            applyFilters();
        });

        // Botones de período
        $('.makia-period-btn').on('click', function() {
            const period = $(this).data('period');

            // Resaltar botón activo
            $('.makia-period-btn').removeClass('button-primary');
            $(this).addClass('button-primary');

            // Aplicar filtro de período
            applyPeriodFilter(period);
        });

        // Limpiar filtros
        $('#clear-filters').on('click', function() {
            $('#filter-search').val('');
            $('#filter-status').val('');
            $('.makia-period-btn').removeClass('button-primary');
            showAllBookings();
        });

        /**
         * Aplicar filtros de búsqueda y estado
         */
        function applyFilters() {
            const searchText = $('#filter-search').val().toLowerCase().trim();
            const statusFilter = $('#filter-status').val();

            // Filtrar tarjetas móviles
            $('.makia-booking-card').each(function() {
                const $card = $(this);
                const name = ($card.find('.makia-booking-name').text() || '').toLowerCase();
                const email = ($card.find('.makia-booking-email').text() || '').toLowerCase();
                const phone = ($card.find('.makia-booking-phone').text() || '').toLowerCase();
                const status = $card.data('status') || '';

                let matchesSearch = true;
                let matchesStatus = true;

                if (searchText) {
                    matchesSearch = name.includes(searchText) ||
                                   email.includes(searchText) ||
                                   phone.includes(searchText);
                }

                if (statusFilter) {
                    matchesStatus = status === statusFilter;
                }

                if (matchesSearch && matchesStatus) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });

            // Filtrar filas de tabla desktop
            $('.makia-bookings-table tbody tr').each(function() {
                const $row = $(this);
                const name = ($row.find('td:nth-child(1)').text() || '').toLowerCase();
                const email = ($row.find('td:nth-child(2)').text() || '').toLowerCase();
                const phone = ($row.find('td:nth-child(3)').text() || '').toLowerCase();
                const status = $row.data('status') || $row.find('.status-badge').text().toLowerCase();

                let matchesSearch = true;
                let matchesStatus = true;

                if (searchText) {
                    matchesSearch = name.includes(searchText) ||
                                   email.includes(searchText) ||
                                   phone.includes(searchText);
                }

                if (statusFilter) {
                    const statusMap = {
                        'pending': ['pendiente', 'pending'],
                        'approved': ['aprobada', 'approved', 'confirmada'],
                        'cancelled': ['cancelada', 'cancelled', 'rechazada']
                    };
                    const validStatuses = statusMap[statusFilter] || [statusFilter];
                    matchesStatus = validStatuses.some(s => status.includes(s));
                }

                if (matchesSearch && matchesStatus) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });

            updateVisibleCount();
        }

        /**
         * Aplicar filtro de período
         */
        function applyPeriodFilter(period) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);

            const weekEnd = new Date(today);
            weekEnd.setDate(weekEnd.getDate() + 7);

            if (period === 'all') {
                showAllBookings();
                return;
            }

            // Filtrar tarjetas móviles
            $('.makia-booking-card').each(function() {
                const $card = $(this);
                const dateStr = $card.data('date');
                if (!dateStr) {
                    $card.show();
                    return;
                }

                const bookingDate = new Date(dateStr);
                bookingDate.setHours(0, 0, 0, 0);

                let show = false;

                switch(period) {
                    case 'today':
                        show = bookingDate.getTime() === today.getTime();
                        break;
                    case 'tomorrow':
                        show = bookingDate.getTime() === tomorrow.getTime();
                        break;
                    case 'week':
                        show = bookingDate >= today && bookingDate <= weekEnd;
                        break;
                }

                if (show) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });

            // Filtrar filas de tabla desktop
            $('.makia-bookings-table tbody tr').each(function() {
                const $row = $(this);
                const dateStr = $row.data('date') || $row.find('td:nth-child(4)').text();
                if (!dateStr) {
                    $row.show();
                    return;
                }

                // Parsear fecha en formato dd/mm/yyyy o yyyy-mm-dd
                let bookingDate;
                if (dateStr.includes('/')) {
                    const parts = dateStr.split('/');
                    bookingDate = new Date(parts[2], parts[1] - 1, parts[0]);
                } else {
                    bookingDate = new Date(dateStr);
                }
                bookingDate.setHours(0, 0, 0, 0);

                let show = false;

                switch(period) {
                    case 'today':
                        show = bookingDate.getTime() === today.getTime();
                        break;
                    case 'tomorrow':
                        show = bookingDate.getTime() === tomorrow.getTime();
                        break;
                    case 'week':
                        show = bookingDate >= today && bookingDate <= weekEnd;
                        break;
                }

                if (show) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });

            updateVisibleCount();
        }

        /**
         * Mostrar todas las reservas
         */
        function showAllBookings() {
            $('.makia-booking-card').show();
            $('.makia-bookings-table tbody tr').show();
            updateVisibleCount();
        }

        /**
         * Actualizar contador de resultados visibles
         */
        function updateVisibleCount() {
            const visibleCards = $('.makia-booking-card:visible').length;
            const visibleRows = $('.makia-bookings-table tbody tr:visible').length;
            const total = visibleCards || visibleRows;

            // Actualizar contador si existe
            if ($('.makia-filter-count').length) {
                $('.makia-filter-count').text(total + ' reserva' + (total !== 1 ? 's' : ''));
            }
        }

        // ========================================
        // ACCIONES DE RESERVAS (AJAX)
        // ========================================

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
