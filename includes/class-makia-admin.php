<?php
/**
 * Clase principal de administración MakIA
 * Versión con menú hamburguesa desplegable/pegable
 */

class MakIA_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'MakIA Reservas',
            'MakIA Reservas',
            'makia_manage_bookings',
            'makia',
            array($this, 'render_main_page'),
            'dashicons-calendar-alt',
            30
        );
        
        // Subpágina: Operarios
        add_submenu_page(
            'makia',
            'Operarios',
            'Operarios',
            'manage_options', // Solo el admin gestiona operarios
            'makia-operators',
            array('MakIA_Operators', 'render_operators_page')
        );
        
        // Subpágina: Auditoría
        add_submenu_page(
            'makia',
            'Auditoría',
            'Auditoría',
            'makia_view_audit', // Operarios pueden ver auditoría si tienen la cap
            'makia-audit',
            array('MakIA_Audit', 'render_audit_page')
        );
    }
    
    /**
     * Cargar assets del admin
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_makia') {
            return;
        }
        
        // CSS mejorado
        wp_enqueue_style(
            'makia-admin-improved',
            MAKIA_PLUGIN_URL . 'assets/css/makia-admin-improved.css',
            array(),
            MAKIA_VERSION
        );
        
        // CSS del menú hamburguesa
        wp_enqueue_style(
            'makia-hamburger-menu',
            MAKIA_PLUGIN_URL . 'assets/css/makia-hamburger-menu.css',
            array(),
            MAKIA_VERSION
        );
        
        // JavaScript del menú hamburguesa
        wp_enqueue_script(
            'makia-hamburger-menu',
            MAKIA_PLUGIN_URL . 'assets/js/makia-hamburger-menu.js',
            array('jquery'),
            MAKIA_VERSION,
            true
	        );
	        
	        // JavaScript de administración
	        wp_enqueue_script(
	            'makia-admin',
	            MAKIA_PLUGIN_URL . 'assets/js/makia-admin.js',
	            array('jquery'),
	            MAKIA_VERSION,
	            true
	        );
	        
	        // JavaScript de notas de reserva
	        wp_enqueue_script(
	            'makia-booking-notes',
	            MAKIA_PLUGIN_URL . 'assets/js/makia-booking-notes.js',
	            array('jquery'),
	            MAKIA_VERSION,
	            true
	        );
	        
	        // Localizar scripts de administración
	        wp_localize_script('makia-admin', 'makiaAdminConfig', array(
	            'ajaxUrl' => admin_url('admin-ajax.php'),
	            'adminNonce' => wp_create_nonce('makia_admin_nonce'),
	            'noteNonce' => wp_create_nonce('makia_add_note_action') // Usar el nonce de añadir nota para ambos
	        ));
	        
	        // Localizar scripts de notas
	        wp_localize_script('makia-booking-notes', 'makiaAdminConfig', array(
	            'ajaxUrl' => admin_url('admin-ajax.php'),
	            'noteNonce' => wp_create_nonce('makia_add_note_action')
	        ));
    }
    
    /**
     * Página principal con menú hamburguesa
     */
    public function render_main_page() {
        global $makia_license_manager;
        
        // Obtener información de licencia
        $license_key = get_option('makia_license_key', '');
        $license_status = get_option('makia_license_status', 'inactive');
        $license_info = $makia_license_manager->get_license_info();
        
        // Determinar plan actual
        $current_plan = $this->get_current_plan($license_info);
        
        ?>
        <!-- Botón Hamburguesa -->
        <button class="makia-hamburger-btn">
            <span class="dashicons dashicons-menu"></span>
        </button>
        
        <!-- Overlay -->
        <div class="makia-overlay"></div>
        
        <div class="makia-admin-wrap">
            <!-- Sidebar del Menú -->
            <div class="makia-sidebar">
                <div class="makia-sidebar-header">
                    <h2>MakIA Reservas</h2>
                    <button class="makia-pin-btn" title="Pegar/Despegar menú">📍</button>
                </div>
                <nav class="makia-sidebar-nav">
                    <ul>
                        <li><button class="active" data-tab="principal"><span class="dashicons dashicons-admin-home"></span> Principal</button></li>
                        <li><button data-tab="reservations"><span class="dashicons dashicons-calendar-alt"></span> Reservas</button></li>
                        <li><button data-tab="settings"><span class="dashicons dashicons-admin-generic"></span> Configuración</button></li>
                        <li><button data-tab="appearance"><span class="dashicons dashicons-admin-appearance"></span> Apariencia</button></li>
                        <li><button data-tab="schedule"><span class="dashicons dashicons-clock"></span> Horarios</button></li>
                        <li><button data-tab="special-days"><span class="dashicons dashicons-calendar"></span> Días Especiales</button></li>
                        <li><button data-tab="capacity"><span class="dashicons dashicons-clock"></span> Capacidad</button></li>
                        <li><button data-tab="design"><span class="dashicons dashicons-admin-customizer"></span> Diseño</button></li>
                        <li><button data-tab="button"><span class="dashicons dashicons-button"></span> Botón Reservas</button></li>
                        <li><button data-tab="templates"><span class="dashicons dashicons-email"></span> Plantillas</button></li>
                        <li><button data-tab="blacklist"><span class="dashicons dashicons-dismiss"></span> Lista Negra</button></li>
                        <li><button data-tab="license"><span class="dashicons dashicons-admin-network"></span> Licencia</button></li>
                    </ul>
                </nav>
            </div>
            
            <!-- Contenido Principal -->
            <div class="makia-main-content">
                <!-- Header Mejorado con Logo -->
                <div class="makia-admin-header">
                    <div class="makia-admin-header-logo">
                        <img src="<?php echo MAKIA_PLUGIN_URL; ?>assets/images/makia-logo.svg" alt="MakIA Logo">
                    </div>
                    <div class="makia-admin-header-content">
                        <h1>MakIA - Sistema de Reservas</h1>
                        <p>Gestiona todas las reservas de tu restaurante desde un solo lugar</p>
                    </div>
                </div>
                
                <!-- Contenido de pestañas -->
                
                <!-- Principal -->
                <div id="makia-tab-principal" class="makia-tab-content active">
                    <?php $this->render_dashboard_tab(); ?>
                </div>
                
                <!-- Reservas -->
                <div id="makia-tab-reservations" class="makia-tab-content">
                    <?php $this->render_reservations_tab(); ?>
                </div>
                
                <!-- Configuración -->
                <div id="makia-tab-settings" class="makia-tab-content">
                    <?php $this->render_settings_tab(); ?>
                </div>
                
                <!-- Apariencia -->
                <div id="makia-tab-appearance" class="makia-tab-content">
                    <?php $this->render_appearance_tab(); ?>
                </div>
                
                <!-- Horarios Semanales -->
                <div id="makia-tab-schedule" class="makia-tab-content">
                    <?php Makia_Schedule::get_instance()->render_page(); ?>
                </div>
                
                <!-- Días Especiales -->
                <div id="makia-tab-special-days" class="makia-tab-content">
                    <?php MakIA_Special_Days::render_page(); ?>
                </div>
                
                <!-- Capacidad -->
                <div id="makia-tab-capacity" class="makia-tab-content">
                    <?php MakIA_Capacity_UI::render_page(); ?>
                </div>
                
                <!-- Diseño -->
                <div id="makia-tab-design" class="makia-tab-content">
                    <?php MakIA_Design::render_design_tab(); ?>
                </div>
                
                <!-- Botón de Reservas -->
                <div id="makia-tab-button" class="makia-tab-content">
                    <?php $this->render_button_tab(); ?>
                </div>
                
                <!-- Plantillas -->
                <div id="makia-tab-templates" class="makia-tab-content">
                    <?php MakIA_Templates::render_page(); ?>
                </div>
                
                <!-- Lista Negra -->
                <div id="makia-tab-blacklist" class="makia-tab-content">
                    <?php MakIA_Blacklist::render_page(); ?>
                </div>
                
                <!-- Licencia -->
                <div id="makia-tab-license" class="makia-tab-content">
                    <?php $this->render_license_tab($current_plan, $license_info); ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tarjetas interactivas - Click para navegar a Reservas con filtro
            $('.makia-stat-card').on('click', function() {
                var filter = $(this).data('filter');
                
                // Construir URL con filtros y pestaña
                var params = ['page=makia', 'tab=reservations'];
                
                if (filter === 'pending') {
                    params.push('filter_status=pending');
                } else if (filter === 'approved') {
                    params.push('filter_status=approved');
                } else if (filter === 'cancelled') {
                    params.push('filter_status=cancelled');
                } else if (filter === 'upcoming') {
                    params.push('filter_status=approved');
                    var today = new Date();
                    var nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
                    params.push('filter_date_from=' + today.toISOString().split('T')[0]);
                    params.push('filter_date_to=' + nextWeek.toISOString().split('T')[0]);
                }
                
                // Navegar a la URL con los filtros
                window.location.href = '?' + params.join('&');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Renderizar pestaña Principal (antes Dashboard)
     */
    private function render_dashboard_tab() {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'makia_bookings';
        
        // Obtener estadísticas
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$bookings_table}");
        $pending = $wpdb->get_var("SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'pending'");
        $approved = $wpdb->get_var("SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'approved'");
        $cancelled = $wpdb->get_var("SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'cancelled'");
        $upcoming = $wpdb->get_var("SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'approved' AND booking_date >= CURDATE()");
        
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <!-- Total Reservas -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #667eea;">
                <h3 style="margin: 0 0 10px 0; color: #667eea; font-size: 16px;">Total Reservas</h3>
                <p style="margin: 0; font-size: 36px; font-weight: 700; color: #333;"><?php echo $total; ?></p>
            </div>
            
            <!-- Pendientes -->
            <div class="makia-stat-card" data-filter="pending" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #f0b849; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.05)';">
                <h3 style="margin: 0 0 10px 0; color: #f0b849; font-size: 16px;">⏳ Pendientes</h3>
                <p style="margin: 0; font-size: 36px; font-weight: 700; color: #333;"><?php echo $pending; ?></p>
            </div>
            
            <!-- Aprobadas -->
            <div class="makia-stat-card" data-filter="approved" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #46b450; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.05)';">
                <h3 style="margin: 0 0 10px 0; color: #46b450; font-size: 16px;">✅ Aprobadas</h3>
                <p style="margin: 0; font-size: 36px; font-weight: 700; color: #333;"><?php echo $approved; ?></p>
            </div>
            
            <!-- Canceladas -->
            <div class="makia-stat-card" data-filter="cancelled" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #dc3232; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.05)';">
                <h3 style="margin: 0 0 10px 0; color: #dc3232; font-size: 16px;">🚫 Canceladas</h3>
                <p style="margin: 0; font-size: 36px; font-weight: 700; color: #333;"><?php echo $cancelled; ?></p>
            </div>
            
            <!-- Próximas -->
            <div class="makia-stat-card" data-filter="upcoming" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #764ba2; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.05)';">
                <h3 style="margin: 0 0 10px 0; color: #764ba2; font-size: 16px;">📅 Próximas 7 días</h3>
                <p style="margin: 0; font-size: 36px; font-weight: 700; color: #333;"><?php echo $upcoming; ?></p>
            </div>
        </div>
        
        <div style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h2 style="margin: 0 0 20px 0; color: #667eea;">Bienvenido al Panel Principal</h2>
            <p style="margin: 0 0 15px 0; color: #666; line-height: 1.6;">
                Desde aquí puedes gestionar todas las reservas de tu restaurante. Utiliza el menú lateral para navegar entre las diferentes secciones.
            </p>
            <ul style="list-style: none; padding: 0; margin: 20px 0;">
                <li style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
                    <strong style="color: #667eea;">📅 Reservas:</strong> Gestiona todas las reservas (aprobar, rechazar, ver detalles)
                </li>
                <li style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
                    <strong style="color: #667eea;">⚙️ Configuración:</strong> Configura horarios, capacidad y datos del restaurante
                </li>
                <li style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
                    <strong style="color: #667eea;">🎨 Apariencia:</strong> Personaliza los colores y estilos del formulario
                </li>
                <li style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
                    <strong style="color: #667eea;">📧 Plantillas:</strong> Personaliza los mensajes de email, SMS y WhatsApp
                </li>
                <li style="padding: 10px 0;">
                    <strong style="color: #667eea;">🚫 Lista Negra:</strong> Gestiona usuarios baneados por no-show
                </li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Renderizar pestaña de Reservas
     */
    private function render_reservations_tab() {
        // Llamar a la clase MakIA_Bookings para renderizar
        MakIA_Bookings::render_page();
    }
    
    /**
     * Renderizar pestaña de Configuración
     */
    private function render_settings_tab() {
        // Guardar configuración si se envió el formulario
        if (isset($_POST['save_settings']) && check_admin_referer('makia_save_settings', 'makia_settings_nonce')) {
            update_option('makia_restaurant_name', sanitize_text_field($_POST['restaurant_name']));
            update_option('makia_restaurant_email', sanitize_email($_POST['restaurant_email']));
            update_option('makia_restaurant_phone', sanitize_text_field($_POST['restaurant_phone']));
            update_option('makia_restaurant_address', sanitize_textarea_field($_POST['restaurant_address']));
            update_option('makia_max_capacity', intval($_POST['max_capacity']));
            update_option('makia_max_per_reservation', intval($_POST['max_per_reservation']));
            
            // Guardar páginas legales
            update_option('makia_legal_page', intval($_POST['legal_page']));
            update_option('makia_privacy_page', intval($_POST['privacy_page']));
            
            // Guardar logo si se subió
            if (!empty($_FILES['restaurant_logo']['name'])) {
                $upload = wp_handle_upload($_FILES['restaurant_logo'], array('test_form' => false));
                if ($upload && !isset($upload['error'])) {
                    update_option('makia_restaurant_logo', $upload['url']);
                }
            }
            
            echo '<div class="notice notice-success"><p>Configuración guardada correctamente</p></div>';
        }
        
        $restaurant_name = get_option('makia_restaurant_name', '');
        $restaurant_email = get_option('makia_restaurant_email', get_option('admin_email'));
        $restaurant_phone = get_option('makia_restaurant_phone', '');
        $restaurant_address = get_option('makia_restaurant_address', '');
        $restaurant_logo = get_option('makia_restaurant_logo', '');
        $max_capacity = get_option('makia_max_capacity', 50);
        $max_per_reservation = get_option('makia_max_per_reservation', 12);
        $legal_page = get_option('makia_legal_page', 0);
        $privacy_page = get_option('makia_privacy_page', 0);
        
        ?>
        <div style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h2 style="margin: 0 0 20px 0; color: #667eea;">⚙️ Configuración del Restaurante</h2>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('makia_save_settings', 'makia_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="restaurant_name">Nombre del Restaurante *</label></th>
                        <td>
                            <input type="text" name="restaurant_name" id="restaurant_name" value="<?php echo esc_attr($restaurant_name); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="restaurant_logo">Logo del Restaurante</label></th>
                        <td>
                            <?php if ($restaurant_logo): ?>
                                <img src="<?php echo esc_url($restaurant_logo); ?>" style="max-width: 150px; display: block; margin-bottom: 10px;">
                            <?php endif; ?>
                            <input type="file" name="restaurant_logo" id="restaurant_logo" accept="image/*">
                            <p class="description">Logo que aparecerá en los emails (recomendado: 300x300px, PNG con fondo transparente)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="restaurant_email">Email *</label></th>
                        <td>
                            <input type="email" name="restaurant_email" id="restaurant_email" value="<?php echo esc_attr($restaurant_email); ?>" class="regular-text" required>
                            <p class="description">Email donde recibirás las notificaciones de reservas</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="restaurant_phone">Teléfono</label></th>
                        <td>
                            <input type="tel" name="restaurant_phone" id="restaurant_phone" value="<?php echo esc_attr($restaurant_phone); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="restaurant_address">Dirección</label></th>
                        <td>
                            <textarea name="restaurant_address" id="restaurant_address" rows="3" class="large-text"><?php echo esc_textarea($restaurant_address); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_capacity">Capacidad Máxima *</label></th>
                        <td>
                            <input type="number" name="max_capacity" id="max_capacity" value="<?php echo esc_attr($max_capacity); ?>" min="1" required>
                            <p class="description">Número máximo de comensales simultáneos</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_per_reservation">Máximo por Reserva *</label></th>
                        <td>
                            <input type="number" name="max_per_reservation" id="max_per_reservation" value="<?php echo esc_attr($max_per_reservation); ?>" min="1" required>
                            <p class="description">Número máximo de comensales por reserva individual</p>
                        </td>
                    </tr>
                </table>
                
                <h3 style="margin-top: 30px; color: #667eea;">📄 Páginas Legales</h3>
                <p style="color: #666; margin-bottom: 15px;">Selecciona las páginas que contienen tu Aviso Legal y Política de Privacidad. Los usuarios deberán aceptar estos términos al hacer una reserva.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="legal_page">Página de Aviso Legal</label></th>
                        <td>
                            <?php 
                            wp_dropdown_pages(array(
                                'name' => 'legal_page',
                                'id' => 'legal_page',
                                'selected' => $legal_page,
                                'show_option_none' => '-- Seleccionar página --',
                                'option_none_value' => '0'
                            ));
                            ?>
                            <p class="description">Página con los términos y condiciones legales</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="privacy_page">Página de Política de Privacidad</label></th>
                        <td>
                            <?php 
                            wp_dropdown_pages(array(
                                'name' => 'privacy_page',
                                'id' => 'privacy_page',
                                'selected' => $privacy_page,
                                'show_option_none' => '-- Seleccionar página --',
                                'option_none_value' => '0'
                            ));
                            ?>
                            <p class="description">Página con la política de privacidad y protección de datos</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="save_settings" class="button button-primary">💾 Guardar Configuración</button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Renderizar pestaña de Apariencia
     */
    private function render_appearance_tab() {
        echo '<div style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">';
        echo '<h2 style="margin: 0 0 20px 0; color: #667eea;">🎨 Personalización de Apariencia</h2>';
        echo '<p>Funcionalidad de personalización de colores y estilos próximamente.</p>';
        echo '</div>';
    }
    
    /**
     * Renderizar pestaña de Licencia con información detallada del plan
     */
    private function render_license_tab($current_plan, $license_info) {
        global $makia_license_manager;
        
        // Obtener estadísticas de uso
        $stats = isset($makia_license_manager) ? $makia_license_manager->get_usage_stats() : null;
        $all_plans = isset($makia_license_manager) ? $makia_license_manager->get_available_plans() : array();
        
        ?>
        <style>
            .makia-license-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
            @media (max-width: 782px) { .makia-license-grid { grid-template-columns: 1fr; } }
            .makia-license-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
            .makia-license-header { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; }
            .makia-license-icon { font-size: 48px; }
            .makia-license-title h2 { margin: 0 0 5px 0; color: #667eea; font-size: 28px; }
            .makia-license-title p { margin: 0; color: #666; font-size: 14px; }
            .makia-usage-bar { background: #f0f0f0; border-radius: 20px; height: 30px; overflow: hidden; margin: 20px 0; position: relative; }
            .makia-usage-fill { height: 100%; transition: width 0.5s ease; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 13px; }
            .makia-usage-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 20px; }
            .makia-usage-stat { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; }
            .makia-usage-stat-value { font-size: 28px; font-weight: 700; color: #333; display: block; }
            .makia-usage-stat-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 5px; }
            .makia-plan-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 30px; }
            @media (max-width: 1200px) { .makia-plan-cards { grid-template-columns: 1fr; } }
            .makia-plan-card { background: #fff; border: 2px solid #e0e0e0; border-radius: 12px; padding: 25px; text-align: center; transition: all 0.3s; position: relative; }
            .makia-plan-card.active { border-color: #667eea; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.15); }
            .makia-plan-card.active::before { content: '✓ Plan Actual'; position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #667eea; color: #fff; padding: 4px 15px; border-radius: 20px; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; }
            .makia-plan-name { font-size: 24px; font-weight: 700; color: #333; margin: 0 0 10px 0; }
            .makia-plan-price { font-size: 18px; color: #666; margin-bottom: 20px; }
            .makia-plan-price strong { font-size: 32px; color: #667eea; }
            .makia-plan-limit { background: #f0f7ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .makia-plan-limit-value { font-size: 36px; font-weight: 700; color: #667eea; display: block; }
            .makia-plan-limit-label { font-size: 13px; color: #666; }
            .makia-plan-features { text-align: left; list-style: none; padding: 0; margin: 20px 0; }
            .makia-plan-features li { padding: 8px 0; color: #666; font-size: 14px; }
            .makia-plan-features li::before { content: '✓'; color: #667eea; font-weight: 700; margin-right: 10px; }
            .makia-upgrade-btn { display: inline-block; padding: 12px 30px; background: #667eea; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s; border: none; cursor: pointer; }
            .makia-upgrade-btn:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); }
            .makia-alert { padding: 15px 20px; border-radius: 8px; margin: 20px 0; display: flex; align-items: center; gap: 15px; }
            .makia-alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
            .makia-alert.danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
            .makia-alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
            .makia-alert-icon { font-size: 24px; }
        </style>
        
        <div class="makia-license-grid">
            <!-- Columna principal -->
            <div>
                <div class="makia-license-card">
                    <div class="makia-license-header">
                        <div class="makia-license-icon">🍺</div>
                        <div class="makia-license-title">
                            <h2><?php echo esc_html($stats ? $stats['plan_name'] : $current_plan); ?></h2>
                            <p>Tu plan actual de reservas</p>
                        </div>
                    </div>
                    
                    <?php if ($stats): ?>
                        <!-- Alerta según el estado -->
                        <?php if ($stats['usage_percentage'] >= 100): ?>
                            <div class="makia-alert danger">
                                <div class="makia-alert-icon">🔴</div>
                                <div>
                                    <strong>Límite alcanzado</strong><br>
                                    Has llegado al límite de reservas de tu plan. Las nuevas reservas serán rechazadas hasta que actualices tu plan o se reinicie el contador el próximo mes.
                                </div>
                            </div>
                        <?php elseif ($stats['usage_percentage'] >= 80): ?>
                            <div class="makia-alert warning">
                                <div class="makia-alert-icon">🟠</div>
                                <div>
                                    <strong>Cerca del límite</strong><br>
                                    Solo te quedan <?php echo $stats['remaining']; ?> reservas disponibles este mes. Considera actualizar tu plan.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="makia-alert success">
                                <div class="makia-alert-icon">🟢</div>
                                <div>
                                    <strong>Todo en orden</strong><br>
                                    Tienes <?php echo $stats['remaining']; ?> reservas disponibles de <?php echo $stats['plan_limit']; ?> totales.
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Barra de uso -->
                        <div class="makia-usage-bar">
                            <div class="makia-usage-fill" style="width: <?php echo min(100, $stats['usage_percentage']); ?>%; background: <?php echo $stats['status']['color']; ?>;">
                                <?php echo $stats['current_count']; ?> / <?php echo $stats['plan_limit']; ?> reservas
                            </div>
                        </div>
                        
                        <!-- Estadísticas -->
                        <div class="makia-usage-stats">
                            <div class="makia-usage-stat">
                                <span class="makia-usage-stat-value"><?php echo $stats['current_count']; ?></span>
                                <span class="makia-usage-stat-label">Usadas</span>
                            </div>
                            <div class="makia-usage-stat">
                                <span class="makia-usage-stat-value"><?php echo $stats['remaining']; ?></span>
                                <span class="makia-usage-stat-label">Disponibles</span>
                            </div>
                            <div class="makia-usage-stat">
                                <span class="makia-usage-stat-value"><?php echo $stats['days_until_reset']; ?></span>
                                <span class="makia-usage-stat-label">Días hasta reset</span>
                            </div>
                        </div>
                        
                        <p style="text-align: center; color: #999; font-size: 13px; margin-top: 20px;">
                            Próximo reinicio: <?php echo date('d/m/Y', strtotime('first day of next month')); ?> a las 00:00
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Planes disponibles -->
                <div style="margin-top: 30px;">
                    <h3 style="color: #333; font-size: 20px; margin-bottom: 20px;">
                        📊 Compara Planes
                    </h3>
                    
                    <div class="makia-plan-cards">
                        <?php foreach ($all_plans as $plan_key => $plan): ?>
                            <div class="makia-plan-card <?php echo ($stats && strtolower($stats['plan_name']) === strtolower($plan['name'])) ? 'active' : ''; ?>">
                                <h3 class="makia-plan-name"><?php echo esc_html($plan['name']); ?></h3>
                                <div class="makia-plan-price">
                                    <?php if ($plan['price'] == 0): ?>
                                        <strong>GRATIS</strong>
                                    <?php else: ?>
                                        <strong><?php echo $plan['price']; ?>€</strong>/mes <small>(sin IVA)</small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="makia-plan-limit">
                                    <span class="makia-plan-limit-value"><?php echo $plan['limit']; ?></span>
                                    <span class="makia-plan-limit-label">reservas al mes</span>
                                </div>
                                
                                <ul class="makia-plan-features">
                                    <li>Panel de gestión completo</li>
                                    <li>Notificaciones por email</li>
                                    <li>Gestión de horarios</li>
                                    <li>Control de capacidad</li>
                                    <?php if ($plan_key === 'cana' || $plan_key === 'mascana'): ?>
                                        <li>Soporte prioritario</li>
                                    <?php endif; ?>
                                    <?php if ($plan_key === 'mascana'): ?>
                                        <li>Estadísticas avanzadas</li>
                                        <li>WhatsApp/SMS premium</li>
                                    <?php endif; ?>
                                </ul>
                                
                                <?php if (!$stats || strtolower($stats['plan_name']) !== strtolower($plan['name'])): ?>
                                    <a href="https://contacpro.app/pricing" target="_blank" class="makia-upgrade-btn">
                                        Cambiar a <?php echo esc_html($plan['name']); ?>
                                    </a>
                                <?php else: ?>
                                    <button class="makia-upgrade-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
                                        Plan Actual
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Columna lateral -->
            <div>
                <div class="makia-license-card">
                    <h3 style="color: #333; margin-top: 0;">ℹ️ Información</h3>
                    
                    <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <p style="margin: 0 0 10px 0; font-size: 13px; color: #666;">
                            <strong>Gestiona tu licencia:</strong>
                        </p>
                        <a href="https://contacpro.app/dashboard" target="_blank" style="display: inline-block; padding: 10px 20px; background: #667eea; color: #fff; text-decoration: none; border-radius: 6px; font-size: 14px;">
                            🔗 Ir a Contacpro
                        </a>
                    </div>
                    
                    <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 20px 0;">
                    
                    <h4 style="color: #333; font-size: 15px; margin-top: 20px;">📌 Cómo funciona</h4>
                    <ol style="font-size: 13px; color: #666; line-height: 1.8; padding-left: 20px;">
                        <li>Cada reserva cuenta para tu límite mensual</li>
                        <li>El contador se reinicia el 1º de cada mes</li>
        <li>Al llegar al límite, no se aceptan más reservas</li>
                        <li>Actualiza tu plan en cualquier momento</li>
                    </ol>
                    
                    <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 20px 0;">
                    
                    <h4 style="color: #333; font-size: 15px;">💡 Recomendaciones</h4>
                    <div style="font-size: 13px; color: #666; line-height: 1.6;">
                        <?php if ($stats && $stats['usage_percentage'] >= 80): ?>
                            <p style="padding: 10px; background: #fff3cd; border-radius: 6px; margin: 10px 0;">
                                ⚠️ Considera actualizar a un plan superior para evitar rechazar reservas.
                            </p>
                        <?php elseif ($stats && $stats['current_count'] < ($stats['plan_limit'] * 0.3)): ?>
                            <p style="padding: 10px; background: #d4edda; border-radius: 6px; margin: 10px 0;">
                                ✅ Tu plan actual se ajusta perfectamente a tus necesidades.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($stats && $stats['usage_percentage'] >= 90): ?>
                <div class="makia-license-card" style="margin-top: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff;">
                    <h3 style="color: #fff; margin-top: 0;">🚀 ¡Actualiza ahora!</h3>
                    <p style="font-size: 14px; line-height: 1.6; opacity: 0.95;">
                        Estás cerca de alcanzar tu límite. Actualiza tu plan para seguir recibiendo reservas sin interrupciones.
                    </p>
                    <a href="https://contacpro.app/upgrade" target="_blank" style="display: inline-block; padding: 12px 24px; background: #fff; color: #667eea; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 10px;">
                        Ver Planes →
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Obtener plan actual
     */
    private function get_current_plan($license_info) {
        if (isset($license_info['plan'])) {
            return $license_info['plan'];
        }
        return 'Free';
    }
    
    /**
     * Renderizar pestaña de Botón de Reservas - Diseño simplificado y fácil de usar
     */
    private function render_button_tab() {
        global $makia_button_settings;
        
        // Procesar guardado de opciones
        if (isset($_POST['save_button_settings']) && check_admin_referer('makia_button_settings_nonce')) {
            // Guardar opciones del botón
            update_option('makia_button_text', sanitize_text_field($_POST['makia_button_text'] ?? 'Reservar Mesa'));
            update_option('makia_button_color', sanitize_hex_color($_POST['makia_button_color'] ?? '#2c5530'));
            update_option('makia_button_text_color', sanitize_hex_color($_POST['makia_button_text_color'] ?? '#ffffff'));
            update_option('makia_button_size', sanitize_text_field($_POST['makia_button_size'] ?? 'medium'));
            update_option('makia_button_style', sanitize_text_field($_POST['makia_button_style'] ?? 'solid'));
            update_option('makia_button_border_radius', intval($_POST['makia_button_border_radius'] ?? 8));
            update_option('makia_button_url', esc_url_raw($_POST['makia_button_url'] ?? ''));
            
            // Opciones del botón flotante
            update_option('makia_floating_enabled', isset($_POST['makia_floating_enabled']) ? true : false);
            update_option('makia_floating_position', sanitize_text_field($_POST['makia_floating_position'] ?? 'right'));
            update_option('makia_floating_scroll_offset', intval($_POST['makia_floating_scroll_offset'] ?? 100));
            update_option('makia_floating_animation', sanitize_text_field($_POST['makia_floating_animation'] ?? 'fade'));
            update_option('makia_floating_mobile', isset($_POST['makia_floating_mobile']) ? true : false);
            
            echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada correctamente.</p></div>';
        }
        
        // Obtener opciones actuales
        $options = array(
            'makia_button_text' => get_option('makia_button_text', 'Reservar Mesa'),
            'makia_button_color' => get_option('makia_button_color', '#2c5530'),
            'makia_button_text_color' => get_option('makia_button_text_color', '#ffffff'),
            'makia_button_size' => get_option('makia_button_size', 'medium'),
            'makia_button_style' => get_option('makia_button_style', 'solid'),
            'makia_button_border_radius' => get_option('makia_button_border_radius', '8'),
            'makia_button_url' => get_option('makia_button_url', ''),
            'makia_floating_enabled' => get_option('makia_floating_enabled', false),
            'makia_floating_position' => get_option('makia_floating_position', 'right'),
            'makia_floating_scroll_offset' => get_option('makia_floating_scroll_offset', '100'),
            'makia_floating_animation' => get_option('makia_floating_animation', 'fade'),
            'makia_floating_mobile' => get_option('makia_floating_mobile', true)
        );
        ?>
        
        <style>
            .makia-btn-panel { max-width: 1200px; }
            .makia-btn-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 20px; }
            .makia-btn-card h3 { margin: 0 0 20px 0; color: #2c5530; font-size: 18px; display: flex; align-items: center; gap: 10px; }
            .makia-btn-card h3 .dashicons { font-size: 24px; width: 24px; height: 24px; }
            .makia-btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            @media (max-width: 782px) { .makia-btn-grid { grid-template-columns: 1fr; } }
            .makia-btn-field { margin-bottom: 20px; }
            .makia-btn-field label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
            .makia-btn-field input[type="text"], .makia-btn-field input[type="url"], .makia-btn-field input[type="number"], .makia-btn-field select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
            .makia-btn-field input[type="color"] { width: 60px; height: 40px; padding: 2px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer; }
            .makia-btn-field .description { color: #666; font-size: 12px; margin-top: 6px; }
            .makia-btn-toggle { display: flex; align-items: center; justify-content: space-between; padding: 20px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px; }
            .makia-btn-toggle-info h4 { margin: 0 0 5px 0; color: #333; }
            .makia-btn-toggle-info p { margin: 0; color: #666; font-size: 13px; }
            .makia-btn-switch { position: relative; width: 60px; height: 32px; }
            .makia-btn-switch input { opacity: 0; width: 0; height: 0; }
            .makia-btn-switch .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 32px; }
            .makia-btn-switch .slider:before { position: absolute; content: ""; height: 24px; width: 24px; left: 4px; bottom: 4px; background-color: white; transition: .3s; border-radius: 50%; }
            .makia-btn-switch input:checked + .slider { background-color: #2c5530; }
            .makia-btn-switch input:checked + .slider:before { transform: translateX(28px); }
            .makia-preview-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 40px; text-align: center; position: sticky; top: 50px; }
            .makia-preview-box h4 { color: #fff; margin: 0 0 20px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; }
            .makia-preview-btn { display: inline-flex; align-items: center; gap: 10px; padding: 14px 28px; font-weight: 600; text-decoration: none; border-radius: 8px; font-size: 16px; transition: all 0.3s ease; cursor: pointer; border: none; }
            .makia-preview-btn svg { width: 20px; height: 20px; }
            .makia-shortcode-box { background: #f0f7f1; border: 2px dashed #2c5530; border-radius: 8px; padding: 20px; text-align: center; }
            .makia-shortcode-box code { background: #fff; padding: 12px 20px; border-radius: 6px; font-size: 14px; display: inline-block; margin: 10px 0; user-select: all; cursor: pointer; border: 1px solid #e0e0e0; }
            .makia-shortcode-box p { margin: 10px 0 0 0; color: #666; font-size: 13px; }
            .makia-color-row { display: flex; gap: 20px; }
            .makia-color-item { flex: 1; }
            .makia-style-options { display: flex; gap: 10px; }
            .makia-style-option { flex: 1; }
            .makia-style-option input { display: none; }
            .makia-style-option label { display: block; padding: 12px; text-align: center; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 13px; }
            .makia-style-option input:checked + label { border-color: #2c5530; background: #f0f7f1; color: #2c5530; font-weight: 600; }
            .makia-size-options { display: flex; gap: 10px; }
            .makia-size-option { flex: 1; }
            .makia-size-option input { display: none; }
            .makia-size-option label { display: block; padding: 10px; text-align: center; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
            .makia-size-option input:checked + label { border-color: #2c5530; background: #f0f7f1; }
            .makia-size-option label span { display: block; font-size: 11px; color: #666; margin-top: 4px; }
            .makia-pos-options { display: flex; gap: 10px; }
            .makia-pos-option { flex: 1; }
            .makia-pos-option input { display: none; }
            .makia-pos-option label { display: flex; flex-direction: column; align-items: center; padding: 15px 10px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
            .makia-pos-option input:checked + label { border-color: #2c5530; background: #f0f7f1; }
            .makia-pos-option label .dashicons { font-size: 20px; margin-bottom: 5px; color: #666; }
            .makia-pos-option input:checked + label .dashicons { color: #2c5530; }
            .makia-btn-submit { background: #2c5530; color: #fff; border: none; padding: 14px 30px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
            .makia-btn-submit:hover { background: #1e3d22; transform: translateY(-1px); }
            .makia-advanced-toggle { color: #2c5530; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 5px; margin-top: 10px; }
            .makia-advanced-options { display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
            .makia-advanced-options.show { display: block; }
        </style>
        
        <div class="makia-btn-panel">
            <form method="post" action="">
                <?php wp_nonce_field('makia_button_settings_nonce'); ?>
                
                <div class="makia-btn-grid">
                    <!-- Columna izquierda: Configuración -->
                    <div>
                        <!-- Activación Rápida del Botón Flotante -->
                        <div class="makia-btn-card">
                            <div class="makia-btn-toggle">
                                <div class="makia-btn-toggle-info">
                                    <h4>Botón Flotante en tu Web</h4>
                                    <p>Muestra un botón de reservas visible en todas las páginas</p>
                                </div>
                                <label class="makia-btn-switch">
                                    <input type="checkbox" 
                                           id="makia_floating_enabled" 
                                           name="makia_floating_enabled" 
                                           value="1" 
                                           <?php checked($options['makia_floating_enabled'], true); ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div id="floating-options" style="<?php echo $options['makia_floating_enabled'] ? '' : 'opacity: 0.5; pointer-events: none;'; ?>">
                                <div class="makia-btn-field">
                                    <label>Posición del botón</label>
                                    <div class="makia-pos-options">
                                        <div class="makia-pos-option">
                                            <input type="radio" id="pos_left" name="makia_floating_position" value="left" <?php checked($options['makia_floating_position'], 'left'); ?>>
                                            <label for="pos_left">
                                                <span class="dashicons dashicons-align-left"></span>
                                                Izquierda
                                            </label>
                                        </div>
                                        <div class="makia-pos-option">
                                            <input type="radio" id="pos_center" name="makia_floating_position" value="center" <?php checked($options['makia_floating_position'], 'center'); ?>>
                                            <label for="pos_center">
                                                <span class="dashicons dashicons-align-center"></span>
                                                Centro
                                            </label>
                                        </div>
                                        <div class="makia-pos-option">
                                            <input type="radio" id="pos_right" name="makia_floating_position" value="right" <?php checked($options['makia_floating_position'], 'right'); ?>>
                                            <label for="pos_right">
                                                <span class="dashicons dashicons-align-right"></span>
                                                Derecha
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="makia-btn-field">
                                    <label>
                                        <input type="checkbox" 
                                               name="makia_floating_mobile" 
                                               value="1" 
                                               <?php checked($options['makia_floating_mobile'], true); ?>>
                                        Mostrar también en móviles
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Personalización del Botón -->
                        <div class="makia-btn-card">
                            <h3><span class="dashicons dashicons-art"></span> Personaliza tu Botón</h3>
                            
                            <div class="makia-btn-field">
                                <label for="makia_button_text">Texto del botón</label>
                                <input type="text" 
                                       id="makia_button_text" 
                                       name="makia_button_text" 
                                       value="<?php echo esc_attr($options['makia_button_text']); ?>"
                                       placeholder="Ej: Reservar Mesa">
                            </div>
                            
                            <div class="makia-color-row">
                                <div class="makia-color-item makia-btn-field">
                                    <label for="makia_button_color">Color del botón</label>
                                    <input type="color" 
                                           id="makia_button_color" 
                                           name="makia_button_color" 
                                           value="<?php echo esc_attr($options['makia_button_color']); ?>">
                                </div>
                                <div class="makia-color-item makia-btn-field">
                                    <label for="makia_button_text_color">Color del texto</label>
                                    <input type="color" 
                                           id="makia_button_text_color" 
                                           name="makia_button_text_color" 
                                           value="<?php echo esc_attr($options['makia_button_text_color']); ?>">
                                </div>
                            </div>
                            
                            <div class="makia-btn-field">
                                <label>Tamaño</label>
                                <div class="makia-size-options">
                                    <div class="makia-size-option">
                                        <input type="radio" id="size_small" name="makia_button_size" value="small" <?php checked($options['makia_button_size'], 'small'); ?>>
                                        <label for="size_small">Pequeño<span>Discreto</span></label>
                                    </div>
                                    <div class="makia-size-option">
                                        <input type="radio" id="size_medium" name="makia_button_size" value="medium" <?php checked($options['makia_button_size'], 'medium'); ?>>
                                        <label for="size_medium">Mediano<span>Recomendado</span></label>
                                    </div>
                                    <div class="makia-size-option">
                                        <input type="radio" id="size_large" name="makia_button_size" value="large" <?php checked($options['makia_button_size'], 'large'); ?>>
                                        <label for="size_large">Grande<span>Destacado</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="makia-btn-field">
                                <label>Estilo</label>
                                <div class="makia-style-options">
                                    <div class="makia-style-option">
                                        <input type="radio" id="style_solid" name="makia_button_style" value="solid" <?php checked($options['makia_button_style'], 'solid'); ?>>
                                        <label for="style_solid">Sólido</label>
                                    </div>
                                    <div class="makia-style-option">
                                        <input type="radio" id="style_outline" name="makia_button_style" value="outline" <?php checked($options['makia_button_style'], 'outline'); ?>>
                                        <label for="style_outline">Contorno</label>
                                    </div>
                                    <div class="makia-style-option">
                                        <input type="radio" id="style_gradient" name="makia_button_style" value="gradient" <?php checked($options['makia_button_style'], 'gradient'); ?>>
                                        <label for="style_gradient">Gradiente</label>
                                    </div>
                                </div>
                            </div>
                            
                            <span class="makia-advanced-toggle" onclick="document.getElementById('advanced-opts').classList.toggle('show'); this.querySelector('.dashicons').classList.toggle('dashicons-arrow-down-alt2'); this.querySelector('.dashicons').classList.toggle('dashicons-arrow-up-alt2');">
                                <span class="dashicons dashicons-arrow-down-alt2"></span> Opciones avanzadas
                            </span>
                            
                            <div id="advanced-opts" class="makia-advanced-options">
                                <div class="makia-btn-field">
                                    <label for="makia_button_border_radius">Bordes redondeados (px)</label>
                                    <input type="number" 
                                           id="makia_button_border_radius" 
                                           name="makia_button_border_radius" 
                                           value="<?php echo esc_attr($options['makia_button_border_radius']); ?>" 
                                           min="0" 
                                           max="50"
                                           style="width: 100px;">
                                </div>
                                
                                <div class="makia-btn-field">
                                    <label for="makia_button_url">URL de destino (opcional)</label>
                                    <input type="url" 
                                           id="makia_button_url" 
                                           name="makia_button_url" 
                                           value="<?php echo esc_url($options['makia_button_url']); ?>" 
                                           placeholder="Se detecta automáticamente">
                                    <p class="description">Deja vacío para detectar automáticamente la página de reservas.</p>
                                </div>
                                
                                <div class="makia-btn-field">
                                    <label for="makia_floating_scroll_offset">Mostrar botón flotante después de (px de scroll)</label>
                                    <input type="number" 
                                           id="makia_floating_scroll_offset" 
                                           name="makia_floating_scroll_offset" 
                                           value="<?php echo esc_attr($options['makia_floating_scroll_offset']); ?>" 
                                           min="0"
                                           style="width: 100px;">
                                    <p class="description">Usa 0 para mostrar inmediatamente.</p>
                                </div>
                                
                                <div class="makia-btn-field">
                                    <label for="makia_floating_animation">Animación de aparición</label>
                                    <select id="makia_floating_animation" name="makia_floating_animation" style="width: 200px;">
                                        <option value="fade" <?php selected($options['makia_floating_animation'], 'fade'); ?>>Desvanecer</option>
                                        <option value="slide" <?php selected($options['makia_floating_animation'], 'slide'); ?>>Deslizar</option>
                                        <option value="scale" <?php selected($options['makia_floating_animation'], 'scale'); ?>>Escalar</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shortcode -->
                        <div class="makia-btn-card">
                            <h3><span class="dashicons dashicons-shortcode"></span> Insertar en cualquier lugar</h3>
                            <div class="makia-shortcode-box">
                                <p style="margin: 0 0 10px 0; color: #333;">Copia este código y pégalo donde quieras mostrar el botón:</p>
                                <code onclick="navigator.clipboard.writeText('[makia_boton_reserva]'); this.style.background='#d4edda'; setTimeout(() => this.style.background='#fff', 1000);">[makia_boton_reserva]</code>
                                <p>Haz clic en el código para copiarlo</p>
                            </div>
                        </div>
                        
                        <button type="submit" name="save_button_settings" class="makia-btn-submit">
                            Guardar Cambios
                        </button>
                    </div>
                    
                    <!-- Columna derecha: Vista Previa -->
                    <div>
                        <div class="makia-btn-card">
                            <h3><span class="dashicons dashicons-visibility"></span> Vista Previa</h3>
                            <div class="makia-preview-box">
                                <h4>Así se verá tu botón</h4>
                                <button type="button" 
                                        id="preview-btn"
                                        class="makia-preview-btn"
                                        style="background-color: <?php echo esc_attr($options['makia_button_color']); ?>; color: <?php echo esc_attr($options['makia_button_text_color']); ?>; border-radius: <?php echo esc_attr($options['makia_button_border_radius']); ?>px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    <span id="preview-text"><?php echo esc_html($options['makia_button_text']); ?></span>
                                </button>
                            </div>
                            
                            <div style="margin-top: 20px; padding: 15px; background: #f0f7f1; border-radius: 8px;">
                                <p style="margin: 0; color: #2c5530; font-size: 13px;">
                                    <strong>Consejo:</strong> Activa el botón flotante para que tus clientes siempre tengan visible la opción de reservar mientras navegan por tu web.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle opciones flotantes
            $('#makia_floating_enabled').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#floating-options').css({'opacity': '1', 'pointer-events': 'auto'});
                } else {
                    $('#floating-options').css({'opacity': '0.5', 'pointer-events': 'none'});
                }
            });
            
            // Actualizar preview en tiempo real
            function updatePreview() {
                var color = $('#makia_button_color').val();
                var textColor = $('#makia_button_text_color').val();
                var text = $('#makia_button_text').val() || 'Reservar Mesa';
                var radius = $('#makia_button_border_radius').val() || 8;
                
                $('#preview-btn').css({
                    'background-color': color,
                    'color': textColor,
                    'border-radius': radius + 'px'
                });
                $('#preview-text').text(text);
                
                // Actualizar estilo
                var style = $('input[name="makia_button_style"]:checked').val();
                if (style === 'outline') {
                    $('#preview-btn').css({
                        'background-color': 'transparent',
                        'border': '2px solid ' + color,
                        'color': color
                    });
                } else if (style === 'gradient') {
                    $('#preview-btn').css({
                        'background': 'linear-gradient(135deg, ' + color + ' 0%, #1a3a1d 100%)',
                        'border': 'none',
                        'color': textColor
                    });
                } else {
                    $('#preview-btn').css({
                        'background-color': color,
                        'background': color,
                        'border': 'none',
                        'color': textColor
                    });
                }
                
                // Actualizar tamaño
                var size = $('input[name="makia_button_size"]:checked').val();
                if (size === 'small') {
                    $('#preview-btn').css({'padding': '10px 18px', 'font-size': '13px'});
                } else if (size === 'large') {
                    $('#preview-btn').css({'padding': '18px 36px', 'font-size': '18px'});
                } else {
                    $('#preview-btn').css({'padding': '14px 28px', 'font-size': '16px'});
                }
            }
            
            // Eventos para actualizar preview
            $('#makia_button_color, #makia_button_text_color').on('input', updatePreview);
            $('#makia_button_text, #makia_button_border_radius').on('input', updatePreview);
            $('input[name="makia_button_style"], input[name="makia_button_size"]').on('change', updatePreview);
        });
        </script>
        <?php
    }
}
