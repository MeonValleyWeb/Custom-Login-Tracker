<?php
/**
 * Admin interface handling
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CLT_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_export_login_history', array($this, 'export_login_history'));
        add_action('wp_ajax_export_failed_logins', array($this, 'export_failed_logins'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_users_page(
            __('Login Tracker', 'custom-login-tracker'),
            __('Login Tracker', 'custom-login-tracker'),
            'manage_options',
            'custom-login-tracker',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'users.php',
            __('Failed Login Attempts', 'custom-login-tracker'),
            __('Failed Logins', 'custom-login-tracker'),
            'manage_options',
            'custom-login-tracker-failed',
            array($this, 'render_failed_logins_page')
        );
        
        add_submenu_page(
            'users.php',
            __('Login Tracker Settings', 'custom-login-tracker'),
            __('Login Tracker Settings', 'custom-login-tracker'),
            'manage_options',
            'custom-login-tracker-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'users.php',
            __('Login Statistics', 'custom-login-tracker'),
            __('Login Statistics', 'custom-login-tracker'),
            'manage_options',
            'custom-login-tracker-stats',
            array($this, 'render_statistics_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'custom-login-tracker') !== false) {
            wp_enqueue_style('custom-login-tracker-admin', CLT_PLUGIN_URL . 'assets/css/admin.css', array(), CLT_VERSION);
            wp_enqueue_script('custom-login-tracker-admin', CLT_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-datepicker'), CLT_VERSION, true);
            wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
            
            wp_localize_script('custom-login-tracker-admin', 'customLoginTracker', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('custom-login-tracker-nonce'),
                'confirm_delete' => __('Are you sure you want to delete this record?', 'custom-login-tracker')
            ));
        }
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        $options = get_option('custom_login_tracker_options');
        
        if (isset($options['dashboard_widget_enabled']) && $options['dashboard_widget_enabled']) {
            wp_add_dashboard_widget(
                'custom_login_tracker_widget',
                __('Recent Login Activity', 'custom-login-tracker'),
                array($this, 'render_dashboard_widget')
            );
        }
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_login_tracker';
        
        // Get login history
        $login_history = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT h.*, u.user_login, u.display_name
                FROM $table_name h
                LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
                ORDER BY h.login_time DESC
                LIMIT %d",
                5
            )
        );
        
        if (empty($login_history)) {
            echo '<p>' . __('No login history found.', 'custom-login-tracker') . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>
                <th>' . __('User', 'custom-login-tracker') . '</th>
                <th>' . __('Login Time', 'custom-login-tracker') . '</th>
                <th>' . __('IP Address', 'custom-login-tracker') . '</th>
            </tr></thead><tbody>';
            
            foreach ($login_history as $entry) {
                echo '<tr>';
                if (!empty($entry->display_name)) {
                    echo '<td>' . esc_html($entry->display_name) . ' (' . esc_html($entry->user_login) . ')</td>';
                } else {
                    echo '<td>' . esc_html($entry->user_id) . ' (' . __('User deleted', 'custom-login-tracker') . ')</td>';
                }
                echo '<td>' . esc_html(get_date_from_gmt($entry->login_time)) . '</td>';
                echo '<td>' . esc_html($entry->ip_address) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '<p class="dashboard-widget-control-actions">';
            echo '<a href="' . esc_url(admin_url('users.php?page=custom-login-tracker')) . '">' . __('View All', 'custom-login-tracker') . '</a>';
            echo '</p>';
        }
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $database = new CLT_Database();
        
        // Handle filters
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        // Get total count
        $total_items = $database->get_login_records(array(
            'user_id' => $user_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'count_only' => true
        ));
        
        $total_pages = ceil($total_items / $per_page);
        
        // Get login history
        $login_history = $database->get_login_records(array(
            'user_id' => $user_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'per_page' => $per_page,
            'page' => $current_page
        ));
        
        // Get all users for filter dropdown
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_login_tracker';
        $users = $wpdb->get_results("
            SELECT DISTINCT u.ID, u.user_login, u.display_name
            FROM {$wpdb->users} u
            INNER JOIN $table_name h ON u.ID = h.user_id
            ORDER BY u.display_name
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Login History', 'custom-login-tracker'); ?></h1>
            
            <!-- Export button -->
            <div class="export-controls">
                <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                    <input type="hidden" name="action" value="export_login_history">
                    <?php wp_nonce_field('custom-login-tracker-export', 'export_nonce'); ?>
                    <button type="submit" class="button button-secondary">
                        <span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span>
                        <?php _e('Export to CSV', 'custom-login-tracker'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="page" value="custom-login-tracker">
                    
                    <div class="alignleft actions">
                        <select name="user_id">
                            <option value=""><?php _e('All Users', 'custom-login-tracker'); ?></option>
                            <?php foreach ($users as $user) : ?>
                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user_id, $user->ID); ?>>
                                    <?php echo esc_html($user->display_name) . ' (' . esc_html($user->user_login) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label>
                            <span><?php _e('From:', 'custom-login-tracker'); ?></span>
                            <input type="text" name="date_from" class="date-picker" value="<?php echo esc_attr($date_from); ?>" placeholder="YYYY-MM-DD">
                        </label>
                        
                        <label>
                            <span><?php _e('To:', 'custom-login-tracker'); ?></span>
                            <input type="text" name="date_to" class="date-picker" value="<?php echo esc_attr($date_to); ?>" placeholder="YYYY-MM-DD">
                        </label>
                        
                        <input type="submit" class="button" value="<?php _e('Filter', 'custom-login-tracker'); ?>">
                    </div>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('User', 'custom-login-tracker'); ?></th>
                        <th scope="col"><?php _e('Login Time', 'custom-login-tracker'); ?></th>
                        <th scope="col"><?php _e('Logout Time', 'custom-login-tracker'); ?></th>
                        <th scope="col"><?php _e('Session Duration', 'custom-login-tracker'); ?></th>
                        <th scope="col"><?php _e('IP Address', 'custom-login-tracker'); ?></th>
                        <th scope="col"><?php _e('Location', 'custom-login-tracker'); ?></th>
                        <th scope="col"><?php _e('Browser/Device', 'custom-login-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($login_history)) : ?>
                        <tr>
                            <td colspan="7"><?php _e('No login history found.', 'custom-login-tracker'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($login_history as $entry) : ?>
                            <tr>
                                <td>
                                    <?php if (!empty($entry->display_name)) : ?>
                                        <?php echo esc_html($entry->display_name); ?> (<?php echo esc_html($entry->user_login); ?>)
                                    <?php else : ?>
                                        <?php echo esc_html($entry->user_id); ?> (<?php _e('User deleted', 'custom-login-tracker'); ?>)
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(get_date_from_gmt($entry->login_time)); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($entry->logout_time)) {
                                        echo esc_html(get_date_from_gmt($entry->logout_time));
                                    } else {
                                        echo '<em>' . __('Session active or browser closed', 'custom-login-tracker') . '</em>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($entry->session_duration)) {
                                        echo $this->format_duration($entry->session_duration);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($entry->ip_address); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($entry->country) && !empty($entry->city)) {
                                        echo esc_html($entry->city) . ', ' . esc_html($entry->country);
                                    } elseif (!empty($entry->country)) {
                                        echo esc_html($entry->country);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $device_info = array();
                                    
                                    if (!empty($entry->browser)) {
                                        $browser_info = $entry->browser;
                                        if (!empty($entry->browser_version) && $entry->browser_version !== 'Unknown') {
                                            $browser_info .= ' ' . $entry->browser_version;
                                        }
                                        $device_info[] = $browser_info;
                                    }
                                    
                                    if (!empty($entry->os)) {
                                        $device_info[] = $entry->os;
                                    }
                                    
                                    if (!empty($entry->is_mobile) && $entry->is_mobile) {
                                        $device_info[] = __('Mobile', 'custom-login-tracker');
                                    }
                                    
                                    echo !empty($device_info) ? esc_html(implode(' / ', $device_info)) : '—';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php echo sprintf(
                                _n('%s item', '%s items', $total_items, 'custom-login-tracker'),
                                number_format_i18n($total_items)
                            ); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page,
                            ));
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render failed logins page
     */
    public function render_failed_logins_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $database = new CLT_Database();
        
        // Handle filters
        $username = isset($_GET['username']) ? sanitize_text_field($_GET['username']) : '';
        $ip = isset($_GET['ip']) ? sanitize_text_field($_GET['ip']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        // Get total count
        $total_items = $database->get_failed_login_records(array(
            'username' => $username,
            'ip' => $ip,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'count_only' => true
        ));
        
        $total_pages = ceil($total_items / $per_page);
        
        // Get failed login records
        $failed_logins = $database->get_failed_login_records(array(
            'username' => $username,
            'ip' => $ip,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'per_page' => $per_page,
            'page' => $current_page
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Failed Login Attempts', 'custom-login-tracker'); ?></h1>
            
            <!-- Export button -->
            <div class="export-controls">
                <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                    <input type="hidden" name="action" value="export_failed_logins">
                    <?php wp_nonce_field('custom-login-tracker-export', 'export_nonce'); ?>
                    <button type="submit" class="button button-secondary">
                        <span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span>
                        <?php _e('Export to CSV', 'custom-login-tracker'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="page" value="custom-login-tracker-failed">
                    
                    <div class="alignleft actions">
                        <input type="text" name="username" placeholder="<?php _e('Username', 'custom-login-tracker'); ?>" value="<?php echo esc_attr($username); ?>">
                        
                        <input type="text" name="ip" placeholder="<?php _e('IP Address', 'custom-login-tracker'); ?>" value="<?php echo esc_attr($ip); ?>">
                        
                        <label>
                            <span><?php _e('From:', 'custom-login-tracker'); ?></span>
                            <input type="text" name="date_from" class="date-picker" value="<?php echo esc_attr($date_from); ?>" placeholder="YYYY-MM-DD">
                        </label>
                        
                        <label>
                            <span><?php _e('To:', 'custom-login-tracker'); ?></span>
                            <input type="text" name="date_to" class="date-picker" value="<?php echo esc_attr($date_to); ?>" placeholder="YYYY-MM-DD">
                        </label>
                        
                        <input type="submit" class="button" value="<?php _e('Filter', 'custom-login-tracker'); ?>">
                    </div>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('Username', 'custom-login-tracker'); ?></th>
                        <th scope="col"><?php _e('Attempt Time', 'custom-login-tracker'); ?></th>
                        <th scope="col"><?php _e('IP Address', 'custom-login-tracker'); ?></th>
                        <th scope="col"><?php _e('User Agent', 'custom-login-tracker'); ?></th>
                        <th scope="col"><?php _e('Failure Reason', 'custom-login-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($failed_logins)) : ?>
                        <tr>
                            <td colspan="5"><?php _e('No failed login attempts found.', 'custom-login-tracker'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($failed_logins as $entry) : ?>
                            <tr>
                                <td><?php echo esc_html($entry->username); ?></td>
                                <td><?php echo esc_html(get_date_from_gmt($entry->attempt_time)); ?></td>
                                <td><?php echo esc_html($entry->ip_address); ?></td>
                                <td class="user-agent-column">
                                    <div class="user-agent-truncated">
                                        <?php echo esc_html(substr($entry->user_agent, 0, 100)); ?>
                                        <?php if (strlen($entry->user_agent) > 100) : ?>
                                            <span class="show-more">...</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (strlen($entry->user_agent) > 100) : ?>
                                        <div class="user-agent-full" style="display: none;">
                                            <?php echo esc_html($entry->user_agent); ?>
                                            <span class="show-less"><?php _e('Show less', 'custom-login-tracker'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($entry->failure_reason); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php echo sprintf(
                                _n('%s item', '%s items', $total_items, 'custom-login-tracker'),
                                number_format_i18n($total_items)
                            ); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page,
                            ));
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = new CLT_Settings();
        $settings->render_settings_page();
    }
    
    /**
     * Render statistics page
     */
    public function render_statistics_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $database = new CLT_Database();
        $stats = $database->get_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Login Statistics', 'custom-login-tracker'); ?></h1>
            
            <div class="login-stats-overview">
                <div class="login-stats-card">
                    <h2><?php _e('Login Overview', 'custom-login-tracker'); ?></h2>
                    <table class="widefat">
                        <tr>
                            <th><?php _e('Total Logins', 'custom-login-tracker'); ?></th>
                            <td><?php echo number_format_i18n($stats['total_logins']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Logins Today', 'custom-login-tracker'); ?></th>
                            <td><?php echo number_format_i18n($stats['logins_today']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Total Failed Logins', 'custom-login-tracker'); ?></th>
                            <td><?php echo number_format_i18n($stats['total_failed']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Failed Logins Today', 'custom-login-tracker'); ?></th>
                            <td><?php echo number_format_i18n($stats['failed_today']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Average Session Duration', 'custom-login-tracker'); ?></th>
                            <td>
                                <?php 
                                if (!empty($stats['avg_session'])) {
                                    echo $this->format_duration($stats['avg_session']);
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Most Active User', 'custom-login-tracker'); ?></th>
                            <td>
                                <?php 
                                if (!empty($stats['most_active_user'])) {
                                    echo esc_html($stats['most_active_user']['display_name']) . ' (' . 
                                         esc_html($stats['most_active_user']['user_login']) . ') - ' . 
                                         sprintf(_n('%s login', '%s logins', $stats['most_active_user']['login_count'], 'custom-login-tracker'), 
                                                number_format_i18n($stats['most_active_user']['login_count']));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Export login history to CSV
     */
    public function export_login_history() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'custom-login-tracker'));
        }
        
        check_admin_referer('custom-login-tracker-export', 'export_nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_login_tracker';
        
        $records = $wpdb->get_results(
            "SELECT h.*, u.user_login, u.display_name
            FROM $table_name h
            LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
            ORDER BY h.login_time DESC"
        );
        
        $filename = 'login-history-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, array(
            __('User ID', 'custom-login-tracker'),
            __('Username', 'custom-login-tracker'),
            __('Display Name', 'custom-login-tracker'),
            __('Login Time', 'custom-login-tracker'),
            __('Logout Time', 'custom-login-tracker'),
            __('Session Duration (seconds)', 'custom-login-tracker'),
            __('IP Address', 'custom-login-tracker'),
            __('Country', 'custom-login-tracker'),
            __('City', 'custom-login-tracker'),
            __('Browser', 'custom-login-tracker'),
            __('Browser Version', 'custom-login-tracker'),
            __('OS', 'custom-login-tracker'),
            __('Mobile Device', 'custom-login-tracker'),
            __('User Agent', 'custom-login-tracker')
        ));
        
        foreach ($records as $record) {
            fputcsv($output, array(
                $record->user_id,
                $record->user_login ?? 'Unknown',
                $record->display_name ?? 'Unknown',
                get_date_from_gmt($record->login_time),
                !empty($record->logout_time) ? get_date_from_gmt($record->logout_time) : '',
                $record->session_duration,
                $record->ip_address,
                $record->country ?? '',
                $record->city ?? '',
                $record->browser ?? '',
                $record->browser_version ?? '',
                $record->os ?? '',
                $record->is_mobile ? __('Yes', 'custom-login-tracker') : __('No', 'custom-login-tracker'),
                $record->user_agent
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export failed logins to CSV
     */
    public function export_failed_logins() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'custom-login-tracker'));
        }
        
        check_admin_referer('custom-login-tracker-export', 'export_nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_login_tracker_failed';
        
        $records = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY attempt_time DESC"
        );
        
        $filename = 'failed-logins-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, array(
            __('Username', 'custom-login-tracker'),
            __('Attempt Time', 'custom-login-tracker'),
            __('IP Address', 'custom-login-tracker'),
            __('Failure Reason', 'custom-login-tracker'),
            __('User Agent', 'custom-login-tracker')
        ));
        
        foreach ($records as $record) {
            fputcsv($output, array(
                $record->username,
                get_date_from_gmt($record->attempt_time),
                $record->ip_address,
                $record->failure_reason,
                $record->user_agent
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Format duration into human readable string
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return sprintf(_n('%s second', '%s seconds', $seconds, 'custom-login-tracker'), number_format_i18n($seconds));
        }
        
        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return sprintf(_n('%s minute', '%s minutes', $minutes, 'custom-login-tracker'), number_format_i18n($minutes));
        }
        
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;
        
        if ($hours < 24) {
            if ($minutes == 0) {
                return sprintf(_n('%s hour', '%s hours', $hours, 'custom-login-tracker'), number_format_i18n($hours));
            } else {
                return sprintf(
                    __('%s hours, %s minutes', 'custom-login-tracker'),
                    number_format_i18n($hours),
                    number_format_i18n($minutes)
                );
            }
        }
        
        $days = floor($hours / 24);
        $hours = $hours % 24;
        
        if ($hours == 0) {
            return sprintf(_n('%s day', '%s days', $days, 'custom-login-tracker'), number_format_i18n($days));
        } else {
            return sprintf(
                __('%s days, %s hours', 'custom-login-tracker'),
                number_format_i18n($days),
                number_format_i18n($hours)
            );
        }
    }
}