<?php

// custom-gpt-chatbot-ajax.php


// Include required files
require_once dirname(__FILE__) . '/chatbot-location.php';
require_once dirname(__FILE__) . '/chatbot-pinecone.php';
require_once dirname(__FILE__) . '/chatbot-format.php';

function custom_gpt_chatbot_handle_request() {
    chatbot_log('👉 Handler Function Called', [
        'post_data' => $_POST,
        'request_method' => $_SERVER['REQUEST_METHOD']
    ]);

    // Verify nonce first
    if (!check_ajax_referer('custom_gpt_chatbot_nonce', 'nonce', false)) {
        chatbot_log('❌ Invalid nonce in AJAX request');
        wp_send_json_error(['reply' => 'Security check failed']);
        return;
    }

    try {
        // Get and sanitize the message
        $message = sanitize_text_field($_POST['message'] ?? '');

        if (empty($message)) {
            chatbot_log('❌ Empty message received');
            throw new Exception('Message cannot be empty');
        }

        // Extract location first
        $location = extract_location_from_message($message);
        $industry = extract_industry_from_message($message, $location);

        chatbot_log('📍 Location and industry extracted', [
            'raw_message' => $message,
            'industry' => $industry,
            'county' => $location['county'] ?? 'none',
            'city' => $location['city'] ?? 'none'
        ]);

        if (empty($industry)) {
            chatbot_log('❌ No industry found in message');
            throw new Exception('Could not determine what you are looking for. Please try again with a business type or industry.');
        }

        // Query Pinecone with error handling
        $results = query_pinecone_by_metadata(
            $industry,
            $location['county'],
            $location['city']
        );

        // Check for Pinecone-specific errors
        if (is_pinecone_error($results)) {
            chatbot_log('📌 Pinecone error occurred', [
                'error' => $results['message'],
                'query' => $industry,
                'county' => $location['county'] ?? 'no-county',
                'city' => $location['city'] ?? 'no-city'
            ]);

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
            chatbot_log('✅ Search completed', [
                'matches_found' => count($results),
                'query' => $industry,
                'county' => $location['county'] ?? 'no-county',
                'city' => $location['city'] ?? 'no-city'
            ]);

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
            // No matches found, fallback
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

            if (empty($location['county']) && empty($location['city'])) {
                $fallback .= "\n\nWhich city or county are you searching in? Choose below:";
            }

            wp_send_json_success([
                'reply' => $fallback . '<!--RETRY-OPTIONS-->',
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
        chatbot_log('❌ System error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        wp_send_json_error([
            'reply' => "I'm sorry, but I encountered an error. Please try:
                - Using different keywords
                - Checking the spelling
                - Using the suggestion buttons above",
            'error' => true,
            'debug' => WP_DEBUG ? $e->getMessage() : null,
            'metadata' => [
                'errorType' => 'system'
            ]
        ]);
    } finally {
        $execution_time = microtime(true) - $start_time;
        chatbot_log('⏱️ Request completed', [
            'execution_time' => round($execution_time, 4)
        ]);
    }
}

// Hook the AJAX handler
add_action('wp_ajax_custom_gpt_chatbot_message', 'custom_gpt_chatbot_handle_request');
add_action('wp_ajax_nopriv_custom_gpt_chatbot_message', 'custom_gpt_chatbot_handle_request');
