<?php
// php/chatbot-log-admin.php

add_action('admin_menu', function() {
  add_management_page('Chatbot Logs', 'Chatbot Logs', 'manage_options', 'chatbot-logs', 'render_chatbot_logs_page');
});

function render_chatbot_logs_page() {
  echo '<div class="wrap"><h1>Chatbot Logs</h1>';

  $log_file = plugin_dir_path(__FILE__) . '../logs/chatbot.log';
  if (!file_exists($log_file)) {
    echo '<p>No logs found.</p></div>';
    return;
  }

  $lines = array_reverse(file($log_file)); // Show most recent first
  if (empty($lines)) {
    echo '<p>Log file is empty.</p></div>';
    return;
  }

  echo '<table class="widefat"><thead><tr><th>Timestamp</th><th>Event</th></tr></thead><tbody>';

  foreach ($lines as $line) {
    if (preg_match('/^\[(.*?)\]\s+(.*)$/', $line, $matches)) {
      $timestamp = esc_html($matches[1]);
      $message = esc_html($matches[2]);
      echo "<tr><td>{$timestamp}</td><td><code>{$message}</code></td></tr>";
    }
  }

  echo '</tbody></table></div>';
}
