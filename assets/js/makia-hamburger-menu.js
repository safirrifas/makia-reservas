/**
 * Menú Hamburguesa MakIA - Desplegable y Pegable
 * Versión mejorada con soporte táctil para móviles
 * Corregido: problema de desplazamiento de página en móvil
 */

jQuery(document).ready(function($) {
    // Estado del menú (guardado en localStorage)
    let isPinned = localStorage.getItem('makia_menu_pinned') === 'true';
    let isOpen = false; // Siempre cerrado por defecto
    let scrollPosition = 0; // Guardar posición de scroll
    
    // Elementos
    const $hamburgerBtn = $('.makia-hamburger-btn');
    const $sidebar = $('.makia-sidebar');
    const $overlay = $('.makia-overlay');
    const $pinBtn = $('.makia-pin-btn');
    const $mainContent = $('.makia-main-content');
    const $navButtons = $('.makia-sidebar-nav button');
    const $tabContents = $('.makia-tab-content');
    const $body = $('body');
    const $html = $('html');
    
    // Detectar si es dispositivo táctil
    const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    
    // Función para bloquear scroll del body (mejor método)
    function lockBodyScroll() {
        scrollPosition = window.pageYOffset;
        $body.css({
            'overflow': 'hidden',
            'position': 'fixed',
            'top': -scrollPosition + 'px',
            'width': '100%'
        });
        $html.css('overflow', 'hidden');
    }
    
    // Función para desbloquear scroll del body
    function unlockBodyScroll() {
        $body.css({
            'overflow': '',
            'position': '',
            'top': '',
            'width': ''
        });
        $html.css('overflow', '');
        window.scrollTo(0, scrollPosition);
    }
    
    // Inicializar estado
    if (isPinned && $(window).width() > 782) {
        $sidebar.addClass('pinned open');
        $pinBtn.addClass('pinned').html('📌');
        $mainContent.css('margin-left', '280px');
        isOpen = true;
    } else {
        // Asegurar que esté cerrado por defecto
        $sidebar.removeClass('open pinned');
        $overlay.removeClass('show');
        $mainContent.css('margin-left', '0');
        isPinned = false;
    }
    
    // Función para abrir menú
    function openMenu() {
        isOpen = true;
        $sidebar.addClass('open');
        
        if (!isPinned) {
            $overlay.addClass('show');
        }
        
        // En móvil, bloquear scroll del body
        if ($(window).width() <= 782) {
            lockBodyScroll();
        }
    }
    
    // Función para cerrar menú
    function closeMenu() {
        if (isPinned && $(window).width() > 782) {
            return; // No cerrar si está pegado en escritorio
        }
        
        isOpen = false;
        $sidebar.removeClass('open');
        $overlay.removeClass('show');
        
        // Desbloquear scroll del body
        unlockBodyScroll();
    }
    
    // Función para toggle menú
    function toggleMenu(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    }
    
    // Toggle menú hamburguesa - eventos click y touch
    $hamburgerBtn.on('click', function(e) {
        toggleMenu(e);
    });
    
    // Soporte táctil mejorado
    if (isTouchDevice) {
        $hamburgerBtn.on('touchend', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu(e);
        });
        
        // Prevenir que touchstart cause problemas
        $hamburgerBtn.on('touchstart', function(e) {
            e.stopPropagation();
        });
    }
    
    // Cerrar menú al hacer click/touch en overlay
    $overlay.on('click', function(e) {
        if (!isPinned) {
            e.preventDefault();
            closeMenu();
        }
    });
    
    if (isTouchDevice) {
        $overlay.on('touchend', function(e) {
            if (!isPinned) {
                e.preventDefault();
                closeMenu();
            }
        });
    }
    
    // Toggle pin/unpin
    $pinBtn.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // No permitir pin en móvil
        if ($(window).width() <= 782) {
            return;
        }
        
        isPinned = !isPinned;
        
        if (isPinned) {
            $sidebar.addClass('pinned');
            $pinBtn.addClass('pinned').html('📌');
            $overlay.removeClass('show');
            $mainContent.css('margin-left', '280px');
            localStorage.setItem('makia_menu_pinned', 'true');
        } else {
            $sidebar.removeClass('pinned');
            $pinBtn.removeClass('pinned').html('📍');
            $mainContent.css('margin-left', '0');
            localStorage.setItem('makia_menu_pinned', 'false');
        }
    });
    
    // Navegación entre pestañas
    $navButtons.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const tabId = $(this).data('tab');
        
        // Actualizar botones activos
        $navButtons.removeClass('active');
        $(this).addClass('active');
        
        // Mostrar contenido correspondiente
        $tabContents.removeClass('active');
        $('#makia-tab-' + tabId).addClass('active');
        
        // Cerrar menú si no está pegado o si estamos en móvil
        if (!isPinned || $(window).width() <= 782) {
            closeMenu();
        }
        
        // Guardar pestaña activa
        localStorage.setItem('makia_active_tab', tabId);
    });
    
    // Soporte táctil para navegación
    if (isTouchDevice) {
        $navButtons.on('touchend', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).trigger('click');
        });
    }
    
    // Restaurar pestaña activa desde URL o localStorage
    const urlParams = new URLSearchParams(window.location.search);
    const tabFromUrl = urlParams.get('tab');
    const activeTab = tabFromUrl || localStorage.getItem('makia_active_tab') || 'principal';
    
    // Activar pestaña sin disparar evento completo
    $navButtons.removeClass('active');
    $navButtons.filter('[data-tab="' + activeTab + '"]').addClass('active');
    $tabContents.removeClass('active');
    $('#makia-tab-' + activeTab).addClass('active');
    
    // Cerrar menú con ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && isOpen && !isPinned) {
            closeMenu();
        }
    });
    
    // Responsive: cerrar menú pegado en móvil
    $(window).on('resize', function() {
        if ($(window).width() <= 782) {
            if (isPinned) {
                $sidebar.removeClass('pinned');
                $pinBtn.removeClass('pinned').html('📍');
                $mainContent.css('margin-left', '0');
                isPinned = false;
                localStorage.setItem('makia_menu_pinned', 'false');
            }
            // Ocultar botón pin en móvil
            $pinBtn.hide();
            
            // Si el menú está abierto, asegurar que el scroll esté bloqueado
            if (isOpen) {
                lockBodyScroll();
            }
        } else {
            $pinBtn.show();
            // En escritorio, desbloquear scroll si estaba bloqueado
            if (!isOpen || isPinned) {
                unlockBodyScroll();
            }
        }
    });
    
    // Trigger resize inicial para configurar móvil
    $(window).trigger('resize');
    
    // Prevenir que los toques en el sidebar cierren el menú
    $sidebar.on('touchstart touchmove', function(e) {
        e.stopPropagation();
    });
    
    // Permitir scroll dentro del sidebar en móvil
    $sidebar.on('touchmove', function(e) {
        // Solo prevenir si el scroll ha llegado al límite
        const $this = $(this);
        const scrollTop = $this.scrollTop();
        const scrollHeight = $this[0].scrollHeight;
        const height = $this.height();
        
        // Si está en el tope y quiere subir, o en el fondo y quiere bajar, prevenir
        if ((scrollTop <= 0 && e.originalEvent.touches[0].clientY > e.originalEvent.touches[0].clientY) ||
            (scrollTop + height >= scrollHeight && e.originalEvent.touches[0].clientY < e.originalEvent.touches[0].clientY)) {
            // Permitir el scroll normal dentro del sidebar
        }
    });
});
