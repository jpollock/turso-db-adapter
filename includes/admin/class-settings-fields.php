<?php
namespace TursoDBAdapter\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Fields Handler
 */
class Settings_Fields {
    /**
     * Render text field
     */
    public static function render_text_field($args) {
        $options = get_option('turso_db_settings', []);
        $field_id = $args['label_for'];
        $value = isset($options[$field_id]) ? $options[$field_id] : '';
        ?>
        <input type="text" 
               id="<?php echo esc_attr($field_id); ?>" 
               name="<?php echo esc_attr('turso_db_settings[' . $field_id . ']'); ?>" 
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <?php
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Render password field
     */
    public static function render_password_field($args) {
        $options = get_option('turso_db_settings', []);
        $field_id = $args['label_for'];
        $value = isset($options[$field_id]) ? $options[$field_id] : '';
        ?>
        <input type="password" 
               id="<?php echo esc_attr($field_id); ?>" 
               name="<?php echo esc_attr('turso_db_settings[' . $field_id . ']'); ?>" 
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <?php
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Render checkbox field
     */
    public static function render_checkbox_field($args) {
        $options = get_option('turso_db_settings', []);
        $field_id = $args['label_for'];
        $value = isset($options[$field_id]) ? $options[$field_id] : false;
        ?>
        <input type="checkbox" 
               id="<?php echo esc_attr($field_id); ?>" 
               name="<?php echo esc_attr('turso_db_settings[' . $field_id . ']'); ?>" 
               value="1"
               <?php checked($value, true); ?>>
        <?php
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Render section info
     */
    public static function render_section_info($args) {
        if ($args['id'] === 'turso_connection_section') {
            echo '<p>' . esc_html__('Configure your Turso database connection settings below.', 'turso-db-adapter') . '</p>';
        }
    }
}
