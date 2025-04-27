<?php

// chatbot-embedding.php

function generate_embedding($query) {
    error_log('📊 [Embedding] Starting generation for query: ' . $query);

    $response = wp_remote_post('https://api.openai.com/v1/embeddings', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . OPENAI_API_KEY,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'input' => $query,
            'model' => 'text-embedding-ada-002'
        ))
    ));

    if (is_wp_error($response)) {
        error_log('❌ [Embedding] Failed to embed query: ' . $response->get_error_message());
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Log the result
    if ($data['data'][0]['embedding'] ?? null) {
        error_log('✅ [Embedding] Successfully generated embedding');
    } else {
        error_log('⚠️ [Embedding] Generation failed or returned null');
    }

    return $data['data'][0]['embedding'] ?? null;
}