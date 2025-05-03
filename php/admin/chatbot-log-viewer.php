<?php

// php/admin/chatbot-log-viewer.php
add_action('admin_menu', function() {
  add_management_page('Chatbot Logs', 'Chatbot Logs', 'manage_options', 'chatbot-log-viewer', 'render_chatbot_log_viewer');
});

function render_chatbot_log_viewer() {
  $log_file = plugin_dir_path(__FILE__) . '../logs/chatbot.log';

  echo '<div class="wrap"><h1>Chatbot Logs</h1>';

  if (!file_exists($log_file)) {
    echo '<p><strong>No chatbot.log file found.</strong></p></div>';
    return;
  }

  $lines = array_reverse(file($log_file));
  echo '<textarea style="width:100%; height:600px; font-family:monospace;">';
  foreach ($lines as $line) {
    echo esc_html($line);
  }
  echo '</textarea></div>';
}