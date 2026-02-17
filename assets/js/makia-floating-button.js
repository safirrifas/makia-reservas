/**
 * MakIA Restaurante - Botón Flotante
 * Gestiona la aparición del botón flotante según el scroll
 */

(function($) {
    'use strict';
    
    // Configuración por defecto
    var defaults = {
        scrollOffset: 100,
        position: 'right',
        animation: 'fade',
        showOnMobile: true
    };
    
    // Obtener opciones del backend o usar defaults
    var options = $.extend({}, defaults, (typeof makiaButtonOptions !== 'undefined') ? makiaButtonOptions : {});
    
    // Elementos
    var $floatingButton = null;
    var $window = $(window);
    var isVisible = false;
    var scrollTimeout = null;
    var isMobile = window.innerWidth <= 768;
    var pulseIntervalId = null;
    
    /**
     * Inicializar el botón flotante
     */
    function init() {
        $floatingButton = $('#makia-floating-btn');
        
        if (!$floatingButton.length) {
            return;
        }
        
        // Verificar si debe mostrarse en móvil
        if (isMobile && !options.showOnMobile) {
            $floatingButton.addClass('makia-hide-mobile');
            return;
        }
        
        // Configurar eventos
        bindEvents();
        
        // Verificar posición inicial
        checkScroll();
    }
    
    /**
     * Vincular eventos
     */
    function bindEvents() {
        // Evento de clic para abrir el modal de reservas
        $floatingButton.on('click.makiaFloating', function(e) {
            e.preventDefault();
            openBookingModal();
        });
        
        // Evento de scroll con throttle para mejor rendimiento
        $window.on('scroll.makiaFloating', function() {
            if (scrollTimeout) {
                return;
            }
            
            scrollTimeout = setTimeout(function() {
                checkScroll();
                scrollTimeout = null;
            }, 50);
        });
        
        // Evento de resize para detectar cambios móvil/desktop
        $window.on('resize.makiaFloating', debounce(function() {
            var wasMobile = isMobile;
            isMobile = window.innerWidth <= 768;
            
            if (wasMobile !== isMobile) {
                handleMobileChange();
            }
        }, 250));
        
        // Animación de pulso ocasional para llamar la atención
        if (options.animation !== 'none') {
            pulseIntervalId = setInterval(function() {
                if (isVisible && !$floatingButton.is(':hover')) {
                    pulseAnimation();
                }
            }, 10000);
        }
    }
    
    /**
     * Verificar posición de scroll y mostrar/ocultar botón
     */
    function checkScroll() {
        var scrollTop = $window.scrollTop();
        var offset = parseInt(options.scrollOffset, 10) || 0;
        
        // Si offset es 0, mostrar inmediatamente
        if (offset === 0) {
            showButton();
            return;
        }
        
        if (scrollTop >= offset) {
            if (!isVisible) {
                showButton();
            }
        } else {
            if (isVisible) {
                hideButton();
            }
        }
    }
    
    /**
     * Mostrar el botón flotante
     */
    function showButton() {
        if (!$floatingButton.length) return;
        
        $floatingButton
            .removeClass('makia-floating-hidden')
            .addClass('makia-floating-visible');
        
        isVisible = true;
    }
    
    /**
     * Ocultar el botón flotante
     */
    function hideButton() {
        if (!$floatingButton.length) return;
        
        $floatingButton
            .removeClass('makia-floating-visible')
            .addClass('makia-floating-hidden');
        
        isVisible = false;
    }
    
    /**
     * Animación de pulso para llamar la atención
     */
    function pulseAnimation() {
        if (!$floatingButton.length) return;
        
        $floatingButton.addClass('makia-pulse');
        
        setTimeout(function() {
            $floatingButton.removeClass('makia-pulse');
        }, 600);
    }
    
    /**
     * Manejar cambio entre móvil y desktop
     */
    function handleMobileChange() {
        if (isMobile && !options.showOnMobile) {
            $floatingButton.addClass('makia-hide-mobile');
            hideButton();
        } else {
            $floatingButton.removeClass('makia-hide-mobile');
            checkScroll();
        }
    }
    
    /**
     * Abrir el modal de reservas
     */
    function openBookingModal() {
        // Usar la función centralizada de makia-booking.js si está disponible
        if (typeof window.makiaOpenModal === 'function') {
            window.makiaOpenModal();
            return;
        }

        // Fallback: abrir modal directamente con clase CSS
        var $modal = $('#makia-modal-overlay');
        if ($modal.length) {
            $modal.addClass('active');
            $('body').css('overflow', 'hidden');
            return;
        }
    }
    
    /**
     * Función debounce para optimizar eventos
     */
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }
    
    /**
     * Destruir eventos (para limpieza)
     */
    function destroy() {
        $window.off('scroll.makiaFloating');
        $window.off('resize.makiaFloating');
        if ($floatingButton && $floatingButton.length) {
            $floatingButton.off('click.makiaFloating');
            $floatingButton
                .removeClass('makia-floating-visible makia-floating-hidden makia-pulse')
                .removeAttr('style');
        }
        clearInterval(pulseIntervalId);
    }
    
    // API pública
    window.MakiaFloatingButton = {
        init: init,
        show: showButton,
        hide: hideButton,
        destroy: destroy,
        isVisible: function() { return isVisible; }
    };
    
    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        init();
    });
    
    // También inicializar en window load por si hay imágenes que afecten el layout
    $(window).on('load', function() {
        // Re-verificar después de que todo cargue
        setTimeout(checkScroll, 100);
    });
    
})(jQuery);

/**
 * Estilos adicionales para animación de pulso
 * (inyectados dinámicamente)
 */
(function() {
    var style = document.createElement('style');
    style.textContent = `
        @keyframes makia-pulse-animation {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .makia-floating-button.makia-pulse {
            animation: makia-pulse-animation 0.6s ease-in-out;
        }
        
        .makia-floating-button.makia-floating-center.makia-pulse {
            animation: makia-pulse-center-animation 0.6s ease-in-out;
        }
        
        @keyframes makia-pulse-center-animation {
            0% { transform: translateX(-50%) scale(1); }
            50% { transform: translateX(-50%) scale(1.05); }
            100% { transform: translateX(-50%) scale(1); }
        }
    `;
    document.head.appendChild(style);
})();
