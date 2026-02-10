<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Gestión de Plantillas de Notificaciones MakIA
 * Maneja las plantillas personalizables de email, SMS y WhatsApp
 */

class MakIA_Templates {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'makia_notification_templates';
    }
    
    /**
     * Obtener todas las plantillas
     */
    public function get_all_templates() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE is_active = 1 ORDER BY template_type, id");
    }
    
    /**
     * Obtener plantilla por tipo
     */
    public function get_template_by_type($type) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE template_type = %s AND is_active = 1 LIMIT 1",
            $type
        ));
    }
    
    /**
     * Actualizar plantilla
     */
    public function update_template($id, $subject, $body) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            array(
                'subject' => $subject,
                'body' => $body,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Procesar plantilla con variables
     */
    public function process_template($template_body, $variables) {
        $processed = $template_body;
        
        foreach ($variables as $key => $value) {
            $processed = str_replace('{' . $key . '}', $value, $processed);
        }
        
        return $processed;
    }
    
    /**
     * Obtener variables disponibles
     */
    public function get_available_variables($template_type) {
        $template = $this->get_template_by_type($template_type);
        if ($template && $template->variables) {
            return json_decode($template->variables, true);
        }
        return array();
    }
    
    /**
     * Renderizar página de gestión de plantillas
     */
    public static function render_page() {
        $templates_instance = new self();
        
        // Guardar cambios si se envió el formulario
        if (isset($_POST['save_templates']) && current_user_can('manage_options') && check_admin_referer('makia_save_templates', 'makia_templates_nonce')) {
            $templates_instance->save_templates_from_form();
            echo '<div class="notice notice-success"><p>Plantillas actualizadas correctamente</p></div>';
        }
        
        // Restaurar plantillas por defecto
        if (isset($_POST['restore_defaults']) && current_user_can('manage_options') && check_admin_referer('makia_restore_templates', 'makia_restore_nonce')) {
            $templates_instance->restore_default_templates();
            echo '<div class="notice notice-success"><p>Plantillas restauradas a valores por defecto</p></div>';
        }
        
        $templates = $templates_instance->get_all_templates();
        
        // Agrupar plantillas por tipo
        $grouped_templates = array(
            'email' => array(),
            'sms' => array(),
            'whatsapp' => array()
        );
        
        foreach ($templates as $template) {
            if (strpos($template->template_type, 'email') === 0) {
                $grouped_templates['email'][] = $template;
            } elseif (strpos($template->template_type, 'sms') === 0) {
                $grouped_templates['sms'][] = $template;
            } elseif (strpos($template->template_type, 'whatsapp') === 0) {
                $grouped_templates['whatsapp'][] = $template;
            }
        }
        
        ?>
        <div class="wrap">
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;">
                <h2 style="margin: 0 0 10px 0; color: #667eea;">📧 Plantillas de Notificaciones</h2>
                <p style="margin: 0; color: #666;">Personaliza los mensajes que se envían a tus clientes y al restaurante.</p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('makia_save_templates', 'makia_templates_nonce'); ?>
                
                <!-- Plantillas de Email -->
                <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;">
                    <h3 style="margin: 0 0 20px 0; color: #667eea; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">📧</span> Plantillas de Email
                    </h3>
                    
                    <?php foreach ($grouped_templates['email'] as $template): ?>
                        <?php $variables = json_decode($template->variables, true); ?>
                        <div style="border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: #333;"><?php echo esc_html($template->template_name); ?></h4>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Asunto:</label>
                                <input type="text" 
                                       name="template_subject_<?php echo $template->id; ?>" 
                                       value="<?php echo esc_attr($template->subject); ?>" 
                                       style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Cuerpo del mensaje:</label>
                                <textarea name="template_body_<?php echo $template->id; ?>" 
                                          rows="12" 
                                          style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 13px; font-family: monospace;"><?php echo esc_textarea($template->body); ?></textarea>
                            </div>
                            
                            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                                <strong style="color: #667eea;">Variables disponibles:</strong>
                                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;">
                                    <?php foreach ($variables as $var => $description): ?>
                                        <code style="background: #fff; padding: 5px 10px; border-radius: 6px; font-size: 12px; border: 1px solid #e0e0e0;" 
                                              title="<?php echo esc_attr($description); ?>">{<?php echo esc_html($var); ?>}</code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Plantillas de SMS -->
                <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;">
                    <h3 style="margin: 0 0 20px 0; color: #46b450; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">💬</span> Plantillas de SMS
                    </h3>
                    
                    <?php foreach ($grouped_templates['sms'] as $template): ?>
                        <?php $variables = json_decode($template->variables, true); ?>
                        <div style="border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: #333;"><?php echo esc_html($template->template_name); ?></h4>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Mensaje (máximo 160 caracteres):</label>
                                <textarea name="template_body_<?php echo $template->id; ?>" 
                                          rows="4" 
                                          maxlength="160"
                                          style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;"><?php echo esc_textarea($template->body); ?></textarea>
                                <small style="color: #999;">Caracteres: <span id="sms-count-<?php echo $template->id; ?>">0</span>/160</small>
                            </div>
                            
                            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                                <strong style="color: #46b450;">Variables disponibles:</strong>
                                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;">
                                    <?php foreach ($variables as $var => $description): ?>
                                        <code style="background: #fff; padding: 5px 10px; border-radius: 6px; font-size: 12px; border: 1px solid #e0e0e0;" 
                                              title="<?php echo esc_attr($description); ?>">{<?php echo esc_html($var); ?>}</code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Plantillas de WhatsApp -->
                <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;">
                    <h3 style="margin: 0 0 20px 0; color: #25D366; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">📱</span> Plantillas de WhatsApp
                    </h3>
                    
                    <?php foreach ($grouped_templates['whatsapp'] as $template): ?>
                        <?php $variables = json_decode($template->variables, true); ?>
                        <div style="border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: #333;"><?php echo esc_html($template->template_name); ?></h4>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Mensaje:</label>
                                <textarea name="template_body_<?php echo $template->id; ?>" 
                                          rows="6" 
                                          style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;"><?php echo esc_textarea($template->body); ?></textarea>
                            </div>
                            
                            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                                <strong style="color: #25D366;">Variables disponibles:</strong>
                                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;">
                                    <?php foreach ($variables as $var => $description): ?>
                                        <code style="background: #fff; padding: 5px 10px; border-radius: 6px; font-size: 12px; border: 1px solid #e0e0e0;" 
                                              title="<?php echo esc_attr($description); ?>">{<?php echo esc_html($var); ?>}</code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Botones de acción -->
                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="submit" name="save_templates" class="button button-primary" style="padding: 12px 30px; border-radius: 8px; font-size: 14px;">
                        💾 Guardar Cambios
                    </button>
                </div>
            </form>
            
            <!-- Formulario separado para restaurar -->
            <form method="post" action="" style="display: inline;">
                <?php wp_nonce_field('makia_restore_templates', 'makia_restore_nonce'); ?>
                <button type="submit" name="restore_defaults" class="button" 
                        onclick="return confirm('¿Estás seguro de restaurar las plantillas por defecto? Se perderán todos los cambios personalizados.');"
                        style="padding: 12px 30px; border-radius: 8px; font-size: 14px;">
                    🔄 Restaurar Plantillas por Defecto
                </button>
            </form>
            
            <script>
            // Contador de caracteres para SMS
            document.addEventListener('DOMContentLoaded', function() {
                const smsTextareas = document.querySelectorAll('textarea[name^="template_body_"]');
                smsTextareas.forEach(function(textarea) {
                    if (textarea.hasAttribute('maxlength')) {
                        const id = textarea.name.replace('template_body_', '');
                        const counter = document.getElementById('sms-count-' + id);
                        if (counter) {
                            // Actualizar contador inicial
                            counter.textContent = textarea.value.length;
                            
                            // Actualizar en tiempo real
                            textarea.addEventListener('input', function() {
                                counter.textContent = this.value.length;
                            });
                        }
                    }
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Guardar plantillas desde el formulario
     */
    private function save_templates_from_form() {
        global $wpdb;
        
        $templates = $this->get_all_templates();
        
        foreach ($templates as $template) {
            $subject_key = 'template_subject_' . $template->id;
            $body_key = 'template_body_' . $template->id;
            
            $subject = isset($_POST[$subject_key]) ? sanitize_text_field($_POST[$subject_key]) : $template->subject;
            $body = isset($_POST[$body_key]) ? sanitize_textarea_field($_POST[$body_key]) : $template->body;
            
            $this->update_template($template->id, $subject, $body);
        }
    }
    
    /**
     * Restaurar plantillas por defecto
     */
    private function restore_default_templates() {
        global $wpdb;
        $table = $wpdb->prefix . 'makia_notification_templates';

        // Clear existing templates
        $wpdb->query("TRUNCATE TABLE {$table}");

        // Re-insert defaults using the activation function
        if (function_exists('makia_insert_default_templates')) {
            makia_insert_default_templates();
        }
    }
}
