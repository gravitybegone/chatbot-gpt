<?php
/*
Plugin Name: Custom GPT Chatbot
Description: A customizable chatbot for WordPress
Version: 2.0.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

// 🧱 Create chatbot_interactions table
function create_chatbot_interactions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chatbot_interactions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        chat_start_time datetime NOT NULL,
        last_update_time datetime NOT NULL,
        user_login varchar(60),
        user_ip varchar(45),
        industry varchar(255),
        county varchar(255),
        city varchar(255),
        found_results int DEFAULT 0,
        search_level varchar(50),
        interaction_data text,
        PRIMARY KEY (id),
        KEY last_update_time (last_update_time),
        KEY industry (industry)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'create_chatbot_interactions_table');

// 🧠 Main plugin class
class CustomGPTChatbot {
    private $plugin_path;
    private $plugin_url;
    private $debug_enabled;

    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->debug_enabled = defined('WP_DEBUG') && WP_DEBUG;

        $this->init_debug();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'inject_chatbot_html'));
    }

    private function init_debug() {
        if ($this->debug_enabled) {
            $this->log_debug('🚀 Plugin initialized', [
                'plugin_path' => $this->plugin_path,
                'plugin_url' => $this->plugin_url
            ]);
        }
    }

    public function log_debug($message, $context = []) {
        if ($this->debug_enabled) {
            error_log('[Chatbot] ' . $message . (!empty($context) ? ' | ' . json_encode($context) : ''));
        }
    }

    public function enqueue_assets() {
        if ($this->debug_enabled) {
            $this->log_debug('📦 Enqueuing assets');
        }

        wp_enqueue_style('chatbot-styles', $this->plugin_url . 'css/chatbot-styles.css', [], '2.0.0');

        wp_enqueue_script('chatbot-config', $this->plugin_url . 'js/config/chatbot-config.js', [], '2.0.0', true);
        wp_enqueue_script('chatbot-state', $this->plugin_url . 'js/state/chatbot-state.js', ['chatbot-config'], '2.0.0', true);
        wp_enqueue_script('chatbot-index', $this->plugin_url . 'js/index.js', ['jquery', 'chatbot-config', 'chatbot-state'], '2.0.0', true);

        wp_localize_script('chatbot-index', 'customGptChatbotAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_gpt_chatbot_nonce'),
            'debug' => $this->debug_enabled,
            'plugin_url' => $this->plugin_url
        ]);

        if ($this->debug_enabled) {
            $this->log_debug('✅ Assets enqueued');
        }
    }

    public function inject_chatbot_html() {
        $template_path = $this->plugin_path . 'templates/chatbot-template.html';

        if (file_exists($template_path)) {
            echo file_get_contents($template_path);
            if ($this->debug_enabled) {
                $this->log_debug('🎨 Template injected');
            }
        } else {
            $this->log_debug('❌ Template not found', ['template_path' => $template_path]);
        }
    }
}

// 🌍 Initialize plugin globally
global $custom_gpt_chatbot;
$custom_gpt_chatbot = new CustomGPTChatbot();

// 🔁 Load AJAX handler
require_once plugin_dir_path(__FILE__) . 'php/custom-gpt-chatbot-ajax.php';

// 🧮 Admin features
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'php/admin/chatbot-analytics.php';
    require_once plugin_dir_path(__FILE__) . 'php/admin/chatbot-log-management.php';
}

// 🔎 Logging final interaction data based on chat_id
if (!function_exists('chatbot_log')) {
    function chatbot_log($message, $data = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatbot_interactions';
        $now = gmdate('Y-m-d H:i:s');

        $final_messages = [
            '❌ No results found',
            '✅ Found city-specific results',
            '✅ Found county-level results',
            '✅ Found county-only results'
        ];
        if (!in_array($message, $final_messages, true)) {
            return;
        }

        if (!isset($_SESSION)) {
            session_start();
        }

        // If no chat_id in session, insert new row
        if (empty($_SESSION['chat_id'])) {
            $wpdb->insert($table_name, [
                'chat_start_time' => $now,
                'last_update_time' => $now,
                'user_login' => 'gravitybegone',
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            $_SESSION['chat_id'] = $wpdb->insert_id;
        }

        // Update the existing row with interaction data
        $wpdb->update($table_name, [
            'last_update_time' => $now,
            'industry' => $data['industry'] ?? null,
            'county' => $data['county'] ?? null,
            'city' => $data['city'] ?? null,
            'found_results' => $data['count'] ?? 0,
            'search_level' => $data['level'] ?? null,
            'interaction_data' => json_encode([
                'final_status' => $message,
                'search_successful' => ($data['count'] ?? 0) > 0,
                'timestamp' => $now
            ])
        ], ['id' => $_SESSION['chat_id']]);
    }
}
?>