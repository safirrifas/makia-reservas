/**
 * MakIA Booking Form - Enhanced Frontend JavaScript
 * Maneja animaciones, validaciones y micro-interacciones
 * Versión 3.4.0
 */

(function($) {
    'use strict';
    
    // Configuración global
    const MakiaBookingEnhanced = {
        
        /**
         * Inicializar todas las mejoras
         */
        init: function() {
            this.setupAnimations();
            this.setupValidations();
            this.setupMicroInteractions();
            this.setupAccessibility();
            this.setupCustomColors();
            this.loadingStates();
        },
        
        /**
         * Configurar animaciones
         */
        setupAnimations: function() {
            // Animación de entrada del modal
            $(document).on('click', '#makia-open-modal', function() {
                const $overlay = $('#makia-modal-overlay');
                const animationsEnabled = $overlay.hasClass('makia-enable-animations');
                
                $overlay.fadeIn(animationsEnabled ? 300 : 0);
                
                if (animationsEnabled) {
                    $('.makia-modal-container').css({
                        opacity: 0,
                        transform: 'scale(0.9)'
                    }).animate({
                        opacity: 1
                    }, 300).css({
                        transform: 'scale(1)',
                        transition: 'transform 0.3s ease'
                    });
                }
            });
            
            // Animación de cierre del modal
            $(document).on('click', '#makia-close-modal, #makia-modal-overlay', function(e) {
                if (e.target === this) {
                    const $overlay = $('#makia-modal-overlay');
                    $overlay.fadeOut(300);
                    $('body').css('overflow', 'auto');
                    // Restaurar estado del formulario
                    $('#makia-success-message').hide();
                    $('#makia-booking-form').show();
                }
            });
            
            // Animación de campos al escribir
            $('input, select, textarea').on('focus', function() {
                $(this).closest('.makia-form-group').addClass('focused');
            }).on('blur', function() {
                $(this).closest('.makia-form-group').removeClass('focused');
            });
        },
        
        /**
         * Validaciones mejoradas
         */
        setupValidations: function() {
            // Validación de email en tiempo real
            $('input[type="email"]').on('input', function() {
                const email = $(this).val();
                const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                
                $(this).toggleClass('valid', isValid && email.length > 0);
                $(this).toggleClass('invalid', !isValid && email.length > 0);
            });
            
            // Validación de teléfono
            $('input[type="tel"]').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                
                // Formatear teléfono español (XXX XXX XXX)
                if (value.length > 3 && value.length <= 6) {
                    value = value.slice(0, 3) + ' ' + value.slice(3);
                } else if (value.length > 6) {
                    value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 9);
                }
                
                $(this).val(value);
            });
            
            // Validación de fecha (no permitir fechas pasadas)
            $('input[type="date"]').on('change', function() {
                const selectedDate = new Date($(this).val());
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate < today) {
                    $(this).addClass('invalid');
                    MakiaBookingEnhanced.showTooltip($(this), 'No puedes reservar en una fecha pasada');
                } else {
                    $(this).removeClass('invalid');
                }
            });
            
            // Validación de número de personas
            $('input[name="guests"]').on('input', function() {
                const guests = parseInt($(this).val());
                
                if (guests < 1) {
                    $(this).val(1);
                } else if (guests > 20) {
                    $(this).val(20);
                    MakiaBookingEnhanced.showTooltip($(this), 'Para grupos mayores de 20 personas, contacta directamente');
                }
            });
        },
        
        /**
         * Micro-interacciones
         */
        setupMicroInteractions: function() {
            // Efecto ripple en botones
            $('button[type="submit"]').on('click', function(e) {
                const $button = $(this);
                const $ripple = $('<span class="ripple"></span>');
                
                const x = e.pageX - $button.offset().left;
                const y = e.pageY - $button.offset().top;
                
                $ripple.css({
                    top: y + 'px',
                    left: x + 'px',
                    position: 'absolute',
                    width: '0',
                    height: '0',
                    borderRadius: '50%',
                    background: 'rgba(255, 255, 255, 0.5)',
                    transform: 'translate(-50%, -50%)',
                    animation: 'ripple 0.6s ease-out'
                });
                
                $button.append($ripple);
                
                setTimeout(function() {
                    $ripple.remove();
                }, 600);
            });
            
            // Feedback visual al completar campos requeridos
            $('input[required], select[required], textarea[required]').on('blur', function() {
                const $field = $(this);
                
                if ($field.val() !== '') {
                    $field.addClass('completed');
                    
                    // Añadir checkmark visual
                    if (!$field.siblings('.field-check').length) {
                        $field.after('<span class="field-check">✓</span>');
                    }
                } else {
                    $field.removeClass('completed');
                    $field.siblings('.field-check').remove();
                }
            });
            
            // Auto-scroll suave a campos con error
            $(document).on('invalid', 'input, select, textarea', function() {
                const $field = $(this);
                
                $('html, body').animate({
                    scrollTop: $field.offset().top - 100
                }, 300);
                
                $field.addClass('shake');
                setTimeout(function() {
                    $field.removeClass('shake');
                }, 500);
            });
            
            // Contador de caracteres para textarea
            $('textarea').each(function() {
                const $textarea = $(this);
                const maxLength = $textarea.attr('maxlength');
                
                if (maxLength) {
                    const $counter = $('<div class="char-counter"></div>');
                    $textarea.after($counter);
                    
                    $textarea.on('input', function() {
                        const remaining = maxLength - $(this).val().length;
                        $counter.text(remaining + ' caracteres restantes');
                        
                        if (remaining < 20) {
                            $counter.addClass('warning');
                        } else {
                            $counter.removeClass('warning');
                        }
                    });
                }
            });
        },
        
        /**
         * Mejoras de accesibilidad
         */
        setupAccessibility: function() {
            // Navegación con teclado mejorada
            let focusableElements = 'input, select, textarea, button, [tabindex]:not([tabindex="-1"])';
            
            $(document).on('keydown', function(e) {
                // Cerrar modal con ESC
                if (e.key === 'Escape' && $('#makia-modal-overlay').is(':visible')) {
                    $('#makia-modal-overlay').fadeOut(300);
                    $('body').css('overflow', 'auto');
                    $('#makia-success-message').hide();
                    $('#makia-booking-form').show();
                }
                
                // Navegación dentro del modal
                if ($('#makia-modal-overlay').is(':visible')) {
                    const $focusable = $('#makia-modal-overlay').find(focusableElements).filter(':visible');
                    const $currentFocus = $(':focus');
                    const currentIndex = $focusable.index($currentFocus);
                    
                    if (e.key === 'Tab') {
                        if (e.shiftKey) {
                            // Tab + Shift (retroceder)
                            if (currentIndex === 0) {
                                e.preventDefault();
                                $focusable.last().focus();
                            }
                        } else {
                            // Tab (avanzar)
                            if (currentIndex === $focusable.length - 1) {
                                e.preventDefault();
                                $focusable.first().focus();
                            }
                        }
                    }
                }
            });
            
            // Anuncios ARIA para lectores de pantalla
            $('form').on('submit', function() {
                MakiaBookingEnhanced.announceToScreenReader('Procesando tu reserva, por favor espera...');
            });
        },
        
        /**
         * Aplicar colores personalizados
         */
        setupCustomColors: function() {
            // Leer colores personalizados de CSS variables
            const primaryColor = getComputedStyle(document.documentElement)
                .getPropertyValue('--makia-primary-color').trim();
            
            const secondaryColor = getComputedStyle(document.documentElement)
                .getPropertyValue('--makia-secondary-color').trim();
            
            if (primaryColor) {
                // Aplicar color primario con opacidad para efectos
                const rgb = this.hexToRgb(primaryColor);
                if (rgb) {
                    document.documentElement.style.setProperty(
                        '--makia-primary-color-transparent',
                        `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.1)`
                    );
                }
            }
        },
        
        /**
         * Estados de carga
         */
        loadingStates: function() {
            // Ocultar loading overlay al recibir respuesta del booking principal
            $(document).on('makia:booking:success makia:booking:error', function() {
                MakiaBookingEnhanced.hideLoadingOverlay();
            });
        },
        
        /**
         * Helpers
         */
        
        showTooltip: function($element, message) {
            const $tooltip = $('<div class="makia-tooltip"></div>').text(message);
            
            $tooltip.css({
                position: 'absolute',
                bottom: '100%',
                left: '50%',
                transform: 'translateX(-50%)',
                background: '#333',
                color: '#fff',
                padding: '8px 12px',
                borderRadius: '4px',
                fontSize: '13px',
                whiteSpace: 'nowrap',
                zIndex: 1000,
                marginBottom: '8px'
            });
            
            $element.parent().css('position', 'relative').append($tooltip);
            
            setTimeout(function() {
                $tooltip.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },
        
        showLoadingOverlay: function() {
            if ($('.makia-loading-overlay').length === 0) {
                const $overlay = $('<div class="makia-loading-overlay"><div class="makia-loading-spinner"></div></div>');
                $('body').append($overlay);
            }
        },
        
        hideLoadingOverlay: function() {
            $('.makia-loading-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        announceToScreenReader: function(message) {
            const $announcement = $('<div role="status" aria-live="polite" class="sr-only"></div>').text(message);
            $('body').append($announcement);
            
            setTimeout(function() {
                $announcement.remove();
            }, 1000);
        },
        
        hexToRgb: function(hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? {
                r: parseInt(result[1], 16),
                g: parseInt(result[2], 16),
                b: parseInt(result[3], 16)
            } : null;
        }
    };
    
    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        MakiaBookingEnhanced.init();
    });
    
    // Exponer funciones públicas
    window.MakiaBookingEnhanced = MakiaBookingEnhanced;
    
})(jQuery);

