<?php
/*
Plugin Name: Custom GPT Chatbot
Description: A customizable chatbot for WordPress
Version: 2.0.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

class CustomGPTChatbot {
    private $plugin_path;
    private $plugin_url;
    private $debug_enabled;

    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        
        // Initialize debug logging
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
            $log_message = '[Chatbot] ' . $message;
            
            if (!empty($context)) {
                $log_message .= ' | Context: ' . json_encode($context);
            }
            
            error_log($log_message);
        }
    }

    public function enqueue_assets() {
        if ($this->debug_enabled) {
            $this->log_debug('📦 Starting asset enqueuing');
        }

        wp_enqueue_style(
            'chatbot-styles',
            $this->plugin_url . 'css/chatbot-styles.css',
            array(),
            '2.0.0'
        );

        wp_enqueue_script(
            'chatbot-config',
            $this->plugin_url . 'js/config/chatbot-config.js',
            array(),
            '2.0.0',
            true
        );

        wp_enqueue_script(
            'chatbot-state',
            $this->plugin_url . 'js/state/chatbot-state.js',
            array('chatbot-config'),
            '2.0.0',
            true
        );

        wp_enqueue_script(
            'chatbot-index',
            $this->plugin_url . 'js/index.js',
            array('jquery', 'chatbot-config', 'chatbot-state'),
            '2.0.0',
            true
        );

        wp_localize_script('chatbot-index', 'customGptChatbotAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_gpt_chatbot_nonce'),
            'debug' => $this->debug_enabled,
            'plugin_url' => $this->plugin_url
        ));

        if ($this->debug_enabled) {
            $this->log_debug('✅ Assets enqueued successfully');
        }
    }

    public function inject_chatbot_html() {
        $template_path = $this->plugin_path . 'templates/chatbot-template.html';
        
        if (file_exists($template_path)) {
            echo file_get_contents($template_path);
            if ($this->debug_enabled) {
                $this->log_debug('🎨 Template injected successfully');
            }
        } else {
            $this->log_debug('❌ Template file not found', [
                'template_path' => $template_path
            ]);
        }
    }
}

// Initialize the plugin and make it globally accessible
global $custom_gpt_chatbot;
$custom_gpt_chatbot = new CustomGPTChatbot();

// Add global logging function
if (!function_exists('chatbot_log')) {
    function chatbot_log($message, $context = []) {
        global $custom_gpt_chatbot;
        if ($custom_gpt_chatbot) {
            $custom_gpt_chatbot->log_debug($message, $context);
        }
    }
}
