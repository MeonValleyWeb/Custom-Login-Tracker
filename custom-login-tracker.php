<?php
/**
 * Plugin Name: Custom Login Tracker
 * Plugin URI: https://meonvalleyweb.com/plugins/custom-login-tracker
 * Description: Enhanced login tracking system for WordPress with support for Bedrock architecture
 * Version: 1.1.0
 * Author: Andrew Wilkinson
 * Author URI: https://meonvalleyweb.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: custom-login-tracker
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 *
 * @package Custom_Login_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CLT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLT_VERSION', '1.1.0');

// Require core files
require_once CLT_PLUGIN_DIR . 'includes/class-database.php';
require_once CLT_PLUGIN_DIR . 'includes/class-tracker.php';
require_once CLT_PLUGIN_DIR . 'includes/class-settings.php';
require_once CLT_PLUGIN_DIR . 'includes/class-admin.php';

// Initialize the plugin
function clt_initialize_plugin() {
    // Initialize database
    $database = new CLT_Database();
    
    // Initialize tracker
    $tracker = new CLT_Tracker();
    
    // Initialize settings
    $settings = new CLT_Settings();
    
    // Initialize admin
    $admin = new CLT_Admin();
}
add_action('plugins_loaded', 'clt_initialize_plugin');

// Activation hook
register_activation_hook(__FILE__, 'clt_activate_plugin');
function clt_activate_plugin() {
    global $wpdb;
    
    // Initialize database class
    require_once CLT_PLUGIN_DIR . 'includes/class-database.php';
    $database = new CLT_Database();
    
    // Ensure the database tables are created
    $database->create_tables();
    
    // Force-check if tables exist, if not create them directly
    $login_table = $wpdb->prefix . 'custom_login_tracker';
    $failed_login_table = $wpdb->prefix . 'custom_login_tracker_failed';
    $charset_collate = $wpdb->get_charset_collate();
    
    if($wpdb->get_var("SHOW TABLES LIKE '$failed_login_table'") != $failed_login_table) {
        $sql = "CREATE TABLE $failed_login_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            username varchar(100) NOT NULL,
            attempt_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            ip_address varchar(100) NOT NULL,
            user_agent text NOT NULL,
            failure_reason varchar(255) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY ip_address (ip_address),
            KEY attempt_time (attempt_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    if($wpdb->get_var("SHOW TABLES LIKE '$login_table'") != $login_table) {
        $sql = "CREATE TABLE $login_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            login_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            logout_time datetime DEFAULT NULL,
            session_duration int(11) DEFAULT NULL,
            ip_address varchar(100) NOT NULL,
            user_agent text NOT NULL,
            country varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            browser_version varchar(50) DEFAULT NULL,
            os varchar(100) DEFAULT NULL,
            is_mobile tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY login_time (login_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Set default options
    $default_options = array(
        'data_retention_days' => 90,
        'track_location_data' => 1,
        'track_browser_data' => 1,
        'alert_threshold' => 5,
        'alert_email' => get_option('admin_email'),
        'dashboard_widget_enabled' => 1
    );
    
    add_option('custom_login_tracker_options', $default_options);
    
    // Schedule cleanup cron job
    if (!wp_next_scheduled('custom_login_tracker_cleanup')) {
        wp_schedule_event(time(), 'daily', 'custom_login_tracker_cleanup');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'clt_deactivate_plugin');
function clt_deactivate_plugin() {
    // Clear scheduled hooks
    wp_clear_scheduled_hook('custom_login_tracker_cleanup');
}