/**
 * MakIA Reservas - JavaScript (Ultra Robusta - WordPress Compatible)
 * Version: 4.3.0
 */

jQuery(document).ready(function($) {
    'use strict';

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    // Variables globales
    var businessHours = {};
    var specialDays = [];
    var isSubmitting = false;

    // Cargar configuración
    if (typeof makiaConfig !== 'undefined') {

        // Parsear businessHours
        if (typeof makiaConfig.businessHours === 'string') {
            try {
                businessHours = JSON.parse(makiaConfig.businessHours);
            } catch (e) {
                console.error('[MakIA] Error parseando businessHours:', e);
            }
        } else {
            businessHours = makiaConfig.businessHours || {};
        }

        // specialDays
        specialDays = makiaConfig.specialDays || [];

    } else {
        console.warn('[MakIA] makiaConfig no definido');
    }

    // =========================================================================
    // MODAL: Abrir / Cerrar (usa display:flex para centrado correcto)
    // =========================================================================

    function openModal() {
        var $overlay = $('#makia-modal-overlay');
        if (!$overlay.length) return;
        $overlay.addClass('active').css('opacity', 0).animate({opacity: 1}, 300);
        $('body').css('overflow', 'hidden');
    }

    function closeModal() {
        var $overlay = $('#makia-modal-overlay');
        if (!$overlay.length || !$overlay.hasClass('active')) return;
        $overlay.animate({opacity: 0}, 300, function() {
            $(this).removeClass('active').css('opacity', '');
        });
        $('body').css('overflow', 'auto');
        $('#makia-success-message').hide();
        $('#makia-booking-form').show();
    }

    // Exponer openModal globalmente para el botón flotante
    window.makiaOpenModal = openModal;

    // Abrir modal
    $(document).on('click', '#makia-open-modal', function(e) {
        e.preventDefault();
        openModal();
    });

    // Cerrar modal (clic en botón cerrar o en overlay)
    $(document).on('click', '#makia-close-modal, #makia-modal-overlay', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Cerrar modal con tecla ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#makia-modal-overlay').hasClass('active')) {
            closeModal();
        }
    });

    // Evitar que clics dentro del contenedor cierren el modal
    $(document).on('click', '.makia-modal-container', function(e) {
        e.stopPropagation();
    });

    // Manejar cambio de fecha
    $(document).on('change', '#makia-date', function() {
        var dateValue = $(this).val();
        loadAvailableHours(dateValue);
    });

    // =========================================================================
    // FORMULARIO: Envío AJAX
    // =========================================================================

    $(document).on('submit', '#makia-booking-form', function(e) {
        e.preventDefault();

        if (isSubmitting) return;
        isSubmitting = true;

        if (typeof makiaConfig === 'undefined') {
            alert('Error de configuración. Recarga la página.');
            isSubmitting = false;
            return;
        }

        var form = this;
        var submitBtn = $('#makia-submit-btn');
        var messagesContainer = $('#makia-booking-messages');

        // Validar
        if (!form.checkValidity()) {
            form.reportValidity();
            isSubmitting = false;
            return;
        }

        // Recopilar datos
        var formData = {
            action: 'makia_submit_booking',
            nonce: makiaConfig.nonce,
            name: $('#makia-name').val(),
            email: $('#makia-email').val(),
            phone: $('#makia-phone').val(),
            date: $('#makia-date').val(),
            time: $('#makia-time').val(),
            guests: $('#makia-guests').val(),
            reason: $('#makia-reason').val(),
            highchair: $('#makia-highchair').val(),
            notes: $('#makia-comments').val(),
            legal: $('#makia-legal').is(':checked') ? 'on' : ''
        };

        // Deshabilitar botón (usar clases CSS para preservar estructura de spans)
        submitBtn.prop('disabled', true).addClass('loading');
        messagesContainer.empty();

        // Enviar AJAX
        $.ajax({
            url: makiaConfig.ajaxUrl,
            method: 'POST',
            data: formData,
            success: function(response) {

                if (response.success) {
                    // Ocultar formulario y mostrar mensaje de éxito
                    var $form = $(form);
                    var $successMsg = $('#makia-success-message');
                    var $details = $('#makia-reservation-details');

                    // Rellenar detalles de la reserva
                    $details.empty();
                    var detailsDiv = document.createElement('div');
                    detailsDiv.style.cssText = 'background:#f8f9fa;padding:15px;border-radius:8px;margin:10px 0;';

                    var items = [
                        { label: 'Nombre', value: formData.name },
                        { label: 'Fecha', value: formData.date },
                        { label: 'Hora', value: formData.time },
                        { label: 'Personas', value: formData.guests }
                    ];
                    for (var d = 0; d < items.length; d++) {
                        var p = document.createElement('p');
                        p.style.cssText = 'margin:5px 0;font-size:14px;';
                        var strong = document.createElement('strong');
                        strong.textContent = items[d].label + ': ';
                        p.appendChild(strong);
                        p.appendChild(document.createTextNode(items[d].value));
                        detailsDiv.appendChild(p);
                    }
                    $details.append(detailsDiv);

                    $form.hide();
                    messagesContainer.empty();
                    $successMsg.show();

                    form.reset();
                    $(document).trigger('makia:booking:success');

                    setTimeout(function() {
                        closeModal();
                    }, 4000);
                } else {
                    messagesContainer.html(
                        '<div class="makia-message makia-error" style="background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;">' +
                        '<strong>Error:</strong> ' +
                        escapeHtml(response.data.message || response.data || 'Error desconocido') +
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('[MakIA] Error AJAX:', error);
                $(document).trigger('makia:booking:error');
                messagesContainer.html(
                    '<div class="makia-message makia-error" style="background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;">' +
                    '<strong>Error de conexión:</strong> No se pudo enviar la reserva.' +
                    '</div>'
                );
            },
            complete: function() {
                submitBtn.prop('disabled', false).removeClass('loading');
                isSubmitting = false;
            }
        });
    });

    // =========================================================================
    // HORARIOS
    // =========================================================================

    // Establecer fecha mínima
    var today = new Date().toISOString().split('T')[0];
    $('#makia-date').attr('min', today);

    // Cargar horarios para hoy
    $('#makia-date').val(today);
    loadAvailableHours(today);

    /**
     * Generar slots de tiempo cada 30 minutos
     */
    function generateTimeSlots(openTime, closeTime) {
        var slots = [];

        // Convertir a minutos
        var openParts = openTime.split(':');
        var closeParts = closeTime.split(':');
        var openMinutes = parseInt(openParts[0]) * 60 + parseInt(openParts[1]);
        var closeMinutes = parseInt(closeParts[0]) * 60 + parseInt(closeParts[1]);

        // Generar slots cada 30 minutos
        for (var minutes = openMinutes; minutes <= closeMinutes; minutes += 30) {
            var hours = Math.floor(minutes / 60);
            var mins = minutes % 60;
            var timeStr = (hours < 10 ? '0' : '') + hours + ':' + (mins < 10 ? '0' : '') + mins;
            slots.push(timeStr);
        }

        return slots;
    }

    /**
     * Verificar si una fecha está cerrada
     */
    function isDateClosed(dateString) {
        for (var i = 0; i < specialDays.length; i++) {
            if (specialDays[i].date === dateString && specialDays[i].type === 'closed') {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtener nombre del día en inglés
     */
    function getDayName(dateString) {
        var days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        var date = new Date(dateString + 'T12:00:00');
        return days[date.getDay()];
    }

    /**
     * Verificar si un día especial permite apertura excepcional
     */
    function getSpecialDayOpening(dateString) {
        for (var i = 0; i < specialDays.length; i++) {
            if (specialDays[i].date === dateString &&
                (specialDays[i].type === 'special_hours' || specialDays[i].type === 'exceptional_opening')) {
                return specialDays[i];
            }
        }
        return null;
    }

    /**
     * Cargar horarios disponibles
     */
    function loadAvailableHours(dateString) {

        var timeSelect = $('#makia-time');
        timeSelect.empty();

        // Verificar día especial cerrado
        if (isDateClosed(dateString)) {
            // Pero verificar si hay apertura excepcional
            var specialOpening = getSpecialDayOpening(dateString);
            if (!specialOpening) {
                timeSelect.append('<option value="">Cerrado</option>');
                return;
            }
        }

        // Obtener nombre del día de la semana
        var dayName = getDayName(dateString);

        // Verificar si el día está habilitado en horarios semanales
        var dayHours = businessHours[dayName];

        // Si no existe o no está habilitado, verificar apertura excepcional
        if (!dayHours || dayHours.enabled === false) {
            specialOpening = getSpecialDayOpening(dateString);
            if (specialOpening && specialOpening.start_time && specialOpening.end_time) {
                // Usar horarios del día especial
                var slots = generateTimeSlots(specialOpening.start_time, specialOpening.end_time);
                timeSelect.append('<option value="">Selecciona una hora</option>');
                for (var i = 0; i < slots.length; i++) {
                    timeSelect.append($('<option></option>').val(slots[i]).text(slots[i]));
                }
                return;
            }
            timeSelect.append('<option value="">Cerrado</option>');
            return;
        }

        // Buscar horarios especiales
        var specialDay = null;
        for (var i = 0; i < specialDays.length; i++) {
            if (specialDays[i].date === dateString) {
                specialDay = specialDays[i];
                break;
            }
        }

        var slots = [];

        if (specialDay && specialDay.hours && specialDay.hours.length > 0) {
            slots = specialDay.hours;
        } else if (dayHours.slots && dayHours.slots.length > 0) {
            // Generar slots desde open/close
            for (var i = 0; i < dayHours.slots.length; i++) {
                var slot = dayHours.slots[i];
                if (slot.open && slot.close) {
                    var generated = generateTimeSlots(slot.open, slot.close);
                    slots = slots.concat(generated);
                }
            }
        }

        // Agregar opción por defecto
        timeSelect.append('<option value="">Selecciona una hora</option>');

        if (slots.length > 0) {
            for (var i = 0; i < slots.length; i++) {
                timeSelect.append($('<option></option>').val(slots[i]).text(slots[i]));
            }
        } else {
            timeSelect.append('<option value="">Cerrado</option>');
        }
    }

    // Handler del botón "Nueva reserva"
    $(document).on('click', '#makia-new-booking-btn', function() {
        var $form = $('#makia-booking-form');
        var $successMsg = $('#makia-success-message');
        $successMsg.hide();
        $form.show();
        $form[0].reset();
        $('#makia-booking-messages').empty();
        // Recargar horarios para hoy
        var today = new Date().toISOString().split('T')[0];
        $('#makia-date').val(today);
        loadAvailableHours(today);
    });
});
