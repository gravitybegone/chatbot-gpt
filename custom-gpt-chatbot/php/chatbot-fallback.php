<?php
// chatbot-fallback.php

function generate_fallback_message($query, $location = []) {
  $county = $location['county'] ?? 'your area';
  $cache_key = 'fallback_' . md5($query . $county);

  // 🔁 Return cached response if exists
  $cached = get_transient($cache_key);
  if ($cached) {
    error_log("♻️ Using cached fallback for {$query} in {$county}");
    return $cached;
  }

  $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
    'headers' => [
      'Authorization' => 'Bearer ' . OPENAI_API_KEY,
      'Content-Type'  => 'application/json',
    ],
    'body' => json_encode([
      'model' => 'gpt-4',
      'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant in a small-town business directory.'],
        ['role' => 'user', 'content' => "We don’t have any matches for this: {$query} in {$county}. Write a kind and helpful fallback response that encourages the user to try a nearby county or different keyword."]
      ]
    ])
  ]);

  if (is_wp_error($response)) {
    error_log('❌ GPT fallback failed: ' . $response->get_error_message());
    return "Sorry, we couldn't find anything in {$county}. Please try another area or different keyword.";
  }

  $data = json_decode(wp_remote_retrieve_body($response), true);
  $message = trim($data['choices'][0]['message']['content'] ?? "Sorry, we couldn't find anything in {$county}.");

  // ✅ Cache for 2 hours
  set_transient($cache_key, $message, 2 * HOUR_IN_SECONDS);

  return $message;
}