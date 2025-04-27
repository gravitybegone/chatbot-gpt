<?php
// custom-gpt-chatbot-ajax.php

// Include all required files
require_once dirname(__FILE__) . '/chatbot-location.php';
require_once dirname(__FILE__) . '/chatbot-pinecone.php';
require_once dirname(__FILE__) . '/chatbot-embedding.php';  // Add this line

function custom_gpt_chatbot_handle_request() {
    error_log('🔧 [Chatbot] Handler started');

    // Verify nonce
    if (!check_ajax_referer('custom_gpt_chatbot_nonce', 'nonce', false)) {
        error_log('❌ Invalid nonce provided');
        wp_send_json_error([
            'reply' => 'Invalid security token. Please refresh the page.',
            'error' => true
        ]);
        return;
    }

    // Get the message from the AJAX request
    $message = sanitize_text_field($_POST['message'] ?? '');
    error_log('📩 [Chatbot] User query: ' . $message);

    try {
        // Extract location first
        $location = extract_location_from_message($message);
        error_log('📍 Location extracted: ' . json_encode($location));

        // Use the keyword from location extraction
        $industry = $location['keyword'];
        error_log('🏢 Industry/keyword: ' . $industry);

        // Query Pinecone
        $matches = query_pinecone_by_metadata(
            $industry,
            $location['county'],
            $location['city']
        );
        error_log('🔍 Found ' . count($matches) . ' matches');

        if (!empty($matches)) {
            // Format results as cards
            $response = format_pinecone_matches($matches);
            error_log('📝 Formatted ' . count($matches) . ' matches into cards');
            
            wp_send_json_success([
                'reply' => $response,
                'rawHtml' => true,
                'metadata' => [
                    'matchCount' => count($matches),
                    'keyword' => $industry,
                    'county' => $location['county'],
                    'city' => $location['city']
                ]
            ]);
        } else {
            error_log('⚠️ No matches found, generating fallback message');
            $fallback = "I couldn't find any results for '$industry' in " . 
                       ($location['city'] ? $location['city'] : ($location['county'] ? $location['county'] . ' county' : 'your area')) . 
                       ". Would you like to try:
                       - A different keyword
                       - A nearby city or county
                       - A broader search term";
            
            if (empty($location['county']) && empty($location['city'])) {
                error_log('🌍 No location provided, adding location prompt');
                $fallback .= "\n\nWhich city or county are you searching in? Choose below:";
            }
            
            $response = $fallback . '<!--RETRY-OPTIONS-->';
            error_log('💬 Generated fallback response with retry options');

            wp_send_json_success([
                'reply' => $response,
                'rawHtml' => false,
                'metadata' => [
                    'matchCount' => 0,
                    'keyword' => $industry,
                    'needsLocation' => empty($location['county']) && empty($location['city']),
                    'lastQuery' => $message
                ]
            ]);
        }

    } catch (Exception $e) {
        error_log('❌ Error in chatbot handler: ' . $e->getMessage());
        error_log('🔍 Stack trace: ' . $e->getTraceAsString());
        
        wp_send_json_error([
            'reply' => "I'm sorry, but I encountered an error. Please try:
                - Using different keywords
                - Checking the spelling
                - Using the suggestion buttons above",
            'error' => true,
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ]);
    }
}

// Hook the AJAX handler
add_action('wp_ajax_custom_gpt_chatbot_message', 'custom_gpt_chatbot_handle_request');
add_action('wp_ajax_nopriv_custom_gpt_chatbot_message', 'custom_gpt_chatbot_handle_request');