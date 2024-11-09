jQuery(document).ready(function($) {
    // Handle tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        window.location.href = $(this).attr('href');
    });

    // Handle "Use as WordPress Database" checkbox
    $('#use_as_wpdb').on('change', function() {
        if (this.checked && !confirm(tursoDbAdmin.wpdb_warning)) {
            this.checked = false;
        }
    });

    // Test Connection
    $('#turso-test-connection').on('click', function() {
        const button = $(this);
        const spinner = button.next('.spinner');
        const result = $('#connection-result');

        button.prop('disabled', true);
        spinner.addClass('is-active');
        result.html(tursoDbAdmin.testing_connection);

        $.ajax({
            url: tursoDbAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'turso_test_connection',
                nonce: tursoDbAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success is-dismissible"><p>' + 
                              response.data.message + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error is-dismissible"><p>' + 
                              response.data.message + '</p></div>');
                }
            },
            error: function() {
                result.html('<div class="notice notice-error is-dismissible"><p>' + 
                          'Connection test failed. Please try again.' + '</p></div>');
            },
            complete: function() {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });

    // Initialize Tables
    $('#turso-initialize-tables').on('click', function() {
        var $button = $(this);
        var $spinner = $button.siblings('.spinner');
        var $result = $('#tables-result');

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.html('<div class="notice notice-info"><p>' + tursoDbAdmin.initializing_tables + '</p></div>');

        $.ajax({
            url: tursoDbAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'turso_initialize_tables',
                nonce: tursoDbAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success is-dismissible"><p>' + 
                               response.data.message + '</p></div>');
                    // Reload page after a short delay to show the success message
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $result.html('<div class="notice notice-error is-dismissible"><p>' + 
                               response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error is-dismissible"><p>' + 
                           'Failed to initialize tables. Please try again.' + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Recreate Tables
    $('#turso-recreate-tables').on('click', function() {
        if (!confirm(tursoDbAdmin.confirm_recreate)) {
            return;
        }

        var $button = $(this);
        var $spinner = $button.siblings('.spinner');
        var $result = $('#tables-result');

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.html('<div class="notice notice-info"><p>' + tursoDbAdmin.recreating_tables + '</p></div>');

        $.ajax({
            url: tursoDbAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'turso_recreate_tables',
                nonce: tursoDbAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success is-dismissible"><p>' + 
                               response.data.message + '</p></div>');
                    // Reload page after a short delay to show the success message
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $result.html('<div class="notice notice-error is-dismissible"><p>' + 
                               response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error is-dismissible"><p>' + 
                           'Failed to recreate tables. Please try again.' + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Make notices dismissible
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut();
    });
});
