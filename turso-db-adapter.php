<?php
/**
 * Plugin Name: Turso DB Adapter
 * Plugin URI: https://github.com/yourusername/turso-db-adapter
 * Description: WordPress adapter for Turso Database
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: turso-db-adapter
 * Domain Path: /languages
 */

namespace TursoDBAdapter;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants if not already defined by mu-plugin
if (!defined('TURSO_DB_ADAPTER_VERSION')) {
    define('TURSO_DB_ADAPTER_VERSION', '1.0.0');
}
if (!defined('TURSO_DB_ADAPTER_PLUGIN_DIR')) {
    define('TURSO_DB_ADAPTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('TURSO_DB_ADAPTER_PLUGIN_URL')) {
    define('TURSO_DB_ADAPTER_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Add our custom autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'TursoDBAdapter\\') !== 0) {
        return;
    }

    $class_name = str_replace('TursoDBAdapter\\', '', $class);

    if (strpos($class_name, 'Admin\\') === 0) {
        $class_name = str_replace('Admin\\', '', $class_name);
        $class_file = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $class_name));
        $file = TURSO_DB_ADAPTER_PLUGIN_DIR . 'includes/admin/class-' . $class_file . '.php';
    } else {
        $class_file = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $class_name));
        $file = TURSO_DB_ADAPTER_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
    }

    if (!file_exists($file)) {
        error_log("Turso DB Adapter: Could not load file for class {$class} at {$file}");
        return;
    }

    require_once $file;
});

register_activation_hook(__FILE__, 'TursoDBAdapter\\activate_plugin');
register_uninstall_hook(__FILE__, 'TursoDBAdapter\\uninstall_plugin');

function activate_plugin() {
    try {
        // Create a single client instance
        $client = new Client();
        if (!$client->init_connection()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(esc_html__('Failed to connect to Turso database.', 'turso-db-adapter'));
        }

        // Initialize tables using the shared client
        if (!Installer::init_tables($client)) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(esc_html__('Failed to initialize database tables.', 'turso-db-adapter'));
        }
    } catch (\Exception $e) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(esc_html__('Error during plugin activation: ', 'turso-db-adapter') . $e->getMessage());
    }
}

function uninstall_plugin() {
    // Only run if WordPress initiated the uninstall
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }

    // Clean up database tables
    $installer = new Installer();
    $installer->drop_tables();

    // Remove plugin options
    delete_option('turso_db_settings');
}

/**
 * Returns the main instance of the plugin
 */
function turso_db_adapter() {
    return Core::get_instance();
}

// Initialize the plugin
add_action('plugins_loaded', 'TursoDBAdapter\\turso_db_adapter');
