<!-- Botón para abrir el modal de reservas -->
<?php
if (!defined('ABSPATH')) { exit; }
$style = get_option('makia_style_theme', 'gold');
$button_text = get_option('makia_button_text', 'Reservar Mesa');
$restaurant_name = get_option('makia_restaurant_name', 'Restaurante Brote');

// Obtener plantilla y personalizaciones
$active_template = get_option('makia_active_template', 'modern');
$customizations = array(
    'primary_color' => get_option('makia_primary_color', ''),
    'secondary_color' => get_option('makia_secondary_color', ''),
    'text_color' => get_option('makia_text_color', ''),
    'logo_url' => get_option('makia_logo_url', ''),
    'form_title' => get_option('makia_form_title', 'Reserva tu Mesa'),
    'form_subtitle' => get_option('makia_form_subtitle', 'Completa el formulario y confirmaremos tu reserva'),
    'button_text' => get_option('makia_submit_button_text', 'Confirmar Reserva')
);
?>

<div class="makia-booking-wrapper makia-style-<?php echo esc_attr($style); ?>">
    <button type="button" class="makia-reserve-button" id="makia-open-modal">
        <?php echo esc_html($button_text); ?>
    </button>
</div>

<!-- Estilos personalizados en línea -->
<style>
<?php if ($customizations['primary_color']): ?>
.makia-template-<?php echo esc_attr($active_template); ?> button[type="submit"],
.makia-template-<?php echo esc_attr($active_template); ?> .makia-submit-btn {
    background: <?php echo esc_attr($customizations['primary_color']); ?> !important;
}
.makia-template-<?php echo esc_attr($active_template); ?> button[type="submit"]:hover,
.makia-template-<?php echo esc_attr($active_template); ?> .makia-submit-btn:hover {
    background: <?php echo esc_attr($customizations['primary_color']); ?> !important;
    filter: brightness(1.1);
}
.makia-template-<?php echo esc_attr($active_template); ?> input:focus,
.makia-template-<?php echo esc_attr($active_template); ?> select:focus,
.makia-template-<?php echo esc_attr($active_template); ?> textarea:focus {
    border-color: <?php echo esc_attr($customizations['primary_color']); ?> !important;
}
<?php endif; ?>

<?php if ($customizations['secondary_color']): ?>
.makia-template-<?php echo esc_attr($active_template); ?> {
    background: <?php echo esc_attr($customizations['secondary_color']); ?> !important;
}
<?php endif; ?>

<?php if ($customizations['text_color']): ?>
.makia-template-<?php echo esc_attr($active_template); ?> label,
.makia-template-<?php echo esc_attr($active_template); ?> .makia-form-title {
    color: <?php echo esc_attr($customizations['text_color']); ?> !important;
}
<?php endif; ?>

/* Animaciones mejoradas */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

.makia-modal-overlay {
    animation: fadeIn 0.3s ease;
}

