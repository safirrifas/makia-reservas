<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Gestor de Licencias de MakIA
 * Maneja la activación, verificación y desactivación de licencias con sistema de planes
 * 
 * Planes disponibles:
 * - Chupito: 5 reservas/mes (Gratis)
 * - Caña: 15 reservas/mes (5€/mes)
 * - MasCaña: 100 reservas/mes (15€/mes)
 * 
 * @package MakIA_Reservas
 * @version 3.3.5
 */
class MakIA_License_Manager {
    
    // Definición de planes
    const PLAN_CHUPITO = 'chupito';
    const PLAN_CANA = 'cana';
    const PLAN_MAS_CANA = 'mascana';
    
    private $plans = array(
        'chupito' => array(
            'name' => 'Chupito',
            'limit' => 5,
            'price' => 0,
            'description' => 'Plan gratuito - Perfecto para comenzar'
        ),
        'cana' => array(
            'name' => 'Caña',
            'limit' => 15,
            'price' => 5,
            'description' => 'Plan básico - Para negocios pequeños'
        ),
        'mascana' => array(
            'name' => 'MasCaña',
            'limit' => 100,
            'price' => 15,
            'description' => 'Plan profesional - Para negocios en crecimiento'
        )
    );
    
    public function __construct() {
        // Verificar licencia cada 24 horas
        add_action('init', array($this, 'schedule_license_check'));
        add_action('makia_check_license', array($this, 'verify_license'));
        
        // Resetear contador de reservas mensualmente
        add_action('init', array($this, 'schedule_monthly_reset'));
        add_action('makia_monthly_reset', array($this, 'reset_monthly_counter'));
    }
    
    /**
     * Programar verificación periódica de licencia
     */
    public function schedule_license_check() {
        if (!wp_next_scheduled('makia_check_license')) {
            wp_schedule_event(time(), 'daily', 'makia_check_license');
        }
    }
    
    /**
     * Programar reset mensual de contador
     */
    public function schedule_monthly_reset() {
        if (!wp_next_scheduled('makia_monthly_reset')) {
            // Programar para el primer día de cada mes a las 00:00
            $next_month = strtotime('first day of next month 00:00:00');
            wp_schedule_event($next_month, 'monthly', 'makia_monthly_reset');
        }
    }
    
    /**
     * Resetear contador mensual de reservas
     */
    public function reset_monthly_counter() {
        update_option('makia_monthly_bookings_count', 0);
        update_option('makia_monthly_reset_date', date('Y-m-d H:i:s'));
        
        MakIA_Logger::log("Monthly bookings counter reset", "INFO");
    }
    
    /**
     * Obtener información del plan actual
     */
    public function get_current_plan() {
        $license_key = get_option('makia_license_key');
        if (empty($license_key)) {
            return $this->plans['chupito']; // Plan por defecto
        }
        
        $plan_type = get_option('makia_license_plan', 'chupito');
        return isset($this->plans[$plan_type]) ? $this->plans[$plan_type] : $this->plans['chupito'];
    }
    
    /**
     * Obtener todos los planes disponibles
     */
    public function get_available_plans() {
        return $this->plans;
    }
    
    /**
     * Verificar si se puede crear una reserva según el límite del plan
     */
    public function can_create_booking() {
        $current_plan = $this->get_current_plan();
        $current_count = intval(get_option('makia_monthly_bookings_count', 0));
        
        if ($current_count >= $current_plan['limit']) {
            return array(
                'allowed' => false,
                'message' => sprintf(
                    'Has alcanzado el límite de %d reservas de tu plan %s. Actualiza tu plan en Contacpro.app para seguir recibiendo reservas.',
                    $current_plan['limit'],
                    $current_plan['name']
                ),
                'current' => $current_count,
                'limit' => $current_plan['limit'],
                'plan' => $current_plan['name']
            );
        }
        
        return array(
            'allowed' => true,
            'current' => $current_count,
            'limit' => $current_plan['limit'],
            'remaining' => $current_plan['limit'] - $current_count,
            'plan' => $current_plan['name']
        );
    }
    
