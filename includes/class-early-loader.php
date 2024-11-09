<?php
namespace TursoDBAdapter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Early Loader class for initializing Turso DB before WordPress database
 */
class Early_Loader {
    /**
     * Initialize Turso DB
     */
    public static function init() {
        // Load settings early
        $settings = self::get_early_settings();
        
        // Initialize logger
        Logger::init($settings['enable_logging']);
        Logger::info('Early initialization started');
        
        if ( true ) { //$settings['use_as_wpdb']) {
            Logger::info('Turso DB configured as primary WordPress database');

            // Initialize the database adapter
            try {
                global $wpdb;
                $wpdb = new DB_Adapter((object)$settings);
                
                Logger::info('Database adapter initialized successfully');

                // Define WordPress database constants to prevent default MySQL connection
                if (!defined('DB_HOST'))     define('DB_HOST', 'turso');
                if (!defined('DB_USER'))     define('DB_USER', 'turso');
                if (!defined('DB_PASSWORD')) define('DB_PASSWORD', 'turso');
                if (!defined('DB_NAME'))     define('DB_NAME', 'turso');
                
                // Prevent WordPress from establishing its own database connection
                if (!defined('WP_USE_EXT_MYSQL')) {
                    define('WP_USE_EXT_MYSQL', false);
                }

                Logger::info('WordPress database constants defined');
                return true;
            } catch (\Exception $e) {
                Logger::error('Failed to initialize database adapter: ' . $e->getMessage());
                return false;
            }
        } else {
            Logger::info('Using default WordPress database (Turso not configured as primary database)');
        }
        
        return false;
    }

    /**
     * Get settings before WordPress is fully loaded
     */
    private static function get_early_settings() {
        // Default settings
        $defaults = [
            'database_url' => '',
            'auth_token' => '',
            'enable_logging' => false,
            'connection_timeout' => 30,
            'use_as_wpdb' => false
        ];

        // Try to get settings from constants first
        if (defined('TURSO_DATABASE_URL') && defined('TURSO_AUTH_TOKEN')) {
            Logger::debug('Loading settings from constants');
            return [
                'database_url' => TURSO_DATABASE_URL,
                'auth_token' => TURSO_AUTH_TOKEN,
                'enable_logging' => defined('TURSO_ENABLE_LOGGING') ? TURSO_ENABLE_LOGGING : false,
                'use_as_wpdb' => defined('TURSO_USE_AS_WPDB') ? TURSO_USE_AS_WPDB : false,
                'connection_timeout' => defined('TURSO_CONNECTION_TIMEOUT') ? TURSO_CONNECTION_TIMEOUT : 30
            ];
        }

        // Try to get settings from wp-config.php
        global $wpdb;
        if (isset($wpdb)) {
            try {
                Logger::debug('Loading settings from database');
                $options = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT option_value FROM wp_options WHERE option_name = %s LIMIT 1",
                        'turso_db_settings'
                    )
                );
                
                if ($options) {
                    $settings = unserialize($options->option_value);
                    Logger::debug('Settings loaded from database: ' . print_r($settings, true));
                    return wp_parse_args($settings, $defaults);
                }
            } catch (\Exception $e) {
                Logger::error('Error loading settings from database: ' . $e->getMessage());
            }
        }

        Logger::debug('Using default settings');
        return $defaults;
    }
}
