/**
 * MakIA Admin Design Panel JavaScript
 * Maneja la interactividad del panel de diseño, vista previa y personalización
 */

(function($) {
    'use strict';
    
    const MakiaDesignPanel = {
        
        /**
         * Inicializar
         */
        init: function() {
            this.bindEvents();
            this.loadPreview();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Seleccionar plantilla
            $('.makia-select-template').on('click', this.selectTemplate.bind(this));
            
            // Hover effects en tarjetas
            $('.makia-template-card').hover(
                this.onCardHoverIn.bind(this),
                this.onCardHoverOut.bind(this)
            );
            
            // Vista previa desktop/mobile
            $('.makia-preview-btn').on('click', this.togglePreviewMode.bind(this));
            
            // Personalización
            $('#makia-logo-upload').on('click', this.uploadLogo.bind(this));
            $('#makia-save-customization').on('click', this.saveCustomization.bind(this));
            
            // Actualizar preview al cambiar opciones
            $('#makia-custom-title, #makia-custom-subtitle, #makia-button-text').on('input', 
                _.debounce(this.updatePreview.bind(this), 500)
            );
            
            $('#makia-primary-color, #makia-secondary-color, #makia-text-color').on('change', 
                this.updatePreview.bind(this)
            );
        },
        
        /**
         * Seleccionar plantilla
         */
        selectTemplate: function(e) {
            const $button = $(e.currentTarget);
            const templateId = $button.data('template');
            const $card = $button.closest('.makia-template-card');
            
            // Si ya está activa, no hacer nada
            if ($card.hasClass('active')) {
                return;
            }
            
            // Mostrar loading
            this.showLoading();
            $button.prop('disabled', true).html('<span class="makia-loading-spinner"></span> Aplicando...');
            
            // Llamada AJAX
            $.ajax({
                url: makiaDesignData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'makia_save_template',
                    template_id: templateId,
                    nonce: makiaDesignData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Actualizar UI
                        this.updateActiveTemplate(templateId, $card);
                        this.loadPreview(templateId);
                        this.showNotification('✓ Plantilla aplicada correctamente', 'success');
                    } else {
                        this.showNotification('Error: ' + response.data.message, 'error');
                        $button.prop('disabled', false).text('Seleccionar Plantilla');
                    }
                    this.hideLoading();
                },
                error: () => {
                    this.showNotification('Error al guardar la plantilla', 'error');
                    $button.prop('disabled', false).text('Seleccionar Plantilla');
                    this.hideLoading();
                }
            });
        },
        
        /**
         * Actualizar plantilla activa en la UI
         */
        updateActiveTemplate: function(templateId, $newActiveCard) {
            // Remover activo de todas las tarjetas
            $('.makia-template-card').removeClass('active').css('border-color', '#e0e0e0');
            $('.makia-template-badge').remove();
            $('.makia-select-template')
                .removeClass('active')
                .text('Seleccionar Plantilla');
            
            // Activar la nueva
            $newActiveCard.addClass('active').css('border-color', '#667eea');
            $newActiveCard.prepend(`
                <div class="makia-template-badge">
                    ✓ ACTIVA
                </div>
            `);
            
            $newActiveCard.find('.makia-select-template')
                .addClass('active')
                .text('✓ Plantilla Activa');
            
            // Actualizar datos globales
            makiaDesignData.activeTemplate = templateId;
        },
        
        /**
         * Hover en tarjeta - entrada
         */
        onCardHoverIn: function(e) {
            const $card = $(e.currentTarget);
            if (!$card.hasClass('active')) {
                $card.css({
                    'transform': 'translateY(-5px)',
                    'box-shadow': '0 10px 30px rgba(0,0,0,0.12)',
                    'border-color': '#667eea'
                });
            }
        },
        
        /**
         * Hover en tarjeta - salida
         */
        onCardHoverOut: function(e) {
            const $card = $(e.currentTarget);
            if (!$card.hasClass('active')) {
                $card.css({
                    'transform': 'translateY(0)',
                    'box-shadow': 'none',
                    'border-color': '#e0e0e0'
                });
            }
        },
        
        /**
         * Toggle modo de vista previa
         */
        togglePreviewMode: function(e) {
            const $btn = $(e.currentTarget);
            const mode = $btn.data('view');
            
            $('.makia-preview-btn').removeClass('active');
            $btn.addClass('active');
            
            const $frame = $('#makia-preview-frame');
            if (mode === 'mobile') {
                $frame.addClass('mobile');
            } else {
                $frame.removeClass('mobile');
            }
        },
        
        /**
         * Cargar vista previa
         */
        loadPreview: function(templateId = null) {
            const template = templateId || makiaDesignData.activeTemplate;
            
            $.ajax({
                url: makiaDesignData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'makia_get_preview',
                    template_id: template,
                    nonce: makiaDesignData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#makia-preview-frame').html(response.data.html);
                        this.applyCustomStyling();
                    }
                },
                error: () => {
                    $('#makia-preview-frame').html(
                        '<div style="text-align: center; padding: 60px 20px; color: #dc3545;">' +
                        '<p>Error al cargar la vista previa</p>' +
                        '</div>'
                    );
                }
            });
        },
        
        /**
         * Actualizar vista previa
         */
        updatePreview: function() {
            this.loadPreview();
        },
        
        /**
         * Aplicar estilos personalizados a la vista previa
         */
        applyCustomStyling: function() {
            const template = makiaDesignData.activeTemplate;
            const primaryColor = $('#makia-primary-color').val();
            const secondaryColor = $('#makia-secondary-color').val();
            const textColor = $('#makia-text-color').val();
            
            // Crear o actualizar tag de estilo
            let $style = $('#makia-custom-preview-styles');
            if ($style.length === 0) {
                $style = $('<style id="makia-custom-preview-styles"></style>');
                $('head').append($style);
            }
            
            const customCSS = `
                .makia-template-${template} button.makia-preview-button {
                    background: ${primaryColor} !important;
                }
                .makia-template-${template} input:focus,
                .makia-template-${template} select:focus,
                .makia-template-${template} textarea:focus {
                    border-color: ${primaryColor} !important;
                }
                .makia-template-${template} {
                    background: ${secondaryColor} !important;
                }
                .makia-template-${template} .makia-form-title {
                    color: ${textColor} !important;
                }
            `;
            
            $style.html(customCSS);
        },
        
        /**
         * Subir logo
         */
        uploadLogo: function() {
            // Crear media uploader
            const mediaUploader = wp.media({
                title: 'Seleccionar Logo',
                button: {
                    text: 'Usar este logo'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            // Cuando se selecciona una imagen
            mediaUploader.on('select', () => {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                
                // Actualizar preview
                $('#makia-logo-upload').html(`
                    <div class="makia-logo-preview">
                        <img src="${attachment.url}" alt="Logo">
                    </div>
                `);
                
                // Guardar URL
                $('#makia-logo-url').val(attachment.url);
                
                // Actualizar vista previa
                this.updatePreview();
            });
            
            // Abrir media uploader
            mediaUploader.open();
        },
        
        /**
         * Guardar personalización
         */
        saveCustomization: function() {
            const $button = $('#makia-save-customization');
            
            // Recopilar datos
            const data = {
                action: 'makia_save_customization',
                nonce: makiaDesignData.nonce,
                logo_url: $('#makia-logo-url').val(),
                custom_title: $('#makia-custom-title').val(),
                custom_subtitle: $('#makia-custom-subtitle').val(),
                primary_color: $('#makia-primary-color').val(),
                secondary_color: $('#makia-secondary-color').val(),
                text_color: $('#makia-text-color').val(),
                button_text: $('#makia-button-text').val(),
                show_logo: true
            };
            
            // Mostrar loading
            this.showLoading();
            $button.prop('disabled', true).html('<span class="makia-loading-spinner"></span> Guardando...');
            
            // Llamada AJAX
            $.ajax({
                url: makiaDesignData.ajaxurl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showNotification('✓ Personalización guardada correctamente', 'success');
                        this.updatePreview();
                        makiaDesignData.customization = response.data.options;
                    } else {
                        this.showNotification('Error: ' + response.data.message, 'error');
                    }
                    $button.prop('disabled', false).html('💾 Guardar Personalización');
                    this.hideLoading();
                },
                error: () => {
                    this.showNotification('Error al guardar la personalización', 'error');
                    $button.prop('disabled', false).html('💾 Guardar Personalización');
                    this.hideLoading();
                }
            });
        },
        
        /**
         * Mostrar notificación
         */
        showNotification: function(message, type = 'success') {
            const bgColor = type === 'success' ? '#46b450' : '#dc3545';
            
            // Crear notificación
            const $notification = $(`
                <div class="makia-admin-notification" style="
                    position: fixed;
                    top: 40px;
                    right: 40px;
                    background: ${bgColor};
                    color: white;
                    padding: 18px 24px;
                    border-radius: 8px;
                    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
                    z-index: 999999;
                    font-weight: 600;
                    font-size: 15px;
                    animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                ">
                    ${message}
                </div>
            `);
            
            $('body').append($notification);
            
            // Eliminar después de 3 segundos
            setTimeout(() => {
                $notification.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 3000);
        },
        
        /**
         * Mostrar overlay de loading
         */
        showLoading: function() {
            if ($('#makia-loading-overlay').length === 0) {
                $('body').append(`
                    <div id="makia-loading-overlay" class="makia-loading-overlay">
                        <div class="makia-loading-spinner"></div>
                    </div>
                `);
            }
        },
        
        /**
         * Ocultar overlay de loading
         */
        hideLoading: function() {
            $('#makia-loading-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        }
    };
    
    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        // Solo inicializar si estamos en la pestaña de diseño
        if ($('.makia-design-tab').length > 0) {
            MakiaDesignPanel.init();
        }
    });
    
    // Agregar animaciones CSS
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            .makia-loading-spinner {
                width: 50px;
                height: 50px;
                border: 4px solid #e0e0e0;
                border-top-color: #667eea;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }
            
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        `)
        .appendTo('head');
    
})(jQuery);

// Agregar lodash debounce si no existe
if (typeof _ === 'undefined') {
    window._ = {
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
}
