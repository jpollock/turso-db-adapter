<?php
// templates/admin/settings-page.php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form action="options.php" method="post">
        <?php
        settings_fields('turso_db_options');
        do_settings_sections('turso-db-settings');
        submit_button(__('Save Settings', 'turso-db-adapter'));
        ?>
    </form>

    <div class="turso-db-connection-test">
        <h2><?php _e('Connection Test', 'turso-db-adapter'); ?></h2>
        <button type="button" class="button button-secondary" id="turso-test-connection">
            <?php _e('Test Connection', 'turso-db-adapter'); ?>
        </button>
        <span class="spinner"></span>
        <div class="test-result"></div>
    </div>
</div>