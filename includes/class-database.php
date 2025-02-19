<?php
/**
 * Database handling class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CLT_Database {
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $login_table = $wpdb->prefix . 'custom_login_tracker';
        $failed_login_table = $wpdb->prefix . 'custom_login_tracker_failed';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Login history table
        $sql1 = "CREATE TABLE $login_table (
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
        
        // Failed login attempts table
        $sql2 = "CREATE TABLE $failed_login_table (
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
        dbDelta($sql1);
        dbDelta($sql2);
    }
    
    /**
     * Clean up old records
     */
    public function cleanup_old_records() {
        global $wpdb;
        $options = get_option('custom_login_tracker_options');
        $days = absint($options['data_retention_days']);
        
        if ($days > 0) {
            $login_table = $wpdb->prefix . 'custom_login_tracker';
            $failed_login_table = $wpdb->prefix . 'custom_login_tracker_failed';
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $login_table WHERE login_time < %s",
                $cutoff_date
            ));
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $failed_login_table WHERE attempt_time < %s",
                $cutoff_date
            ));
        }
    }
    
    /**
     * Get login records with filters
     */
    public function get_login_records($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_login_tracker';
        
        $defaults = array(
            'user_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'per_page' => 20,
            'page' => 1,
            'count_only' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        $where = array();
        $where_params = array();
        
        if ($args['user_id'] > 0) {
            $where[] = 'h.user_id = %d';
            $where_params[] = $args['user_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'h.login_time >= %s';
            $where_params[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'h.login_time <= %s';
            $where_params[] = $args['date_to'] . ' 23:59:59';
        }
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }
        
        if ($args['count_only']) {
            $query = "SELECT COUNT(id) FROM $table_name h $where_clause";
            if (!empty($where_params)) {
                $query = $wpdb->prepare($query, $where_params);
            }
            return $wpdb->get_var($query);
        }
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $query = "SELECT h.*, u.user_login, u.display_name
                 FROM $table_name h
                 LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
                 $where_clause
                 ORDER BY h.login_time DESC
                 LIMIT %d OFFSET %d";
                 
        $params = array_merge($where_params, array($args['per_page'], $offset));
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Get failed login records with filters
     */
    public function get_failed_login_records($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_login_tracker_failed';
        
        $defaults = array(
            'username' => '',
            'ip' => '',
            'date_from' => '',
            'date_to' => '',
            'per_page' => 20,
            'page' => 1,
            'count_only' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        $where = array();
        $where_params = array();
        
        if (!empty($args['username'])) {
            $where[] = 'username = %s';
            $where_params[] = $args['username'];
        }
        
        if (!empty($args['ip'])) {
            $where[] = 'ip_address = %s';
            $where_params[] = $args['ip'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'attempt_time >= %s';
            $where_params[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'attempt_time <= %s';
            $where_params[] = $args['date_to'] . ' 23:59:59';
        }
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }
        
        if ($args['count_only']) {
            $query = "SELECT COUNT(id) FROM $table_name $where_clause";
            if (!empty($where_params)) {
                $query = $wpdb->prepare($query, $where_params);
            }
            return $wpdb->get_var($query);
        }
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $query = "SELECT *
                 FROM $table_name
                 $where_clause
                 ORDER BY attempt_time DESC
                 LIMIT %d OFFSET %d";
                 
        $params = array_merge($where_params, array($args['per_page'], $offset));
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Get login statistics
     */
    public function get_statistics() {
        global $wpdb;
        $login_table = $wpdb->prefix . 'custom_login_tracker';
        $failed_table = $wpdb->prefix . 'custom_login_tracker_failed';
        
        $stats = array();
        
        // Total logins
        $stats['total_logins'] = $wpdb->get_var("SELECT COUNT(*) FROM $login_table");
        
        // Total failed logins
        $stats['total_failed'] = $wpdb->get_var("SELECT COUNT(*) FROM $failed_table");
        
        // Logins today
        $today = date('Y-m-d');
        $stats['logins_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $login_table WHERE DATE(login_time) = %s",
            $today
        ));
        
        // Failed logins today
        $stats['failed_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $failed_table WHERE DATE(attempt_time) = %s",
            $today
        ));
        
        // Average session duration
        $stats['avg_session'] = $wpdb->get_var(
            "SELECT AVG(session_duration) FROM $login_table WHERE session_duration IS NOT NULL"
        );
        
        // Most active user
        $most_active = $wpdb->get_row(
            "SELECT user_id, COUNT(*) as login_count 
             FROM $login_table 
             GROUP BY user_id 
             ORDER BY login_count DESC 
             LIMIT 1"
        );
        
        if ($most_active) {
            $user = get_userdata($most_active->user_id);
            $stats['most_active_user'] = array(
                'user_id' => $most_active->user_id,
                'user_login' => $user ? $user->user_login : 'Unknown',
                'display_name' => $user ? $user->display_name : 'Unknown',
                'login_count' => $most_active->login_count
            );
        }
        
        return $stats;
    }
}