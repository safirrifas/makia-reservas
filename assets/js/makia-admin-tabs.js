/**
 * MakIA Admin Panel - Tabs JavaScript
 * Version: 3.2.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Inicializar pestañas
        initTabs();
    });
    
    /**
     * Inicializar sistema de pestañas
     */
    function initTabs() {
        const tabs = $('.makia-tabs-nav button');
        const contents = $('.makia-tab-content');
        
        // Obtener pestaña activa desde URL o usar la primera
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'principal';
        const safeTab = /^[a-z0-9-]+$/.test(activeTab) ? activeTab : 'principal';

        // Activar pestaña inicial
        activateTab(safeTab);
        
        // Manejar clics en pestañas
        tabs.on('click', function() {
            const tabId = $(this).data('tab');
            activateTab(tabId);
            
            // Actualizar URL sin recargar
            const newUrl = updateURLParameter(window.location.href, 'tab', tabId);
            window.history.pushState({path: newUrl}, '', newUrl);
        });
    }
    
    /**
     * Activar una pestaña específica
     */
    function activateTab(tabId) {
        // Desactivar todas las pestañas
        $('.makia-tabs-nav button').removeClass('active');
        $('.makia-tab-content').removeClass('active');
        
        // Activar pestaña seleccionada
        $('[data-tab="' + tabId + '"]').addClass('active');
        $('#makia-tab-' + tabId).addClass('active');
    }
    
    /**
     * Actualizar parámetro en URL
     */
    function updateURLParameter(url, param, paramVal) {
        let newAdditionalURL = "";
        let tempArray = url.split("?");
        let baseURL = tempArray[0];
        let additionalURL = tempArray[1];
        let temp = "";
        
        if (additionalURL) {
            tempArray = additionalURL.split("&");
            for (let i = 0; i < tempArray.length; i++) {
                if (tempArray[i].split('=')[0] != param) {
                    newAdditionalURL += temp + tempArray[i];
                    temp = "&";
                }
            }
        }
        
        let rows_txt = temp + "" + param + "=" + encodeURIComponent(paramVal);
        return baseURL + "?" + newAdditionalURL + rows_txt;
    }
    
})(jQuery);
