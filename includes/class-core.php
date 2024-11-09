<?php
namespace TursoDBAdapter;

if (!defined('ABSPATH')) {
    exit;
}

class Core {
    /**
     * @var Core|null Instance of this class.
     */
    private static $instance = null;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var Client
     */
    private $client;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_components();
        $this->setup_hooks();
    }

    /**
     * Initialize component classes
     */
    private function init_components() {
        $this->settings = new Settings();
        $this->client = new Client($this->settings);
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        add_action('admin_menu', array($this->settings, 'add_settings_page'));
        add_action('admin_init', array($this->settings, 'register_settings'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'turso-db-adapter',
            false,
            dirname(plugin_basename(TURSO_DB_ADAPTER_PLUGIN_DIR)) . '/languages/'
        );
    }

    /**
     * Get client instance
     */
    public function get_client() {
        return $this->client;
    }

    /**
     * Get settings instance
     */
    public function get_settings() {
        return $this->settings;
    }
}