<?php
function enqueue_chatbot_assets() {
    $plugin_url = plugin_dir_url(__FILE__);
    $version = '2.0.0';
    
    // Define file paths
    $files = [
        'css' => 'css/custom-gpt-chatbot.css',
        'config' => 'js/config/chatbot-config.js',
        'state' => 'js/state/chatbot-state.js',
        'index' => 'js/index.js',
        'main' => 'js/custom-gpt-chatbot.js',
        'init' => 'js/chatbot-ui-init.js'
    ];

    // Enqueue CSS
    wp_enqueue_style(
        'custom-gpt-chatbot',
        $plugin_url . $files['css'],
        array(),
        $version
    );

    // Config script
    wp_enqueue_script(
        'chatbot-config',
        $plugin_url . $files['config'],
        array(),
        $version,
        true
    );

    // State script
    wp_enqueue_script(
        'chatbot-state',
        $plugin_url . $files['state'],
        array('chatbot-config'),
        $version,
        true
    );

    // Main index.js file
    wp_enqueue_script(
        'chatbot-index',
        $plugin_url . $files['index'],
        array('jquery', 'chatbot-config', 'chatbot-state'),
        $version,
        true
    );

    // Additional functionality
    wp_enqueue_script(
        'custom-gpt-chatbot',
        $plugin_url . $files['main'],
        array('jquery', 'chatbot-index'),
        $version,
        true
    );

    // Initialize UI last
    wp_enqueue_script(
        'chatbot-ui-init',
        $plugin_url . $files['init'],
        array('custom-gpt-chatbot'),
        $version,
        true
    );

    // Localize script data
    wp_localize_script('chatbot-index', 'customGptChatbotAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('custom_gpt_chatbot_nonce'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG,
        'plugin_url' => $plugin_url
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_chatbot_assets');