    /**
     * Incrementar contador de reservas mensuales
     */
    public function increment_booking_count() {
        $current_count = intval(get_option('makia_monthly_bookings_count', 0));
        update_option('makia_monthly_bookings_count', $current_count + 1);
        
        $current_plan = $this->get_current_plan();
        
        // Log si se está cerca del límite
        if (($current_count + 1) >= ($current_plan['limit'] * 0.8)) {
            MakIA_Logger::log(
                "Approaching plan limit",
                "INFO",
                array(
                    'current' => $current_count + 1,
                    'limit' => $current_plan['limit'],
                    'plan' => $current_plan['name']
                )
            );
        }
        
        return $current_count + 1;
    }
    
    /**
     * Obtener estadísticas de uso
     */
    public function get_usage_stats() {
        $current_plan = $this->get_current_plan();
        $current_count = intval(get_option('makia_monthly_bookings_count', 0));
        $reset_date = get_option('makia_monthly_reset_date', date('Y-m-d H:i:s'));
        
        $usage_percentage = ($current_plan['limit'] > 0) ? ($current_count / $current_plan['limit']) * 100 : 100;
        
        // Calcular días hasta el próximo reset
        $next_reset = strtotime('first day of next month 00:00:00');
        $days_until_reset = ceil(($next_reset - time()) / (60 * 60 * 24));
        
        return array(
            'plan_name' => $current_plan['name'],
            'plan_limit' => $current_plan['limit'],
            'current_count' => $current_count,
            'remaining' => max(0, $current_plan['limit'] - $current_count),
            'usage_percentage' => round($usage_percentage, 1),
            'days_until_reset' => $days_until_reset,
            'last_reset' => $reset_date,
            'status' => $this->get_usage_status($usage_percentage)
        );
    }
    
    /**
     * Obtener estado visual según el uso
     */
    private function get_usage_status($percentage) {
        if ($percentage >= 100) {
            return array('color' => 'red', 'label' => 'Límite alcanzado', 'icon' => '🔴');
        } elseif ($percentage >= 80) {
            return array('color' => 'orange', 'label' => 'Casi agotado', 'icon' => '🟠');
        } elseif ($percentage >= 50) {
            return array('color' => 'yellow', 'label' => 'Uso moderado', 'icon' => '🟡');
        } else {
            return array('color' => 'green', 'label' => 'Uso normal', 'icon' => '🟢');
        }
    }
    
    /**
     * Activar licencia (simplificado - solo verifica)
     * En este sistema, la activación se hace desde el panel de contacpro.app
     */
    public function activate_license($license_key) {
        $domain = parse_url(home_url(), PHP_URL_HOST);
        
        // Usar el endpoint de verify para comprobar la licencia
        $response = wp_remote_post(MAKIA_API_URL . '/trpc/licenses.verify', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'json' => array(
                    'licenseKey' => $license_key,
                    'domain' => $domain
                )
            )),
            'timeout' => 15,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            MakIA_Logger::error("License API connection failed", array(
                'error' => $response->get_error_message(),
                'action' => 'activate'
            ));
            
