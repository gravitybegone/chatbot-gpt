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

    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'inject_chatbot_html'));
    }

    public function enqueue_assets() {
        // Enqueue your scripts and styles here

        // Config script
        wp_enqueue_script(
            'chatbot-config',
            $this->plugin_url . 'js/config/chatbot-config.js',
            array(),
            '2.0.0',
            true
        );

        // State script
        wp_enqueue_script(
            'chatbot-state',
            $this->plugin_url . 'js/state/chatbot-state.js',
            array('chatbot-config'),
            '2.0.0',
            true
        );

        // Main chatbot file
        wp_enqueue_script(
            'chatbot-index',
            $this->plugin_url . 'js/index.js',
            array('jquery', 'chatbot-config', 'chatbot-state'),
            '2.0.0',
            true
        );

        // Localize script
        wp_localize_script('chatbot-index', 'customGptChatbotAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_gpt_chatbot_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'plugin_url' => $this->plugin_url
        ));
    }

    public function inject_chatbot_html() {
        $template_path = $this->plugin_path . 'templates/chatbot-template.html';
        
        if (file_exists($template_path)) {
            echo file_get_contents($template_path);
        } else {
            error_log('Chatbot template file not found: ' . $template_path);
        }
    }
}

// Initialize the plugin
new CustomGPTChatbot();
