<?php
/**
 * Login tracking functionality
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CLT_Tracker {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WordPress actions
        add_action('wp_login', array($this, 'track_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'track_user_logout'));
        add_action('wp_login_failed', array($this, 'track_failed_login'));
        add_action('custom_login_tracker_cleanup', array($this, 'cleanup_records'));
    }
    
    /**
     * Track user login
     */
    public function track_user_login($user_login, $user) {
        global $wpdb;
        $options = get_option('custom_login_tracker_options');
        $table_name = $wpdb->prefix . 'custom_login_tracker';
        
        $data = array(
            'user_id' => $user->ID,
            'ip_address' => $this->get_ip_address(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        );
        
        // Add location data if enabled
        if (isset($options['track_location_data']) && $options['track_location_data']) {
            $location_data = $this->get_location_data($data['ip_address']);
            if ($location_data) {
                $data['country'] = $location_data['country'];
                $data['city'] = $location_data['city'];
            }
        }
        
        // Add browser/OS data if enabled
        if (isset($options['track_browser_data']) && $options['track_browser_data']) {
            $browser_data = $this->parse_user_agent($data['user_agent']);
            $data['browser'] = $browser_data['browser'];
            $data['browser_version'] = $browser_data['version'];
            $data['os'] = $browser_data['platform'];
            $data['is_mobile'] = $browser_data['is_mobile'] ? 1 : 0;
        }
        
        $wpdb->insert($table_name, $data);
        
        // Store the record ID in a user session for logout tracking
        if (!isset($_SESSION)) {
            session_start();
        }
        $_SESSION['custom_login_tracker_id'] = $wpdb->insert_id;
    }
    
    /**
     * Track user logout
     */
    public function track_user_logout() {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (isset($_SESSION['custom_login_tracker_id'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'custom_login_tracker';
            $record_id = intval($_SESSION['custom_login_tracker_id']);
            
            // Get login time
            $login_record = $wpdb->get_row($wpdb->prepare(
                "SELECT login_time FROM $table_name WHERE id = %d",
                $record_id
            ));
            
            if ($login_record) {
                $logout_time = current_time('mysql', true);
                $login_time = strtotime($login_record->login_time);
                $session_duration = strtotime($logout_time) - $login_time;
                
                $wpdb->update(
                    $table_name,
                    array(
                        'logout_time' => $logout_time,
                        'session_duration' => $session_duration
                    ),
                    array('id' => $record_id)
                );
            }
            
            unset($_SESSION['custom_login_tracker_id']);
        }
    }
    
    /**
     * Track failed login attempts
     */
    public function track_failed_login($username) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_login_tracker_failed';
        $options = get_option('custom_login_tracker_options');
        
        $data = array(
            'username' => $username,
            'ip_address' => $this->get_ip_address(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'failure_reason' => 'Invalid credentials'
        );
        
        $wpdb->insert($table_name, $data);
        
        // Check if we need to send an alert
        if (isset($options['alert_threshold']) && $options['alert_threshold'] > 0) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                WHERE ip_address = %s 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                $data['ip_address']
            ));
            
            if ($count >= $options['alert_threshold']) {
                $this->send_alert_email($data['ip_address'], $count, $username);
            }
        }
    }
    
    /**
     * Send alert email
     */
    private function send_alert_email($ip_address, $count, $username) {
        $options = get_option('custom_login_tracker_options');
        $to = $options['alert_email'];
        $subject = sprintf(__('[%s] Security Alert: Multiple Failed Login Attempts', 'custom-login-tracker'), get_bloginfo('name'));
        
        $message = sprintf(
            __('There have been %d failed login attempts from IP address %s within the last hour. The most recent attempt was for username: %s', 'custom-login-tracker'),
            $count,
            $ip_address,
            $username
        );
        
        $message .= "\n\n";
        $message .= sprintf(
            __('You can view all failed login attempts here: %s', 'custom-login-tracker'),
            admin_url('users.php?page=custom-login-tracker-failed')
        );
        
        wp_mail($to, $subject, $message);
    }
    
    /**
     * Cleanup old records
     */
    public function cleanup_records() {
        $database = new CLT_Database();
        $database->cleanup_old_records();
    }
    
    /**
     * Get IP address
     */
    private function get_ip_address() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'];
    }
    
    /**
     * Get location data
     */
    private function get_location_data($ip) {
        if ($ip == '127.0.0.1' || $ip == '::1') {
            return array(
                'country' => 'Local',
                'city' => 'Local'
            );
        }
        
        // Use a third-party geolocation API or local database
        // This is a simplified example using ipinfo.io
        $response = wp_remote_get("https://ipinfo.io/{$ip}/json");
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['country']) && isset($data['city'])) {
            return array(
                'country' => sanitize_text_field($data['country']),
                'city' => sanitize_text_field($data['city'])
            );
        }
        
        return false;
    }
    
    /**
     * Parse user agent
     */
    private function parse_user_agent($user_agent) {
        $browser  = 'Unknown';
        $version  = 'Unknown';
        $platform = 'Unknown';
        $is_mobile = false;
        
        // Detect platform
        if (preg_match('/android/i', $user_agent)) {
            $platform = 'Android';
            $is_mobile = true;
        } elseif (preg_match('/iphone|ipad|ipod/i', $user_agent)) {
            $platform = 'iOS';
            $is_mobile = true;
        } elseif (preg_match('/windows phone/i', $user_agent)) {
            $platform = 'Windows Phone';
            $is_mobile = true;
        } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
            $platform = 'Mac OS';
        } elseif (preg_match('/windows|win32/i', $user_agent)) {
            $platform = 'Windows';
        } elseif (preg_match('/linux/i', $user_agent)) {
            $platform = 'Linux';
        }
        
        // Detect browser
        if (preg_match('/MSIE|Trident/i', $user_agent)) {
            $browser = 'Internet Explorer';
            preg_match('/MSIE\s([0-9\.]+)/i', $user_agent, $matches);
            if (empty($matches)) {
                preg_match('/Trident\/([0-9\.]+)/i', $user_agent, $matches);
                $version = $matches[1] ?? 'Unknown';
            } else {
                $version = $matches[1] ?? 'Unknown';
            }
        } elseif (preg_match('/Edge/i', $user_agent)) {
            $browser = 'Microsoft Edge';
            preg_match('/Edge\/([0-9\.]+)/i', $user_agent, $matches);
            $version = $matches[1] ?? 'Unknown';
        } elseif (preg_match('/Edg/i', $user_agent)) {
            $browser = 'Microsoft Edge Chromium';
            preg_match('/Edg\/([0-9\.]+)/i', $user_agent, $matches);
            $version = $matches[1] ?? 'Unknown';
        } elseif (preg_match('/Firefox/i', $user_agent)) {
            $browser = 'Mozilla Firefox';
            preg_match('/Firefox\/([0-9\.]+)/i', $user_agent, $matches);
            $version = $matches[1] ?? 'Unknown';
        } elseif (preg_match('/Chrome/i', $user_agent)) {
            $browser = 'Google Chrome';
            preg_match('/Chrome\/([0-9\.]+)/i', $user_agent, $matches);
            $version = $matches[1] ?? 'Unknown';
        } elseif (preg_match('/Safari/i', $user_agent)) {
            $browser = 'Apple Safari';
            preg_match('/Version\/([0-9\.]+)/i', $user_agent, $matches);
            $version = $matches[1] ?? 'Unknown';
        } elseif (preg_match('/Opera/i', $user_agent)) {
            $browser = 'Opera';
            preg_match('/Opera\/([0-9\.]+)/i', $user_agent, $matches);
            $version = $matches[1] ?? 'Unknown';
        } elseif (preg_match('/OPR/i', $user_agent)) {
            $browser = 'Opera';
            preg_match('/OPR\/([0-9\.]+)/i', $user_agent, $matches);
            $version = $matches[1] ?? 'Unknown';
        }
        
        // Additional mobile detection
        if (!$is_mobile && preg_match('/mobile|tablet/i', $user_agent)) {
            $is_mobile = true;
        }
        
        return array(
            'browser' => $browser,
            'version' => $version,
            'platform' => $platform,
            'is_mobile' => $is_mobile
        );
    }
}