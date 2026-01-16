/**
 * MakIA Reservas - JavaScript (Ultra Robusta - WordPress Compatible)
 * Version: 3.2.3
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('[MakIA] Script cargado');
    
    // Variables globales
    var businessHours = {};
    var specialDays = [];
    
    // Cargar configuración
    if (typeof makiaConfig !== 'undefined') {
        console.log('[MakIA] Config encontrado:', makiaConfig);
        
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
        
        console.log('[MakIA] Horarios:', businessHours);
        console.log('[MakIA] Días especiales:', specialDays);
    } else {
        console.warn('[MakIA] makiaConfig no definido');
    }
    
    // Inicializar modal
    console.log('[MakIA] Inicializando modal...');
    
    $(document).on('click', '#makia-open-modal', function(e) {
        e.preventDefault();
        console.log('[MakIA] Abriendo modal');
        $('#makia-modal-overlay').fadeIn(300);
        $('body').css('overflow', 'hidden');
    });
    
    $(document).on('click', '#makia-close-modal, #makia-modal-overlay', function(e) {
        if (e.target === this) {
            console.log('[MakIA] Cerrando modal');
            $('#makia-modal-overlay').fadeOut(300);
            $('body').css('overflow', 'auto');
        }
    });
    
    $(document).on('click', '.makia-modal-container', function(e) {
        e.stopPropagation();
    });
    
    // Manejar cambio de fecha
    $(document).on('change', '#makia-date', function() {
        var dateValue = $(this).val();
        console.log('[MakIA] Fecha cambiada:', dateValue);
        loadAvailableHours(dateValue);
    });
    
    // Manejar envío del formulario
    $(document).on('submit', '#makia-booking-form', function(e) {
        e.preventDefault();
        console.log('[MakIA] === FORMULARIO ENVIADO ===');
        
        var form = this;
        var submitBtn = $('#makia-submit-btn');
        var messagesContainer = $('#makia-booking-messages');
        
        // Validar
        if (!form.checkValidity()) {
            console.log('[MakIA] Formulario inválido');
            form.reportValidity();
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
        
        console.log('[MakIA] Datos a enviar:', formData);
        
        // Deshabilitar botón
        submitBtn.prop('disabled', true).text('Procesando...');
        messagesContainer.empty();
        
        // Enviar AJAX
        $.ajax({
            url: makiaConfig.ajaxUrl,
            method: 'POST',
            data: formData,
            success: function(response) {
                console.log('[MakIA] Respuesta:', response);
                
                if (response.success) {
                    messagesContainer.html(
                        '<div class="makia-message makia-success" style="background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:15px 0;">' +
                        '<strong>¡Reserva recibida!</strong><br>' +
                        (response.data.message || 'Te enviaremos un email de confirmación cuando sea aprobada.') +
                        '</div>'
                    );
                    
                    form.reset();
                    
                    setTimeout(function() {
                        $('#makia-modal-overlay').fadeOut(300);
                        $('body').css('overflow', 'auto');
                        messagesContainer.empty();
                    }, 3000);
                } else {
                    messagesContainer.html(
                        '<div class="makia-message makia-error" style="background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;">' +
                        '<strong>Error:</strong> ' +
                        (response.data.message || 'Error desconocido') +
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('[MakIA] Error AJAX:', error, xhr);
                messagesContainer.html(
                    '<div class="makia-message makia-error" style="background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;">' +
                    '<strong>Error de conexión:</strong> No se pudo enviar la reserva.' +
                    '</div>'
                );
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Confirmar Reserva');
            }
        });
    });
    
    // Establecer fecha mínima
    var today = new Date().toISOString().split('T')[0];
    $('#makia-date').attr('min', today);
    
    // Cargar horarios para hoy
    $('#makia-date').val(today);
    loadAvailableHours(today);
    
    console.log('[MakIA] Inicialización completa');
    
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
                console.log('[MakIA] Fecha cerrada por día especial:', dateString);
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
        console.log('[MakIA] Cargando horarios para:', dateString);
        
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
        console.log('[MakIA] Día de la semana:', dayName);
        
        // Verificar si el día está habilitado en horarios semanales
        var dayHours = businessHours[dayName];
        
        // Si no existe o no está habilitado, verificar apertura excepcional
        if (!dayHours || dayHours.enabled === false) {
            var specialOpening = getSpecialDayOpening(dateString);
            if (specialOpening && specialOpening.start_time && specialOpening.end_time) {
                // Usar horarios del día especial
                console.log('[MakIA] Apertura excepcional:', specialOpening);
                var slots = generateTimeSlots(specialOpening.start_time, specialOpening.end_time);
                timeSelect.append('<option value="">Selecciona una hora</option>');
                for (var i = 0; i < slots.length; i++) {
                    timeSelect.append('<option value="' + slots[i] + '">' + slots[i] + '</option>');
                }
                return;
            }
            console.log('[MakIA] Día cerrado (no habilitado)');
            timeSelect.append('<option value="">Cerrado</option>');
            return;
        }
        
        console.log('[MakIA] Horarios del día:', dayHours);
        
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
            console.log('[MakIA] Usando horarios especiales');
        } else if (dayHours.slots && dayHours.slots.length > 0) {
            // Generar slots desde open/close
            for (var i = 0; i < dayHours.slots.length; i++) {
                var slot = dayHours.slots[i];
                if (slot.open && slot.close) {
                    var generated = generateTimeSlots(slot.open, slot.close);
                    slots = slots.concat(generated);
                }
            }
            console.log('[MakIA] Usando horarios normales');
        }
        
        // Agregar opción por defecto
        timeSelect.append('<option value="">Selecciona una hora</option>');
        
        if (slots.length > 0) {
            for (var i = 0; i < slots.length; i++) {
                timeSelect.append('<option value="' + slots[i] + '">' + slots[i] + '</option>');
            }
            console.log('[MakIA] Horarios agregados:', slots.length);
        } else {
            timeSelect.append('<option value="">Cerrado</option>');
        }
    }
});
