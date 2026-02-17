<?php
/**
 * Plugin Name: MakIA Restaurante
 * Plugin URI: https://contacpro.app
 * Description: MakIA Restaurante - Sistema completo de reservas con IA para restaurantes. Incluye formulario de reservas, gestión de horarios, control de capacidad, plantillas personalizables con vista previa en vivo, y lista negra de usuarios.
 * Version: 4.5.0
 * Author: MakIA Team
 * Author URI: https://contacpro.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: makia-reservas
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * ============================================================================
 * CONFIGURACIÓN REQUERIDA EN wp-config.php
 * ============================================================================
 *
 * Variables de entorno opcionales (pero recomendadas para seguridad):
 *
 * 1. JWT_AUTH_SECRET_KEY (Recomendado para API REST)
 *    - Clave secreta para firmar tokens JWT
 *    - Si no se define, se usará una combinación de las salts de WordPress
 *    - Ejemplo: define('JWT_AUTH_SECRET_KEY', 'tu-clave-secreta-muy-larga-y-aleatoria');
 *    - Genera una clave segura en: https://api.wordpress.org/secret-key/1.1/salt/
 *
 * 2. MAKIA_VAPID_PUBLIC_KEY (Requerido para Push Notifications)
 *    - Clave pública VAPID para Web Push
 *    - Ejemplo: define('MAKIA_VAPID_PUBLIC_KEY', 'BEl62i...');
 *
 * 3. MAKIA_VAPID_PRIVATE_KEY (Requerido para Push Notifications)
 *    - Clave privada VAPID para Web Push
 *    - Ejemplo: define('MAKIA_VAPID_PRIVATE_KEY', 'UUxI4o...');
 *
 * Configuración de Twilio para SMS (Opcional):
 *    - Las credenciales de Twilio se configuran en el panel de administración
 *    - El SDK de Twilio es opcional: si no está instalado, el plugin funciona sin SMS
 *    - Para instalar Twilio: composer require twilio/sdk
 *
 * Filtros de WordPress disponibles:
 *    - 'makia_rate_limit_ip': Límite de intentos por IP (default: 5)
 *    - 'makia_rate_limit_email': Límite de intentos por email (default: 3)
 *    - 'makia_rate_limit_window': Ventana de tiempo en segundos (default: 900 = 15 min)
 *    - 'makia_trusted_proxies': Array de IPs de proxies confiables para X-Forwarded-For
 *
 * ============================================================================
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('MAKIA_VERSION', '4.5.0');
define('MAKIA_PLUGIN_FILE', __FILE__);
define('MAKIA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAKIA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAKIA_API_URL', 'https://contacpro.app/api');

// Cargar clases
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-logger.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-license-manager.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-admin.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-shortcodes.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-special-days.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-bookings.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-templates.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-blacklist.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-capacity.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-capacity-ui.php';

require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-design.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-button-settings.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-schedule.php';

// Sistema de notas, operarios y auditoría
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-audit.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-notes.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-operators.php';

// Enlace a PWA para operarios
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-pwa-link.php';

// API REST para sincronización con la app
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-api.php';

// Servicio de Push Notifications
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-push-service.php';

// Servicio de SMS con Twilio
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-sms.php';

// Servicio de WhatsApp con WhatsApp Business API
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-whatsapp.php';
require_once MAKIA_PLUGIN_DIR . 'includes/class-makia-whatsapp-webhook.php';

/**
 * Activación del plugin
 */
