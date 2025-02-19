<?php
/**
 * Settings page and options handling
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CLT_Settings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('custom_login_tracker', 'custom_login_tracker_options', array(
            'sanitize_callback' => array($this, 'sanitize_options')
        ));
        
        add_settings_section(
            'custom_login_tracker_main',
            __('Login Tracker Settings', 'custom-login-tracker'),
            array($this, 'settings_section_callback'),
            'custom_login_tracker'
        );
        
        add_settings_field(
            'data_retention_days',
            __('Data Retention Period (days)', 'custom-login-tracker'),
            array($this, 'retention_days_callback'),
            'custom_login_tracker',
            'custom_login_tracker_main'
        );
        
        add_settings_field(
            'track_location_data',
            __('Track Location Data', 'custom-login-tracker'),
            array($this, 'checkbox_callback'),
            'custom_login_tracker',
            'custom_login_tracker_main',
            array('option' => 'track_location_data')
        );
        
        add_settings_field(
            'track_browser_data',
            __('Track Browser/OS Data', 'custom-login-tracker'),
            array($this, 'checkbox_callback'),
            'custom_login_tracker',
            'custom_login_tracker_main',
            array('option' => 'track_browser_data')
        );
        
        add_settings_field(
            'alert_threshold',
            __('Failed Login Alert Threshold', 'custom-login-tracker'),
            array($this, 'alert_threshold_callback'),
            'custom_login_tracker',
            'custom_login_tracker_main'
        );
        
        add_settings_field(
            'alert_email',
            __('Alert Email Address', 'custom-login-tracker'),
            array($this, 'alert_email_callback'),
            'custom_login_tracker',
            'custom_login_tracker_main'
        );
        
        add_settings_field(
            'dashboard_widget_enabled',
            __('Show Dashboard Widget', 'custom-login-tracker'),
            array($this, 'checkbox_callback'),
            'custom_login_tracker',
            'custom_login_tracker_main',
            array('option' => 'dashboard_widget_enabled')
        );
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($options) {
        $sanitized = array();
        
        // Retention days
        $sanitized['data_retention_days'] = isset($options['data_retention_days']) ? 
            absint($options['data_retention_days']) : 90;
            
        if ($sanitized['data_retention_days'] < 1) {
            $sanitized['data_retention_days'] = 1;
        }
        if ($sanitized['data_retention_days'] > 3650) {
            $sanitized['data_retention_days'] = 3650;
        }
        
        // Checkboxes
        $sanitized['track_location_data'] = isset($options['track_location_data']) ? 1 : 0;
        $sanitized['track_browser_data'] = isset($options['track_browser_data']) ? 1 : 0;
        $sanitized['dashboard_widget_enabled'] = isset($options['dashboard_widget_enabled']) ? 1 : 0;
        
        // Alert threshold
        $sanitized['alert_threshold'] = isset($options['alert_threshold']) ? 
            absint($options['alert_threshold']) : 5;
            
        if ($sanitized['alert_threshold'] < 1) {
            $sanitized['alert_threshold'] = 1;
        }
        if ($sanitized['alert_threshold'] > 100) {
            $sanitized['alert_threshold'] = 100;
        }
        
        // Alert email
        $sanitized['alert_email'] = isset($options['alert_email']) ? 
            sanitize_email($options['alert_email']) : get_option('admin_email');
            
        if (!is_email($sanitized['alert_email'])) {
            $sanitized['alert_email'] = get_option('admin_email');
        }
        
        return $sanitized;
    }
    
    /**
     * Settings section description
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure how login tracking functions.', 'custom-login-tracker') . '</p>';
    }
    
    /**
     * Retention days field callback
     */
    public function retention_days_callback() {
        $options = get_option('custom_login_tracker_options');
        echo '<input type="number" min="1" max="3650" name="custom_login_tracker_options[data_retention_days]" value="' . esc_attr($options['data_retention_days']) . '" />';
        echo '<p class="description">' . __('Number of days to keep login records before automatic deletion. (1-3650)', 'custom-login-tracker') . '</p>';
    }
    
    /**
     * Checkbox field callback
     */
    public function checkbox_callback($args) {
        $options = get_option('custom_login_tracker_options');
        $option = $args['option'];
        echo '<input type="checkbox" name="custom_login_tracker_options[' . $option . ']" value="1" ' . checked(1, isset($options[$option]) ? $options[$option] : 0, false) . ' />';
        
        switch ($option) {
            case 'track_location_data':
                echo '<p class="description">' . __('Attempt to determine location (country/city) from IP address.', 'custom-login-tracker') . '</p>';
                break;
            case 'track_browser_data':  
                echo '<p class="description">' . __('Record browser, version, operating system and device type.', 'custom-login-tracker') . '</p>';
                break;
            case 'dashboard_widget_enabled':
                echo '<p class="description">' . __('Display a widget showing recent logins on the admin dashboard.', 'custom-login-tracker') . '</p>';
                break;
        }
    }
    
    /**
     * Alert threshold field callback
     */
    public function alert_threshold_callback() {
        $options = get_option('custom_login_tracker_options');
        echo '<input type="number" min="1" max="100" name="custom_login_tracker_options[alert_threshold]" value="' . esc_attr($options['alert_threshold']) . '" />';
        echo '<p class="description">' . __('Number of failed login attempts within an hour before sending an email alert.', 'custom-login-tracker') . '</p>';
    }
    
    /**
     * Alert email field callback
     */
    public function alert_email_callback() {
        $options = get_option('custom_login_tracker_options');
        echo '<input type="email" class="regular-text" name="custom_login_tracker_options[alert_email]" value="' . esc_attr($options['alert_email']) . '" />';
        echo '<p class="description">' . __('Email address to send security alerts to.', 'custom-login-tracker') . '</p>';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('custom_login_tracker');
                do_settings_sections('custom_login_tracker');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}