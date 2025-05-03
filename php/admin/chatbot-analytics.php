<?php
// php/admin/chatbot-analytics.php

if (!defined('ABSPATH')) exit;

function add_chatbot_analytics_menu() {
    add_menu_page(
        'Chatbot Analytics',
        'Chatbot Analytics',
        'manage_options',
        'chatbot-analytics',
        'render_chatbot_analytics_page',
        'dashicons-chart-bar',
        30
    );
}
add_action('admin_menu', 'add_chatbot_analytics_menu');

function render_chatbot_analytics_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chatbot_interactions';
    
    // ✅ Only count meaningful interactions (those with industry)
    $total_interactions = $wpdb->get_var("
        SELECT COUNT(DISTINCT session_id) 
        FROM $table_name 
        WHERE industry IS NOT NULL
    ");

    // ✅ Calculate % of successful searches
    $success_rate = $wpdb->get_var("
        SELECT AVG(found_results > 0) 
        FROM $table_name 
        WHERE industry IS NOT NULL
    ");

    // ✅ Top industries by usage
    $top_industries = $wpdb->get_results("
        SELECT 
            industry, 
            COUNT(DISTINCT session_id) as count,
            AVG(found_results > 0) as success_rate
        FROM $table_name 
        WHERE industry IS NOT NULL
        GROUP BY industry 
        ORDER BY count DESC 
        LIMIT 10
    ");

    // ✅ Latest interaction per session (final search only)
    $recent_interactions = $wpdb->get_results("
        SELECT 
            t1.timestamp_utc,
            t1.industry,
            t1.county,
            t1.city,
            t1.found_results,
            t1.search_level
        FROM $table_name t1
        INNER JOIN (
            SELECT session_id, MAX(timestamp_utc) as max_time
            FROM $table_name
            WHERE industry IS NOT NULL
            GROUP BY session_id
        ) t2 ON t1.session_id = t2.session_id AND t1.timestamp_utc = t2.max_time
        ORDER BY t1.timestamp_utc DESC
        LIMIT 10
    ");

    // 🔍 Load the dashboard view
    include(plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/admin/analytics-dashboard.php');
}