function makia_activate() {
    // Crear tabla de reservas
    MakIA_Bookings::create_table();
    
    // Crear tablas de plantillas y lista negra
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabla de plantillas
    $templates_table = $wpdb->prefix . 'makia_notification_templates';
    $sql_templates = "CREATE TABLE IF NOT EXISTS {$templates_table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        template_type varchar(50) NOT NULL,
        template_name varchar(100) NOT NULL,
        subject varchar(200) DEFAULT NULL,
        body text NOT NULL,
        variables text DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY template_type (template_type),
        KEY is_active (is_active)
    ) $charset_collate;";
    
    // Tabla de lista negra
    $blacklist_table = $wpdb->prefix . 'makia_blacklist';
    $sql_blacklist = "CREATE TABLE IF NOT EXISTS {$blacklist_table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        email varchar(100) DEFAULT NULL,
        phone varchar(20) DEFAULT NULL,
        reason varchar(255) NOT NULL,
        no_show_count int(11) DEFAULT 0,
        banned_by bigint(20) NOT NULL,
        banned_at datetime DEFAULT CURRENT_TIMESTAMP,
        notes text DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        unbanned_at datetime DEFAULT NULL,
        unbanned_by bigint(20) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY email (email),
        KEY phone (phone),
        KEY is_active (is_active)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_templates);
    dbDelta($sql_blacklist);
    
    // Insertar plantillas por defecto si no existen
    $existing_templates = $wpdb->get_var("SELECT COUNT(*) FROM {$templates_table}");
    if ($existing_templates == 0) {
        makia_insert_default_templates();
    }
    
    // Crear opciones
    add_option('makia_license_key', '');
    add_option('makia_license_status', 'inactive');
    add_option('makia_restaurant_name', '');
    add_option('makia_restaurant_email', '');
    add_option('makia_restaurant_phone', '');
    add_option('makia_restaurant_address', '');
    add_option('makia_max_capacity', 50);
    add_option('makia_max_per_reservation', 12);
    
    // Crear estructura de horarios por defecto
    $default_hours = array(
        'monday' => array('enabled' => true, 'slots' => array(
            array('open' => '12:00', 'close' => '16:00'),
            array('open' => '20:00', 'close' => '23:00')
        )),
        'tuesday' => array('enabled' => true, 'slots' => array(
            array('open' => '12:00', 'close' => '16:00'),
            array('open' => '20:00', 'close' => '23:00')
        )),
        'wednesday' => array('enabled' => false, 'slots' => array()),
        'thursday' => array('enabled' => true, 'slots' => array(
            array('open' => '12:00', 'close' => '16:00'),
            array('open' => '20:00', 'close' => '23:00')
        )),
        'friday' => array('enabled' => true, 'slots' => array(
            array('open' => '12:00', 'close' => '16:00'),
            array('open' => '20:00', 'close' => '23:00')
        )),
        'saturday' => array('enabled' => true, 'slots' => array(
            array('open' => '12:00', 'close' => '16:00'),
            array('open' => '20:00', 'close' => '23:00')
        )),
        'sunday' => array('enabled' => true, 'slots' => array(
            array('open' => '12:00', 'close' => '16:00'),
            array('open' => '20:00', 'close' => '23:00')
        ))
    );
    add_option('makia_business_hours', $default_hours);
    
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'makia_activate');

/**
 * Insertar plantillas por defecto
 */