.makia-modal-container {
    animation: slideInUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.makia-form-group {
    animation: slideInUp 0.3s ease forwards;
    opacity: 0;
}

.makia-form-row:nth-child(1) .makia-form-group { animation-delay: 0.1s; }
.makia-form-row:nth-child(2) .makia-form-group { animation-delay: 0.15s; }
.makia-form-row:nth-child(3) .makia-form-group { animation-delay: 0.2s; }
.makia-form-row:nth-child(4) .makia-form-group { animation-delay: 0.25s; }
.makia-form-row:nth-child(5) .makia-form-group { animation-delay: 0.3s; }
.makia-form-row:nth-child(6) .makia-form-group { animation-delay: 0.35s; }

.makia-submit-btn:active {
    animation: pulse 0.3s ease;
}

/* Micro-interacciones */
.makia-form-group input:focus,
.makia-form-group select:focus,
.makia-form-group textarea:focus {
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.makia-submit-btn {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.makia-submit-btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.makia-submit-btn:hover::before {
    width: 300px;
    height: 300px;
}

.makia-submit-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

/* Loading state mejorado */
.makia-submit-btn.loading {
    pointer-events: none;
    opacity: 0.7;
}

.makia-submit-btn.loading .makia-btn-text {
    display: none;
}

.makia-submit-btn.loading .makia-btn-loading {
    display: inline-block;
}

.makia-btn-loading {
    display: none;
}

.makia-btn-loading::after {
    content: '';
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-left: 8px;
    vertical-align: middle;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Botón de cierre mejorado */
.makia-modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 40px;
    height: 40px;
    border: none;
    background: rgba(0, 0, 0, 0.1);
    color: #333;
    font-size: 24px;
    border-radius: 50%;
    cursor: pointer;
    z-index: 10;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.makia-modal-close:hover {
    background: rgba(0, 0, 0, 0.2);
    transform: rotate(90deg) scale(1.1);
}

/* Responsive mejorado */
@media (max-width: 768px) {
    .makia-modal-container {
        margin: 10px;
        max-height: calc(100vh - 20px);
        overflow-y: auto;
    }
    
    .makia-template-<?php echo esc_attr($active_template); ?> {
        padding: 25px 20px !important;
    }
    
    .makia-form-logo img {
        max-width: 150px !important;
        max-height: 60px !important;
    }
}

@media (max-width: 480px) {
    .makia-form-row {
        flex-direction: column !important;
    }
    
    .makia-form-group {
        width: 100% !important;
        margin-bottom: 20px !important;
    }
    
    .makia-template-<?php echo esc_attr($active_template); ?> {
        padding: 20px 15px !important;
    }
}
</style>

<!-- Modal de Reservas -->
<div class="makia-modal-overlay makia-style-<?php echo esc_attr($style); ?>" id="makia-modal-overlay">
    <div class="makia-modal-container">
        <!-- Botón de cierre mejorado -->
        <button type="button" class="makia-modal-close" id="makia-close-modal" aria-label="Cerrar">
            ✕
        </button>
        
        <!-- Cuerpo del modal con plantilla aplicada -->
        <div class="makia-modal-body">
            <div class="makia-template-<?php echo esc_attr($active_template); ?>" style="position: relative;">
                
                <?php if ($customizations['logo_url']): ?>
                <div class="makia-form-logo" style="text-align: center; margin-bottom: 25px;">
                    <img src="<?php echo esc_url($customizations['logo_url']); ?>" alt="<?php echo esc_attr($restaurant_name); ?>" style="max-width: 200px; max-height: 80px; height: auto;">
                </div>
                <?php endif; ?>
                
                <h2 class="makia-form-title"><?php echo esc_html($customizations['form_title']); ?></h2>
                <p class="makia-form-subtitle"><?php echo esc_html($customizations['form_subtitle']); ?></p>
                
                <div id="makia-booking-messages"></div>
                
                <form id="makia-booking-form" class="makia-booking-form">
                    <div class="makia-form-row" style="display: flex; gap: 15px;">
                        <div class="makia-form-group" style="flex: 1;">
                            <label for="makia-name">Nombre *</label>
                            <input type="text" id="makia-name" name="name" placeholder="Tu nombre" required>
                        </div>
                        
                        <div class="makia-form-group" style="flex: 1;">
                            <label for="makia-phone">Teléfono *</label>
                            <input type="tel" id="makia-phone" name="phone" placeholder="600 123 456" required>
                        </div>
                    </div>
                    
                    <div class="makia-form-row" style="display: flex; gap: 15px;">
                        <div class="makia-form-group" style="flex: 1;">
                            <label for="makia-email">Email *</label>
                            <input type="email" id="makia-email" name="email" placeholder="tu@email.com" required>
                        </div>
                        
                        <div class="makia-form-group" style="flex: 1;">
                            <label for="makia-guests">Personas *</label>
                            <input type="number" id="makia-guests" name="guests" min="1" max="20" value="2" required>
                        </div>
                    </div>
                    
                    <div class="makia-form-row" style="display: flex; gap: 15px;">
                        <div class="makia-form-group" style="flex: 1;">
                            <label for="makia-date">Fecha *</label>
                            <input type="date" id="makia-date" name="date" required>
                        </div>
                        
                        <div class="makia-form-group" style="flex: 1;">
                            <label for="makia-time">Hora *</label>
                            <select id="makia-time" name="time" required>
                                <option value="">Elige hora...</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="makia-form-row" style="display: flex; gap: 15px;">
                        <div class="makia-form-group" style="flex: 1;">
                            <label for="makia-reason">Motivo *</label>
                            <select id="makia-reason" name="reason" required>
                                <option value="">Selecciona...</option>
                                <option value="Degustar Cochinillo">Degustar Cochinillo</option>
                                <option value="Cumpleaños">Cumpleaños</option>
                                <option value="Aniversario">Aniversario</option>
                                <option value="Negocios">Negocios</option>
                                <option value="Familiar">Familiar</option>
                                <option value="Amigos">Con amigos</option>
                                <option value="Cena romántica">Cena romántica</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        
                        <div class="makia-form-group" style="flex: 1;">
                            <label for="makia-highchair">Trona/Carrito</label>
                            <select id="makia-highchair" name="highchair">
                                <option value="no">No necesito</option>
                                <option value="trona">Necesito trona</option>
                                <option value="carrito">Espacio para carrito</option>
                                <option value="ambos">Trona + espacio carrito</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="makia-form-group">
                        <label for="makia-comments">Comentarios</label>
                        <textarea id="makia-comments" name="comments" rows="2" placeholder="Alergias, peticiones especiales..."></textarea>
                    </div>
                    
                    <div class="makia-form-group makia-checkbox">
                        <label>
                            <input type="checkbox" id="makia-legal" name="legal" required>
                            <?php
                            $legal_page_id = get_option('makia_legal_page', 0);
                            $privacy_page_id = get_option('makia_privacy_page', 0);
                            $legal_url = $legal_page_id ? get_permalink($legal_page_id) : '';
                            $privacy_url = $privacy_page_id ? get_permalink($privacy_page_id) : get_privacy_policy_url();
                            
                            if ($legal_url && $privacy_url) {
                                echo 'Acepto el <a href="' . esc_url($legal_url) . '" target="_blank">Aviso Legal</a> y <a href="' . esc_url($privacy_url) . '" target="_blank">Privacidad</a> *';
                            } elseif ($privacy_url) {
                                echo 'Acepto la <a href="' . esc_url($privacy_url) . '" target="_blank">Política de Privacidad</a> *';
                            } else {
                                echo 'Acepto los términos y condiciones *';
                            }
                            ?>
                        </label>
                    </div>
                    
                    <button type="submit" class="makia-submit-btn" id="makia-submit-btn">
                        <span class="makia-btn-text"><?php echo esc_html($customizations['button_text']); ?></span>
                        <span class="makia-btn-loading">Procesando</span>
                    </button>
                </form>
                
                <div id="makia-success-message" class="makia-success-container" style="display: none;">
                    <div class="makia-success-icon" style="font-size: 64px; color: #46b450; text-align: center; margin-bottom: 20px;">✓</div>
                    <h3 style="text-align: center; color: #333; margin-bottom: 15px;">¡Reserva Recibida!</h3>
                    <p style="text-align: center; color: #666; margin-bottom: 20px;">Recibirás un email de confirmación de <?php echo esc_html($restaurant_name); ?>.</p>
                    <div id="makia-reservation-details"></div>
                    <button type="button" class="makia-new-booking-btn" onclick="makiaResetForm()" style="width: 100%; padding: 14px; background: #667eea; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 20px; transition: all 0.3s;">Nueva reserva</button>
                </div>
            </div>
        </div>
        
        <!-- Pie del modal minimalista -->
        <div class="makia-modal-footer makia-compact-footer" style="text-align: center; padding: 15px; font-size: 12px; color: #999;">
            <a href="https://contacpro.app" target="_blank" rel="noopener" style="color: #667eea; text-decoration: none;">Powered by MakIA</a>
        </div>
    </div>
</div>
