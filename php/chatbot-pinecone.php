<?php
// chatbot-pinecone.php

class PineconeSearchException extends Exception {}

function query_pinecone_by_metadata($industry, $county = null, $city = null) {
    chatbot_log('🔍 Starting search', [
        'industry' => $industry,
        'county' => $county,
        'city' => $city,
        'user' => 'gravitybegone'
    ]);

    if ($city) {
        $city_results = perform_pinecone_query($industry, $county, $city);

        if (!empty($city_results)) {
            chatbot_log('✅ Found city-specific results', [
                'industry' => $industry,
                'county' => $county,
                'city' => $city,
                'count' => count($city_results),
                'level' => 'city',
                'user' => 'gravitybegone'
            ]);
            return [
                'results' => $city_results,
                'level' => 'city'
            ];
        }

        chatbot_log('ℹ️ No city results, falling back to county', [
            'industry' => $industry,
            'county' => $county,
            'city' => $city,
            'user' => 'gravitybegone'
        ]);

        $county_results = perform_pinecone_query($industry, $county);

        if (!empty($county_results)) {
            chatbot_log('✅ Found county-level results', [
                'industry' => $industry,
                'county' => $county,
                'city' => $city,
                'count' => count($county_results),
                'level' => 'county',
                'user' => 'gravitybegone'
            ]);
            return [
                'results' => $county_results,
                'level' => 'county',
                'message' => "There are no $industry listings specifically in $city, but here are the $industry listings available in $county County:",
                'delay' => 500
            ];
        }
    } else {
        $county_results = perform_pinecone_query($industry, $county);
        if (!empty($county_results)) {
            chatbot_log('✅ Found county-only results', [
                'industry' => $industry,
                'county' => $county,
                'count' => count($county_results),
                'level' => 'county',
                'user' => 'gravitybegone'
            ]);
            return [
                'results' => $county_results,
                'level' => 'county'
            ];
        }
    }

    chatbot_log('❌ No results found', [
        'industry' => $industry,
        'county' => $county,
        'city' => $city,
        'count' => 0,
        'level' => 'none',
        'user' => 'gravitybegone'
    ]);

    return [
        'results' => [],
        'level' => 'none',
        'message' => "I apologize, but there are currently no $industry listings in $county County. We're continually adding new listings and this will be added in the future."
    ];
}

function perform_pinecone_query($industry, $county, $city = null) {
    chatbot_log('🔍 Starting Pinecone query', [
        'industry' => $industry,
        'county' => $county,
        'city' => $city,
        'user' => 'gravitybegone'
    ]);

    $filter = [
        '$and' => [
            [
                '$or' => [
                    ['industry' => ['$in' => [strtolower($industry)]]],
                    ['industry_synonyms' => ['$in' => [strtolower($industry)]]]
                ]
            ]
        ]
    ];

    if ($county) {
        $filter['$and'][] = ['county' => ['$in' => [strtolower($county)]]];
    }

    if ($city) {
        $filter['$and'][] = ['city' => strtolower($city)];
    }

    chatbot_log('📦 Constructed Pinecone filter', [
        'filter' => $filter,
        'user' => 'gravitybegone'
    ]);

    $request_body = [
        'vector' => array_fill(0, 1536, 0.0),
        'topK' => 10,
        'includeMetadata' => true,
        'filter' => $filter,
        'includeValues' => false
    ];

    $response = wp_remote_post(
        'https://company-search-pb9v2w7.svc.aped-4627-b74a.pinecone.io/query',
        [
            'headers' => [
                'Api-Key' => PINECONE_API_KEY,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_body),
            'timeout' => 15
        ]
    );

    if (is_wp_error($response)) {
        chatbot_log('❌ Pinecone network error', [
            'error' => $response->get_error_message()
        ]);
        return [];
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        chatbot_log('❌ Unexpected Pinecone API response', [
            'code' => $response_code
        ]);
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['matches'])) {
        chatbot_log('❌ Invalid JSON from Pinecone', [
            'error' => json_last_error_msg()
        ]);
        return [];
    }

    return $data['matches'];
}

function chatbot_log($message, $data = []) {
    $interaction_data = [
        'session_id' => uniqid('chat_', true),
        'user' => $data['user'] ?? 'unknown',
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
        'conversation_flow' => [
            'selected_industry' => $data['industry'] ?? null,
            'selected_county' => $data['county'] ?? null,
            'selected_city' => $data['city'] ?? null,
            'found_results' => $data['count'] ?? null,
            'search_level' => $data['level'] ?? null
        ],
        'timestamp_utc' => gmdate('Y-m-d H:i:s')
    ];

    // Store to DB
    store_interaction($interaction_data);

    // Also write to PHP error log
    error_log("[Chatbot] $message | " . json_encode($interaction_data));
}

function store_interaction($data) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'chatbot_interactions';

    $wpdb->insert(
        $table_name,
        [
            'session_id' => $data['session_id'],
            'user_login' => $data['user'],
            'user_ip' => $data['user_ip'],
            'timestamp_utc' => $data['timestamp_utc'],
            'industry' => $data['conversation_flow']['selected_industry'],
            'county' => $data['conversation_flow']['selected_county'],
            'city' => $data['conversation_flow']['selected_city'],
            'found_results' => $data['conversation_flow']['found_results'],
            'search_level' => $data['conversation_flow']['search_level'],
            'interaction_data' => json_encode($data)
        ]
    );
}

function format_and_send_response($query_results) {
    if ($query_results['level'] === 'county' && isset($query_results['delay'])) {
        wp_send_json_success([
            'reply' => $query_results['message'],
            'metadata' => [
                'delay' => 500,
                'followup' => true
            ]
        ]);
    } else {
        wp_send_json_success([
            'reply' => format_pinecone_matches($query_results['results']),
            'metadata' => [
                'level' => $query_results['level'],
                'message' => $query_results['message'] ?? null
            ]
        ]);
    }
}

function is_pinecone_error($response) {
    return isset($response['error']) && $response['error'] === true;
}
?>