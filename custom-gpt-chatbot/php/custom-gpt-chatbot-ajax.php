<?php
// custom-gpt-chatbot-ajax.php

// Include required files
require_once dirname(__FILE__) . '/chatbot-location.php';
require_once dirname(__FILE__) . '/chatbot-pinecone.php';

function custom_gpt_chatbot_handle_request() {
    try {
        // Get and sanitize the message from the AJAX request
        $message = sanitize_text_field($_POST['message'] ?? '');
        
        if (empty($message)) {
            throw new Exception('Message cannot be empty');
        }

        // Extract location first
        $location = extract_location_from_message($message);
        
        // Use the keyword from location extraction
        $industry = $location['keyword'];

        // Query Pinecone with error handling
        $results = query_pinecone_by_metadata(
            $industry,
            $location['county'],
            $location['city']
        );

        // Check for Pinecone-specific errors
        if (is_pinecone_error($results)) {
            error_log('📌 Pinecone search error: ' . $results['message']);
            
            // Send user-friendly error message
            wp_send_json_error([
                'reply' => "I'm having trouble searching right now. Please try:
                    - Refreshing the page
                    - Using different keywords
                    - Trying again in a few moments",
                'error' => true,
                'metadata' => [
                    'keyword' => $industry,
                    'county' => $location['county'],
                    'city' => $location['city'],
                    'errorType' => 'pinecone'
                ]
            ]);
            return;
        }

        // Process successful results
        if (!empty($results)) {
            // Format results as cards
            $response = format_pinecone_matches($results);
            
            wp_send_json_success([
                'reply' => $response,
                'rawHtml' => true,
                'metadata' => [
                    'matchCount' => count($results),
                    'keyword' => $industry,
                    'county' => $location['county'],
                    'city' => $location['city']
                ]
            ]);
        } else {
            // No matches found, generate fallback message
            $location_text = $location['city'] 
                ? $location['city'] 
                : ($location['county'] 
                    ? $location['county'] . ' county' 
                    : 'your area');
            
            $fallback = sprintf(
                "I couldn't find any results for '%s' in %s. Would you like to try:\n" .
                "- A different keyword\n" .
                "- A nearby city or county\n" .
                "- A broader search term",
                esc_html($industry),
                esc_html($location_text)
            );

            // Add location prompt if needed
            if (empty($location['county']) && empty($location['city'])) {
                $fallback .= "\n\nWhich city or county are you searching in? Choose below:";
            }

            $response = $fallback . '<!--RETRY-OPTIONS-->';

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
        error_log('❌ Chatbot error: ' . $e->getMessage() . ' at ' . '2025-04-27 22:20:13');
        
        wp_send_json_error([
            'reply' => "I'm sorry, but I encountered an error. Please try:
                - Using different keywords
                - Checking the spelling
                - Using the suggestion buttons above",
            'error' => true,
            'debug' => WP_DEBUG ? $e->getMessage() : null,
            'metadata' => [
                'errorType' => 'system',
                'timestamp' => '2025-04-27 22:20:13'
            ]
        ]);
    }
}

// Hook the AJAX handler
add_action('wp_ajax_custom_gpt_chatbot_message', 'custom_gpt_chatbot_handle_request');
add_action('wp_ajax_nopriv_custom_gpt_chatbot_message', 'custom_gpt_chatbot_handle_request');
