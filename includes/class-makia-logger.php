<?php
/**
 * Sistema de Logging para MakIA
 * Registra eventos importantes para debugging y seguridad
 * 
 * @package MakIA_Reservas
 * @version 3.3.5
 */

if (!defined('ABSPATH')) {
    exit;
}

class MakIA_Logger {
    
    private static $log_file;
    
    public function __construct() {
        $log_dir = WP_CONTENT_DIR . '/makia-logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Create .htaccess to deny web access
            file_put_contents($log_dir . '/.htaccess', 'Deny from all');
            // Create index.php for extra protection
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden.');
        }
        self::$log_file = $log_dir . '/makia-debug.log';
    }
    
    /**
     * Registrar evento general
     */
    public static function log($message, $level = 'INFO', $context = array()) {
        if (!empty($context)) {
            $message .= ' | Context: ' . json_encode($context);
        }
        self::write_log($level, $message);
    }
    
    /**
     * Registrar error
     */
    public static function error($message, $context = array()) {
        $msg = $message;
        if (!empty($context)) {
            $msg .= ' | Context: ' . json_encode($context);
        }
        self::write_log('ERROR', $msg);
    }
    
    /**
     * Registrar advertencia de seguridad
     */
    public static function security($message, $context = array()) {
        $msg = $message;
        if (!empty($context)) {
            $msg .= ' | Context: ' . json_encode($context);
        }
        self::write_log('SECURITY', $msg);
    }
    
    /**
     * Registrar reserva exitosa
     */
    public static function booking($booking_id, $action, $details = array()) {
        $msg = "Booking #{$booking_id} - {$action}";
        if (!empty($details)) {
            $msg .= ' | ' . json_encode($details);
        }
        self::write_log('BOOKING', $msg);
    }
    
    /**
     * Escribir en archivo de log
     */
    private static function write_log($level, $message) {
        if (!self::$log_file) {
            self::$log_file = WP_CONTENT_DIR . '/makia-logs/makia-debug.log';
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $log_entry = "[{$timestamp}] [{$level}] [IP: {$ip}] {$message}" . PHP_EOL;
        
        // Rotar log si supera 10MB
        if (file_exists(self::$log_file) && filesize(self::$log_file) > 10 * 1024 * 1024) {
            rename(self::$log_file, self::$log_file . '.' . date('Y-m-d-His') . '.old');
        }
        
        error_log($log_entry, 3, self::$log_file);
        
        // También en error_log de PHP para errores críticos
        if (in_array($level, array('ERROR', 'SECURITY'))) {
            error_log("[MakIA] {$message}");
        }
    }
    
    /**
     * Obtener últimas líneas del log
     */
    public static function get_recent_logs($lines = 100) {
        if (!self::$log_file) {
            self::$log_file = WP_CONTENT_DIR . '/makia-logs/makia-debug.log';
        }
        
        if (!file_exists(self::$log_file)) {
            return array();
        }
        
        $file = file(self::$log_file);
        return array_slice($file, -$lines);
    }
    
    /**
     * Limpiar logs antiguos
     */
    public static function clean_old_logs($days = 30) {
        $files = glob(WP_CONTENT_DIR . '/makia-logs/makia-debug.log.*');
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        $cleaned = 0;
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Obtener tamaño de logs
     */
    public static function get_logs_size() {
        if (!self::$log_file) {
            self::$log_file = WP_CONTENT_DIR . '/makia-logs/makia-debug.log';
        }
        
        $size = 0;
        if (file_exists(self::$log_file)) {
            $size += filesize(self::$log_file);
        }
        
        $files = glob(WP_CONTENT_DIR . '/makia-logs/makia-debug.log.*');
        foreach ($files as $file) {
            $size += filesize($file);
        }
        
        return $size;
    }
}
