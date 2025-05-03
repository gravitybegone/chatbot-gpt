<?php
// custom-gpt-chatbot-ajax.php

require_once dirname(__FILE__) . '/chatbot-location.php';
require_once dirname(__FILE__) . '/chatbot-pinecone.php';
require_once dirname(__FILE__) . '/chatbot-format.php';
require_once dirname(__FILE__) . '/chatbot-fallback.php';

// Start session early
add_action('init', function() {
    if (!session_id()) {
        session_start();
    }
});

function custom_gpt_chatbot_handle_request() {
    $start_time = microtime(true);

    chatbot_log('👉 Handler Function Called', [
        'timestamp' => gmdate('Y-m-d H:i:s'),
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
        // Initialize session if missing
        if (!isset($_SESSION['chatbot_context'])) {
            $_SESSION['chatbot_context'] = [
                'industry' => null,
                'county' => null,
                'city' => null,
                'timestamp' => gmdate('Y-m-d H:i:s')
            ];
        }

        $message = sanitize_text_field($_POST['message'] ?? '');

        if (empty($message)) {
            throw new Exception('Message cannot be empty');
        }



// Update session based on POST data
if (!empty($_POST['industry'])) {
    $_SESSION['chatbot_context']['industry'] = sanitize_text_field($_POST['industry']);
}
if (!empty($_POST['county'])) {
    $_SESSION['chatbot_context']['county'] = sanitize_text_field($_POST['county']);
}
if (!empty($_POST['city'])) {
    $_SESSION['chatbot_context']['city'] = sanitize_text_field($_POST['city']);
}

        // 🧹 Commented out the old message parsing logic
        /*
        $lower_message = strtolower(trim($message));

        if (is_city($lower_message)) {
            $_SESSION['chatbot_context']['city'] = $lower_message;
        } elseif (is_county($lower_message)) {
            $_SESSION['chatbot_context']['county'] = $lower_message;
        } else {
            $_SESSION['chatbot_context']['industry'] = $lower_message;
        }
        */

        chatbot_log('🔄 Conversation Context Updated', [
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'context' => $_SESSION['chatbot_context'],
            'user' => 'gravitybegone'
        ]);

        // If no industry detected yet, try extracting from full message
        if (empty($_SESSION['chatbot_context']['industry'])) {
            $location = extract_location_from_message($message);
            $_SESSION['chatbot_context']['industry'] = extract_industry_from_message($message, $location);
            $_SESSION['chatbot_context']['county'] = $location['county'] ?? $_SESSION['chatbot_context']['county'];
            $_SESSION['chatbot_context']['city'] = $location['city'] ?? $_SESSION['chatbot_context']['city'];

            chatbot_log('📍 Location and industry extracted', [
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'context' => $_SESSION['chatbot_context']
            ]);

            if (empty($_SESSION['chatbot_context']['industry'])) {
                throw new Exception('Could not determine what you are looking for. Please try again with a business type or industry.');
            }
        }

        // Perform the search
        $query_results = query_pinecone_by_metadata(
            $_SESSION['chatbot_context']['industry'],
            $_SESSION['chatbot_context']['county'],
            $_SESSION['chatbot_context']['city']
        );

        if (is_pinecone_error($query_results)) {
            chatbot_log('📌 Pinecone error occurred', [
                'error' => $query_results['message'],
                'context' => $_SESSION['chatbot_context']
            ]);

            wp_send_json_error([
                'reply' => "I'm having trouble searching right now. Please try:\n- Refreshing the page\n- Using different keywords\n- Trying again in a few moments",
                'error' => true,
                'metadata' => [
                    'context' => $_SESSION['chatbot_context'],
                    'errorType' => 'pinecone'
                ]
            ]);
            return;
        }

        // Send results or fallback
        if (!empty($query_results['results'])) {
            chatbot_log('✅ Search completed', [
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'matches_found' => count($query_results['results']),
                'context' => $_SESSION['chatbot_context']
            ]);

            wp_send_json_success([
                'reply' => format_pinecone_matches($query_results['results']),
                'rawHtml' => true,
                'metadata' => [
                    'matchCount' => count($query_results['results']),
                    'context' => $_SESSION['chatbot_context']
                ]
            ]);
        } elseif ($query_results['level'] === 'county' && isset($query_results['delay'])) {
            wp_send_json_success([
                'reply' => $query_results['message'],
                'metadata' => [
                    'delay' => 500,
                    'followup' => true,
                    'context' => $_SESSION['chatbot_context']
                ]
            ]);
        } else {
            $fallback = generate_fallback_message(
                $_SESSION['chatbot_context']['industry'],
                $_SESSION['chatbot_context']
            );

            chatbot_log('🔄 Using fallback message', [
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'context' => $_SESSION['chatbot_context'],
                'user' => 'gravitybegone'
            ]);

            wp_send_json_success([
                'reply' => $fallback . '<!--RETRY-OPTIONS-->',
                'rawHtml' => false,
                'metadata' => [
                    'matchCount' => 0,
                    'context' => $_SESSION['chatbot_context'],
                    'lastQuery' => $message,
                    'timestamp' => gmdate('Y-m-d H:i:s')
                ]
            ]);
        }

    } catch (Exception $e) {
        chatbot_log('❌ System error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        wp_send_json_error([
            'reply' => "I'm sorry, but I encountered an error. Please try:\n- Using different keywords\n- Checking the spelling\n- Using the suggestion buttons above",
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
?>