<?php
/**
 * Uninstall Custom Login Tracker
 *
 * @package Custom_Login_Tracker
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Get options to check if data should be removed
$options = get_option('custom_login_tracker_options');
$remove_data = isset($options['remove_data_on_uninstall']) ? $options['remove_data_on_uninstall'] : false;

// Only remove data if the option is enabled
if ($remove_data) {
    global $wpdb;
    
    // Delete tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}custom_login_tracker");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}custom_login_tracker_failed");
    
    // Delete options
    delete_option('custom_login_tracker_options');
    
    // Clear scheduled hooks
    wp_clear_scheduled_hook('custom_login_tracker_cleanup');
}