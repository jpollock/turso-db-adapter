<?php
namespace TursoDBAdapter;

if (!defined('ABSPATH')) {
    exit;
}

// Require admin classes
require_once TURSO_DB_ADAPTER_PLUGIN_DIR . 'includes/admin/class-settings-fields.php';
require_once TURSO_DB_ADAPTER_PLUGIN_DIR . 'includes/admin/class-settings-tabs.php';
require_once TURSO_DB_ADAPTER_PLUGIN_DIR . 'includes/admin/class-settings-ajax.php';

use TursoDBAdapter\Admin\Settings_Fields;
use TursoDBAdapter\Admin\Settings_Tabs;
use TursoDBAdapter\Admin\Settings_Ajax;

/**
 * Settings class for managing plugin options
 */
class Settings {
    /**
     * Option constants
     */
    const OPTION_GROUP = 'turso_db_options';
    const OPTION_PAGE = 'turso-db-settings';
    const OPTION_NAME = 'turso_db_settings';

    /**
     * Default settings
     *
     * @var array
     */
    private $defaults = [
        'database_url' => '',
        'auth_token' => '',
        'enable_logging' => false,
        'connection_timeout' => 30,
        'use_as_wpdb' => false
    ];

    /**
     * Current options
     *
     * @var array
     */
    private $options;

    /**
     * AJAX handler
     *
     * @var Settings_Ajax
     */
    private $ajax_handler;

    /**
     * Constructor
     */
    public function __construct() {
        $this->options = get_option(self::OPTION_NAME, $this->defaults);
        $this->ajax_handler = new Settings_Ajax($this);
        
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->defaults,
            ]
        );

        // Connection Settings Section
        add_settings_section(
            'turso_connection_section',
            __('Connection Settings', 'turso-db-adapter'),
            [Settings_Fields::class, 'render_section_info'],
            self::OPTION_PAGE . '_connection'
        );

        // Database URL Field
        add_settings_field(
            'database_url',
            __('Database URL', 'turso-db-adapter'),
            [Settings_Fields::class, 'render_text_field'],
            self::OPTION_PAGE . '_connection',
            'turso_connection_section',
            [
                'label_for' => 'database_url',
                'description' => __('Your Turso database URL (e.g., libsql://your-db-name.turso.io)', 'turso-db-adapter')
            ]
        );

        // Auth Token Field
        add_settings_field(
            'auth_token',
            __('Auth Token', 'turso-db-adapter'),
            [Settings_Fields::class, 'render_password_field'],
            self::OPTION_PAGE . '_connection',
            'turso_connection_section',
            [
                'label_for' => 'auth_token',
                'description' => __('Your Turso authentication token', 'turso-db-adapter')
            ]
        );

        // Use as WordPress Database Field
        add_settings_field(
            'use_as_wpdb',
            __('Use as WordPress Database', 'turso-db-adapter'),
            [Settings_Fields::class, 'render_checkbox_field'],
            self::OPTION_PAGE . '_connection',
            'turso_connection_section',
            [
                'label_for' => 'use_as_wpdb',
                'description' => __('Use Turso as the primary WordPress database (requires restart)', 'turso-db-adapter')
            ]
        );

        // Enable Logging Field
        add_settings_field(
            'enable_logging',
            __('Enable Logging', 'turso-db-adapter'),
            [Settings_Fields::class, 'render_checkbox_field'],
            self::OPTION_PAGE . '_connection',
            'turso_connection_section',
            [
                'label_for' => 'enable_logging',
                'description' => __('Enable query logging for debugging', 'turso-db-adapter')
            ]
        );

        // Connection Timeout Field
        add_settings_field(
            'connection_timeout',
            __('Connection Timeout', 'turso-db-adapter'),
            [Settings_Fields::class, 'render_text_field'],
            self::OPTION_PAGE . '_connection',
            'turso_connection_section',
            [
                'label_for' => 'connection_timeout',
                'description' => __('Connection timeout in seconds', 'turso-db-adapter'),
                'min' => 5,
                'max' => 60,
                'step' => 5
            ]
        );
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_options_page(
            __('Turso DB Settings', 'turso-db-adapter'),
            __('Turso DB', 'turso-db-adapter'),
            'manage_options',
            self::OPTION_PAGE,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'turso-db-adapter'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php Settings_Tabs::render_tabs(); ?>

            <?php if (Settings_Tabs::get_current_tab() === 'connection'): ?>
                <?php Settings_Tabs::render_connection_tab(); ?>
            <?php else: ?>
                <?php Settings_Tabs::render_tables_tab(new Installer(new Client($this))); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_' . self::OPTION_PAGE !== $hook) {
            return;
        }

        wp_enqueue_style(
            'turso-db-admin',
            TURSO_DB_ADAPTER_PLUGIN_URL . 'assets/css/admin.css',
            [],
            TURSO_DB_ADAPTER_VERSION
        );

        wp_enqueue_script(
            'turso-db-admin',
            TURSO_DB_ADAPTER_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            TURSO_DB_ADAPTER_VERSION,
            true
        );

        wp_localize_script('turso-db-admin', 'tursoDbAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('turso_db_admin'),
            'testing_connection' => __('Testing connection...', 'turso-db-adapter'),
            'initializing_tables' => __('Initializing tables...', 'turso-db-adapter'),
            'recreating_tables' => __('Recreating tables...', 'turso-db-adapter'),
            'confirm_recreate' => __('Warning: This will drop and recreate all WordPress tables. Are you sure?', 'turso-db-adapter'),
            'wpdb_warning' => __('Warning: Using Turso as the WordPress database will replace the default MySQL database. This requires a restart of WordPress to take effect. Make sure you have backed up your data before proceeding.', 'turso-db-adapter')
        ]);
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        if (isset($input['database_url'])) {
            $sanitized['database_url'] = esc_url_raw($input['database_url']);
        }
        
        if (isset($input['auth_token'])) {
            $sanitized['auth_token'] = sanitize_text_field($input['auth_token']);
        }
        
        if (isset($input['enable_logging'])) {
            $sanitized['enable_logging'] = (bool)$input['enable_logging'];
        }
        
        if (isset($input['use_as_wpdb'])) {
            $sanitized['use_as_wpdb'] = (bool)$input['use_as_wpdb'];
        }
        
        if (isset($input['connection_timeout'])) {
            $sanitized['connection_timeout'] = absint($input['connection_timeout']);
        }
        
        return $sanitized;
    }

    /**
     * Get a specific setting value
     *
     * @param string $key The setting key to retrieve
     * @return mixed The setting value or null if not found
     */
    public function get_setting($key) {
        return isset($this->options[$key]) ? $this->options[$key] : null;
    }

    /**
     * Get all settings
     *
     * @return array All current settings
     */
    public function get_settings() {
        return $this->options;
    }
}
