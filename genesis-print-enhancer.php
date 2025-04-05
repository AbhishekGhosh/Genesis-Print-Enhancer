<?php
/**
 * Plugin Name: Genesis Print Enhancer
 * Description: Adds A4 print functionality with header and watermark support for Genesis. Logs prints with device and IP. Includes shortcode and Font Awesome print icon.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

define('GPE_PLUGIN_DIR', plugin_dir_path(__FILE__));

register_activation_hook(__FILE__, 'gpe_create_log_table');
function gpe_create_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gpe_print_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        ip_address VARCHAR(100),
        user_agent TEXT,
        date_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Enqueue frontend scripts and styles
add_action('wp_enqueue_scripts', 'gpe_enqueue_assets');
function gpe_enqueue_assets() {
    wp_enqueue_style('gpe-style', plugin_dir_url(__FILE__) . 'css/print-style.css', [], '1.0', 'print');
    wp_enqueue_script('gpe-script', plugin_dir_url(__FILE__) . 'js/print-script.js', ['jquery'], null, true);
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css');
}

// Shortcode for print button
add_shortcode('print_button', 'gpe_print_button');
function gpe_print_button() {
    return '<div class="gpe-print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print this page</div>';
}

// Log the print
add_action('wp_footer', 'gpe_log_print');
function gpe_log_print() {
    if (is_single()) {
        ?>
        <script>
            jQuery(document).on('click', '.gpe-print-btn', function () {
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'gpe_save_log',
                    post_id: <?php echo get_the_ID(); ?>
                });
            });
        </script>
        <?php
    }
}

add_action('wp_ajax_gpe_save_log', 'gpe_save_log');
add_action('wp_ajax_nopriv_gpe_save_log', 'gpe_save_log');
function gpe_save_log() {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'gpe_print_logs',
        [
            'post_id' => intval($_POST['post_id']),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        ]
    );
    wp_die();
}

// Admin menu
add_action('admin_menu', 'gpe_admin_menu');
function gpe_admin_menu() {
    add_menu_page('Print Settings', 'Print Settings', 'manage_options', 'gpe_settings', 'gpe_settings_page', 'dashicons-printer', 70);
    add_submenu_page('gpe_settings', 'Print Logs', 'Print Logs', 'manage_options', 'gpe_logs', 'gpe_logs_page');
}

// Settings page
function gpe_settings_page() {
    if ($_POST['gpe_save']) {
        update_option('gpe_header_image', esc_url($_POST['gpe_header_image']));
        update_option('gpe_watermark_image', esc_url($_POST['gpe_watermark_image']));
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Print Settings</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Header Image URL:</th>
                    <td><input type="text" name="gpe_header_image" value="<?php echo esc_attr(get_option('gpe_header_image')); ?>" class="large-text" /></td>
                </tr>
                <tr>
                    <th>Watermark Image URL:</th>
                    <td><input type="text" name="gpe_watermark_image" value="<?php echo esc_attr(get_option('gpe_watermark_image')); ?>" class="large-text" /></td>
                </tr>
            </table>
            <p><input type="submit" name="gpe_save" class="button-primary" value="Save Changes" /></p>
        </form>
    </div>
    <?php
}

// Logs page
function gpe_logs_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'gpe_print_logs';

    if (isset($_GET['delete_log'])) {
        $wpdb->delete($table, ['id' => intval($_GET['delete_log'])]);
    }

    $paged = max(1, intval($_GET['paged'] ?? 1));
    $limit = 10;
    $offset = ($paged - 1) * $limit;

    $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY date_time DESC LIMIT $offset, $limit");
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $total_pages = ceil($total / $limit);

    echo '<div class="wrap"><h1>Print Logs</h1>';
    echo '<table class="widefat"><thead><tr><th>ID</th><th>Post</th><th>IP</th><th>Device</th><th>Date</th><th>Action</th></tr></thead><tbody>';

    foreach ($logs as $log) {
        echo '<tr>';
        echo "<td>{$log->id}</td>";
        echo '<td><a href="' . get_permalink($log->post_id) . '" target="_blank">' . get_the_title($log->post_id) . '</a></td>';
        echo "<td>{$log->ip_address}</td>";
        echo "<td>{$log->user_agent}</td>";
        echo "<td>{$log->date_time}</td>";
        echo '<td><a href="?page=gpe_logs&delete_log=' . $log->id . '" class="button">Delete</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<div class="tablenav"><div class="tablenav-pages">';
    for ($i = 1; $i <= $total_pages; $i++) {
        echo "<a class='button' href='?page=gpe_logs&paged=$i'>$i</a> ";
    }
    echo '</div></div></div>';
}
