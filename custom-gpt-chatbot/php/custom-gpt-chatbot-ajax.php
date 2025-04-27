<?php
// custom-gpt-chatbot-ajax.php

// Include required files
require_once dirname(__FILE__) . '/chatbot-location.php';
require_once dirname(__FILE__) . '/chatbot-pinecone.php';

function custom_gpt_chatbot_handle_request() {
    // Get the message from the AJAX request
    $message = sanitize_text_field($_POST['message'] ?? '');

    try {
        // Extract location first
        $location = extract_location_from_message($message);

        // Use the keyword from location extraction
        $industry = $location['keyword'];

        // Query Pinecone
        $matches = query_pinecone_by_metadata(
            $industry,
            $location['county'],
            $location['city']
        );

        if (!empty($matches)) {
            // Format results as cards
            $response = format_pinecone_matches($matches);

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
            // No matches found, generate fallback message
            $fallback = "I couldn't find any results for '$industry' in " . 
                        ($location['city'] ? $location['city'] : ($location['county'] ? $location['county'] . ' county' : 'your area')) . 
                        ". Would you like to try:
                        - A different keyword
                        - A nearby city or county
                        - A broader search term";

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