            return array(
                'success' => false,
                'message' => 'Error de conexión: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Verificar si hay error en la respuesta
        if (isset($body['error'])) {
            $error_message = isset($body['error']['json']['message']) 
                ? $body['error']['json']['message'] 
                : 'Error desconocido';
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
        
        // Verificar si la licencia es válida
        if (isset($body['result']['data']['json']['valid']) && $body['result']['data']['json']['valid']) {
            update_option('makia_license_key', $license_key);
            update_option('makia_license_status', 'active');
            update_option('makia_license_last_check', time());
            
            $license_data = $body['result']['data']['json']['license'];
            $expires_at = isset($license_data['expiresAt']) ? $license_data['expiresAt'] : '';
            $days_until_expiration = isset($license_data['daysUntilExpiration']) ? $license_data['daysUntilExpiration'] : 0;
            
            // Guardar información del plan
            $plan = isset($license_data['plan']) ? strtolower($license_data['plan']) : 'chupito';
            $plan = array_key_exists($plan, $this->plans) ? $plan : 'chupito';
            update_option('makia_license_plan', $plan);
            
            // Inicializar contador si no existe
            if (get_option('makia_monthly_bookings_count') === false) {
                update_option('makia_monthly_bookings_count', 0);
                update_option('makia_monthly_reset_date', date('Y-m-d H:i:s'));
            }
            
            $current_plan = $this->get_current_plan();
            
            MakIA_Logger::log("License activated", "INFO", array(
                'plan' => $current_plan['name'],
                'limit' => $current_plan['limit']
            ));
            
            return array(
                'success' => true,
                'message' => sprintf(
                    'Licencia activada correctamente. Plan: %s (%d reservas/mes). Válida por %d días.',
                    $current_plan['name'],
                    $current_plan['limit'],
                    $days_until_expiration
                ),
                'expires_at' => $expires_at,
                'plan' => $current_plan
            );
        } else {
            $message = isset($body['result']['data']['json']['message']) 
                ? $body['result']['data']['json']['message'] 
                : 'Licencia no válida';
            return array(
                'success' => false,
                'message' => $message
            );
        }
    }
    
    /**
     * Verificar licencia
     */
    public function verify_license() {
        $license_key = get_option('makia_license_key');
        if (empty($license_key)) {
            return false;
        }
        
        $domain = parse_url(home_url(), PHP_URL_HOST);
        
        $response = wp_remote_post(MAKIA_API_URL . '/trpc/licenses.verify', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'json' => array(
                    'licenseKey' => $license_key,
                    'domain' => $domain
                )
            )),
            'timeout' => 15,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            // En caso de error de conexión, mantener el estado actual por 7 días
            $last_check = get_option('makia_license_last_check', 0);
            if (time() - $last_check < 7 * 24 * 60 * 60) {
                return get_option('makia_license_status') === 'active';
            }
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['result']['data']['json']['valid']) && $body['result']['data']['json']['valid']) {
            update_option('makia_license_status', 'active');
            update_option('makia_license_last_check', time());
            return true;
        } else {
            update_option('makia_license_status', 'invalid');
            return false;
        }
    }
    
    /**
     * Desactivar licencia
     */
    public function deactivate_license() {
        // Simplemente eliminar la licencia localmente
        // No hay endpoint de desactivación en el servidor
        update_option('makia_license_status', 'inactive');
        delete_option('makia_license_key');
        delete_option('makia_license_last_check');
        
        return array(
            'success' => true,
            'message' => 'Licencia desactivada localmente'
        );
    }
    
    /**
     * Verificar si la licencia está activa
     */
    public function is_license_active() {
        $status = get_option('makia_license_status');
        $last_check = get_option('makia_license_last_check', 0);
        
        // Si han pasado más de 7 días sin verificar, forzar verificación
        if (time() - $last_check > 7 * 24 * 60 * 60) {
            $this->verify_license();
            $status = get_option('makia_license_status');
        }
        
        return $status === 'active';
    }
    
    /**
     * Obtener información de la licencia
     */
    public function get_license_info() {
        $license_key = get_option('makia_license_key');
        if (empty($license_key)) {
            return null;
        }
        
        $domain = parse_url(home_url(), PHP_URL_HOST);
        
        $response = wp_remote_post(MAKIA_API_URL . '/trpc/licenses.verify', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'json' => array(
                    'licenseKey' => $license_key,
                    'domain' => $domain
                )
            )),
            'timeout' => 15,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['result']['data']['json']['license'])) {
            return $body['result']['data']['json']['license'];
        }
        
        return null;
    }
}