// Estilos adicionales para las mejoras (se inyectan dinámicamente)
jQuery(document).ready(function($) {
    const styles = `
        <style id="makia-enhanced-styles">
            /* Ripple effect */
            @keyframes ripple {
                to {
                    width: 300px;
                    height: 300px;
                    opacity: 0;
                }
            }
            
            /* Shake effect para errores */
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-10px); }
                75% { transform: translateX(10px); }
            }
            
            .shake {
                animation: shake 0.5s ease;
            }
            
            /* Checkmark de campo completado */
            .field-check {
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                color: #46b450;
                font-weight: bold;
                font-size: 18px;
                pointer-events: none;
                animation: fadeIn 0.3s ease;
            }
            
            /* Contador de caracteres */
            .char-counter {
                font-size: 12px;
                color: #666;
                text-align: right;
                margin-top: 5px;
                transition: color 0.3s ease;
            }
            
            .char-counter.warning {
                color: #dc3232;
                font-weight: 600;
            }
            
            /* Campos válidos e inválidos */
            input.valid {
                border-color: #46b450 !important;
            }
            
            input.invalid {
                border-color: #dc3232 !important;
            }
            
            /* Focus visible mejorado */
            .makia-form-group.focused {
                position: relative;
            }
            
            .makia-form-group.focused::before {
                content: '';
                position: absolute;
                top: -5px;
                left: -5px;
                right: -5px;
                bottom: -5px;
                border: 2px solid rgba(102, 126, 234, 0.3);
                border-radius: 8px;
                pointer-events: none;
            }
            
            /* Screen reader only */
            .sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0,0,0,0);
                white-space: nowrap;
                border-width: 0;
            }
        </style>
    `;
    
    if (!$('#makia-enhanced-styles').length) {
        $('head').append(styles);
    }
});
