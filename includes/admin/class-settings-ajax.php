<?php
namespace TursoDBAdapter\Admin;

use TursoDBAdapter\Client;
use TursoDBAdapter\Installer;
use TursoDBAdapter\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings AJAX Handler
 */
class Settings_Ajax {
    /**
     * @var Settings
     */
    private $settings;

    /**
     * Constructor
     *
     * @param Settings $settings Settings instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_turso_test_connection', [$this, 'test_connection']);
        add_action('wp_ajax_turso_initialize_tables', [$this, 'initialize_tables']);
        add_action('wp_ajax_turso_recreate_tables', [$this, 'recreate_tables']);
    }

    /**
     * Test connection callback
     */
    public function test_connection() {
        check_ajax_referer('turso_db_admin', 'nonce');

        try {
            $client = new Client($this->settings);
            if ($client->init_connection()) {
                wp_send_json_success([
                    'message' => __('Connection successful! Your Turso database is properly configured.', 'turso-db-adapter')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Connection failed. Please check your database URL and authentication token.', 'turso-db-adapter')
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Connection error: %s', 'turso-db-adapter'),
                    $e->getMessage()
                )
            ]);
        }
    }

    /**
     * Initialize tables callback
     */
    public function initialize_tables() {
        check_ajax_referer('turso_db_admin', 'nonce');

        try {
            $installer = new Installer(new Client($this->settings));
            $result = $installer->init();

            if ($result) {
                wp_send_json_success([
                    'message' => __('WordPress tables have been successfully initialized in your Turso database.', 'turso-db-adapter')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to initialize tables. Please check your connection settings and try again.', 'turso-db-adapter')
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Error initializing tables: %s', 'turso-db-adapter'),
                    $e->getMessage()
                )
            ]);
        }
    }

    /**
     * Recreate tables callback
     */
    public function recreate_tables() {
        check_ajax_referer('turso_db_admin', 'nonce');

        try {
            $installer = new Installer(new Client($this->settings));
            $installer->drop_tables();
            $result = $installer->init();

            if ($result) {
                wp_send_json_success([
                    'message' => __('WordPress tables have been successfully recreated in your Turso database.', 'turso-db-adapter')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to recreate tables. Please check your connection settings and try again.', 'turso-db-adapter')
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Error recreating tables: %s', 'turso-db-adapter'),
                    $e->getMessage()
                )
            ]);
        }
    }
}