function makia_insert_default_templates() {
    global $wpdb;
    $table = $wpdb->prefix . 'makia_notification_templates';
    
    $templates = array(
        array(
            'type' => 'email_customer_pending',
            'name' => 'Email Cliente - Pendiente',
            'subject' => 'Reserva pendiente de confirmación - {restaurante}',
            'body' => "Hola {nombre},\n\nHemos recibido tu solicitud de reserva en {restaurante}.\n\nDETALLES DE LA RESERVA:\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nFecha: {fecha}\nHora: {hora}\nComensales: {comensales}\nMotivo: {motivo}\nNotas: {notas}\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nTu reserva está PENDIENTE DE CONFIRMACIÓN.\nTe enviaremos un email cuando el restaurante la apruebe.\n\n🔗 GESTIONAR RESERVA:\nPuedes modificar o cancelar tu reserva en cualquier momento:\n{enlace_gestion}\n\nSi tienes alguna pregunta, puedes contactarnos en:\nEmail: {email_restaurante}\nTeléfono: {telefono_restaurante}\n\nGracias por elegir {restaurante}.\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nPowered by MakIA Restaurante - https://contacpro.app",
            'variables' => '{"nombre":"Nombre del cliente","fecha":"Fecha de la reserva","hora":"Hora de la reserva","comensales":"Número de comensales","motivo":"Motivo de la reserva","notas":"Notas especiales","restaurante":"Nombre del restaurante","email_restaurante":"Email del restaurante","telefono_restaurante":"Teléfono del restaurante","enlace_gestion":"Enlace para gestionar la reserva"}'
        ),
        array(
            'type' => 'email_customer_approved',
            'name' => 'Email Cliente - Aprobada',
            'subject' => '✅ Reserva confirmada - {restaurante}',
            'body' => "Hola {nombre},\n\n¡Buenas noticias! Tu reserva ha sido CONFIRMADA.\n\nDETALLES DE LA RESERVA:\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nFecha: {fecha}\nHora: {hora}\nComensales: {comensales}\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n¡Te esperamos en {restaurante}!\n\n🔗 GESTIONAR RESERVA:\nSi necesitas modificar o cancelar tu reserva:\n{enlace_gestion}\n\nO contáctanos directamente:\nEmail: {email_restaurante}\nTeléfono: {telefono_restaurante}\n\nGracias por elegirnos.\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nPowered by MakIA Restaurante - https://contacpro.app",
            'variables' => '{"nombre":"Nombre del cliente","fecha":"Fecha de la reserva","hora":"Hora de la reserva","comensales":"Número de comensales","restaurante":"Nombre del restaurante","email_restaurante":"Email del restaurante","telefono_restaurante":"Teléfono del restaurante","enlace_gestion":"Enlace para gestionar la reserva"}'
        ),
        array(
            'type' => 'email_restaurant',
            'name' => 'Email Restaurante - Nueva Reserva',
            'subject' => 'Nueva reserva pendiente de aprobación',
            'body' => "Nueva solicitud de reserva en {restaurante}:\n\nDATOS DEL CLIENTE:\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nNombre: {nombre}\nEmail: {email}\nTeléfono: {telefono}\n\nDETALLES DE LA RESERVA:\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nFecha: {fecha}\nHora: {hora}\nComensales: {comensales}\nMotivo: {motivo}\nNotas: {notas}\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nPara gestionar esta reserva, accede al panel de administración:\n{url_admin}\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nMakIA Restaurante - https://contacpro.app",
            'variables' => '{"nombre":"Nombre del cliente","email":"Email del cliente","telefono":"Teléfono del cliente","fecha":"Fecha de la reserva","hora":"Hora de la reserva","comensales":"Número de comensales","motivo":"Motivo de la reserva","notas":"Notas especiales","restaurante":"Nombre del restaurante","url_admin":"URL del panel admin"}'
        ),
        array(
            'type' => 'sms',
            'name' => 'SMS - Confirmación',
            'subject' => null,
            'body' => '{restaurante}: Reserva confirmada para {nombre} el {fecha} a las {hora}. Modificar/cancelar: {enlace_gestion}',
            'variables' => '{"nombre":"Nombre del cliente","fecha":"Fecha de la reserva","hora":"Hora de la reserva","restaurante":"Nombre del restaurante","enlace_gestion":"Enlace para gestionar la reserva"}'
        ),
        array(
            'type' => 'sms_reminder',
            'name' => 'SMS - Recordatorio',
            'subject' => null,
            'body' => 'Recordatorio {restaurante}: Tu reserva es mañana {fecha} a las {hora}. Modificar/cancelar: {enlace_gestion}',
            'variables' => '{"nombre":"Nombre del cliente","fecha":"Fecha de la reserva","hora":"Hora de la reserva","restaurante":"Nombre del restaurante","enlace_gestion":"Enlace para gestionar la reserva"}'
        ),
        array(
            'type' => 'whatsapp',
            'name' => 'WhatsApp - Confirmación',
            'subject' => null,
            'body' => '✅ *{restaurante}*\n\nHola {nombre}, tu reserva ha sido *confirmada*:\n\n📅 {fecha}\n🕐 {hora}\n👥 {comensales} personas\n\n¡Te esperamos! 🍽️\n\n📝 Modificar o cancelar:\n{enlace_gestion}',
            'variables' => '{"nombre":"Nombre del cliente","fecha":"Fecha de la reserva","hora":"Hora de la reserva","comensales":"Número de comensales","restaurante":"Nombre del restaurante","enlace_gestion":"Enlace para gestionar la reserva"}'
        ),
        array(
            'type' => 'whatsapp_reminder',
            'name' => 'WhatsApp - Recordatorio',
            'subject' => null,
            'body' => '⏰ *Recordatorio - {restaurante}*\n\nHola {nombre}, te recordamos tu reserva:\n\n📅 {fecha}\n🕐 {hora}\n👥 {comensales} personas\n\n¡Te esperamos!\n\n📝 Modificar o cancelar:\n{enlace_gestion}',
            'variables' => '{"nombre":"Nombre del cliente","fecha":"Fecha de la reserva","hora":"Hora de la reserva","comensales":"Número de comensales","restaurante":"Nombre del restaurante","enlace_gestion":"Enlace para gestionar la reserva"}'
        )
    );
    
    foreach ($templates as $template) {
        $wpdb->insert(
            $table,
            array(
                'template_type' => $template['type'],
                'template_name' => $template['name'],
                'subject' => $template['subject'],
                'body' => $template['body'],
                'variables' => $template['variables'],
                'is_active' => 1
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d')
        );
    }
}

/**
 * Desactivación del plugin
 */
function makia_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'makia_deactivate');

/**
 * Inicializar el plugin
 */
function makia_init() {
    // Cargar traducciones
    load_plugin_textdomain('makia-reservas', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Inicializar clases con variable global
    global $makia_license_manager;
    $makia_license_manager = new MakIA_License_Manager();
    new MakIA_Admin();
    new MakIA_Shortcodes();
    new MakIA_Blacklist(); // Inicializar para hooks AJAX
}
add_action('plugins_loaded', 'makia_init');

// Los assets del frontend se cargan desde MakIA_Shortcodes::enqueue_scripts()
// y MakIA_Button_Settings::enqueue_button_assets()

/**
 * Agregar enlaces en la página de plugins
 */
function makia_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=makia-settings') . '">' . __('Configuración', 'makia-reservas') . '</a>';
    $license_link = '<a href="' . admin_url('admin.php?page=makia-license') . '">' . __('Licencia', 'makia-reservas') . '</a>';
    array_unshift($links, $settings_link, $license_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'makia_plugin_action_links');

/**
 * Verificar licencia en cada carga de admin
 */
function makia_check_license_status() {
    global $makia_license_manager;
    if (isset($makia_license_manager)) {
        $makia_license_manager->verify_license();
    }
}
add_action('admin_init', 'makia_check_license_status');
