<?php

//php/admin/pinecone-debug-admin.php
// Adds a new admin page under Tools
add_action('admin_menu', function() {
  add_management_page('Pinecone Debug', 'Pinecone Debug', 'manage_options', 'pinecone-debug', 'render_pinecone_debug_page');
});

function render_pinecone_debug_page() {
  echo '<div class="wrap"><h1>Pinecone Debugger</h1>';

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = query_dummy_pinecone_vector();
    if (is_wp_error($response)) {
      echo '<div class="notice notice-error"><p>Error: ' . esc_html($response->get_error_message()) . '</p></div>';
    } else {
      $data = json_decode(wp_remote_retrieve_body($response), true);
      if (!empty($data['matches'])) {
        echo '<table class="widefat"><thead><tr>';
        echo '<th>ID</th><th>Score</th><th>Company</th><th>Summary</th><th>Industry</th><th>Industry Synonyms</th><th>City</th><th>County</th>';
        echo '</tr></thead><tbody>';

        foreach ($data['matches'] as $match) {
          $meta = $match['metadata'] ?? [];
          echo '<tr>';
          echo '<td>' . esc_html($match['id']) . '</td>';
          echo '<td>' . esc_html($match['score']) . '</td>';
          echo '<td>' . esc_html($meta['company'] ?? '—') . '</td>';
          echo '<td>' . esc_html($meta['summary'] ?? '—') . '</td>';
          echo '<td>' . esc_html($meta['industry'] ?? '—') . '</td>';
          echo '<td>' . esc_html(is_array($meta['industry_synonyms']) ? implode(", ", $meta['industry_synonyms']) : ($meta['industry_synonyms'] ?? '—')) . '</td>';
          echo '<td>' . esc_html($meta['city'] ?? '—') . '</td>';
          echo '<td><code>' . esc_html(print_r($meta['county'], true)) . '</code></td>';
          echo '</tr>';
        }

        echo '</tbody></table>';
      } else {
        echo '<p>No matches returned from Pinecone.</p>';
      }
    }
  }

  echo '<form method="post">';
  submit_button('Run Pinecone Query');
  echo '</form></div>';
}

function query_dummy_pinecone_vector() {
  $vector = array_fill(0, 1536, 0.01); // Dummy vector with correct size
  return wp_remote_post('https://company-search-pb9v2w7.svc.aped-4627-b74a.pinecone.io/query', [
    'headers' => [
      'Content-Type' => 'application/json',
      'Api-Key' => PINECONE_API_KEY,
    ],
    'body' => json_encode([
      'vector' => $vector,
      'topK' => 20, // Increased from 5 to 20
      'includeMetadata' => true
    ])
  ]);
}
