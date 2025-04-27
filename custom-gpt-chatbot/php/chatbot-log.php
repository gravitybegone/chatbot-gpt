<?php

// chatbot-log.php
function chatbot_log($message) {
  $log_file = plugin_dir_path(__FILE__) . '../logs/chatbot.log';
  $timestamp = date('Y-m-d H:i:s');
  $entry = "[{$timestamp}] {$message}\n";
  error_log($entry, 3, $log_file);
}