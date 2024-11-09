<?php
namespace TursoDBAdapter\Admin;

use TursoDBAdapter\Client;
use TursoDBAdapter\Installer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Tabs Handler
 */
class Settings_Tabs {
    /**
     * Get available tabs
     */
    public static function get_tabs() {
        return [
            'connection' => __('Connection Settings', 'turso-db-adapter'),
            'tables' => __('Database Tables', 'turso-db-adapter')
        ];
    }

    /**
     * Get current tab
     */
    public static function get_current_tab() {
        return isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'connection';
    }

    /**
     * Render tabs navigation
     */
    public static function render_tabs() {
        $current_tab = self::get_current_tab();
        ?>
        <h2 class="nav-tab-wrapper">
            <?php foreach (self::get_tabs() as $tab_id => $tab_name): ?>
                <a href="?page=turso-db-settings&tab=<?php echo esc_attr($tab_id); ?>" 
                   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_name); ?>
                </a>
            <?php endforeach; ?>
        </h2>
        <?php
    }

    /**
     * Render connection settings tab content
     */
    public static function render_connection_tab() {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('turso_db_options');
            do_settings_sections('turso-db-settings_connection');
            submit_button();
            ?>
        </form>

        <div class="turso-connection-test card">
            <h2><?php _e('Connection Test', 'turso-db-adapter'); ?></h2>
            <p><?php _e('Test your Turso database connection settings.', 'turso-db-adapter'); ?></p>
            <button type="button" class="button button-secondary" id="turso-test-connection">
                <?php _e('Test Connection', 'turso-db-adapter'); ?>
            </button>
            <span class="spinner"></span>
            <div id="connection-result"></div>
        </div>
        <?php
    }

    /**
     * Render tables tab content
     */
    public static function render_tables_tab($installer) {
        ?>
        <div class="turso-tables card">
            <h2><?php _e('WordPress Tables', 'turso-db-adapter'); ?></h2>
            <p><?php _e('Manage your WordPress database tables in Turso.', 'turso-db-adapter'); ?></p>
            <?php self::render_tables_status($installer); ?>
            
            <div class="table-actions">
                <button type="button" class="button button-secondary" id="turso-initialize-tables">
                    <?php _e('Initialize Tables', 'turso-db-adapter'); ?>
                </button>
                <button type="button" class="button button-secondary button-warning" id="turso-recreate-tables">
                    <?php _e('Recreate All Tables', 'turso-db-adapter'); ?>
                </button>
                <span class="spinner"></span>
            </div>
            <div id="tables-result"></div>
        </div>
        <?php
    }

    /**
     * Render tables status
     */
    private static function render_tables_status($installer) {
        $tables = [
            'wp_options' => 'Options',
            'wp_users' => 'Users',
            'wp_usermeta' => 'User Meta',
            'wp_posts' => 'Posts',
            'wp_postmeta' => 'Post Meta',
            'wp_comments' => 'Comments',
            'wp_commentmeta' => 'Comment Meta',
            'wp_terms' => 'Terms',
            'wp_term_taxonomy' => 'Term Taxonomy',
            'wp_term_relationships' => 'Term Relationships',
            'wp_termmeta' => 'Term Meta',
            'wp_links' => 'Links'
        ];

        $existing_tables = $installer->get_existing_tables();

        echo '<div class="table-status">';
        foreach ($tables as $table_name => $table_label) {
            $exists = in_array($table_name, $existing_tables);
            echo '<div class="table-status-item">';
            echo '<span class="status-indicator ' . ($exists ? 'status-exists' : 'status-missing') . '"></span>';
            echo esc_html($table_label) . ' (' . esc_html($table_name) . ')';
            echo '</div>';
        }
        echo '</div>';
    }
}
