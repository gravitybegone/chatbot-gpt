<?php
if (!defined('ABSPATH')) exit;

function add_chatbot_log_management_menu() {
    add_submenu_page(
        'chatbot-analytics',    // Parent menu
        'Log Management',       // Page title
        'Log Management',       // Menu title
        'manage_options',       // Required capability
        'chatbot-log-management', // Menu slug
        'render_log_management_page' // Function
    );
}
add_action('admin_menu', 'add_chatbot_log_management_menu');

function render_log_management_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chatbot_interactions';

    // Handle purge all logs
    if (isset($_POST['purge_all_logs']) && check_admin_referer('purge_all_chatbot_logs')) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        echo '<div class="notice notice-success"><p>All chat logs have been purged.</p></div>';
    }

    // Get total count
    $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    ?>
    <div class="wrap">
        <h1>Chatbot Log Management</h1>
        
        <div class="card">
            <h2>Log Statistics</h2>
            <p>Total Log Entries: <?php echo number_format($total_logs); ?></p>
        </div>

        <div class="card">
            <h2>Delete All Logs</h2>
            <p style="color: #d63638;">⚠️ Warning: This will permanently delete ALL chat logs!</p>
            <form method="post" action="">
                <?php wp_nonce_field('purge_all_chatbot_logs'); ?>
                <p class="submit">
                    <input type="submit" name="purge_all_logs" 
                           class="button" 
                           style="background: #d63638; color: white; border-color: #d63638;"
                           value="Delete All Logs" 
                           onclick="return confirm('WARNING: This will delete ALL chat logs! Are you sure?');">
                </p>
            </form>
        </div>
    </div>
    <?php
}