<?php
// chatbot-pinecone.php

class PineconeSearchException extends Exception {}

function query_pinecone_by_metadata($keyword, $county = null, $city = null, $top_k = 10) {
    try {
        // Validate required configuration
        if (!defined('PINECONE_API_KEY')) {
            chatbot_log('❌ Pinecone API key not configured', [
                'error' => 'PINECONE_API_KEY constant not defined'
            ]);
            throw new PineconeSearchException('Pinecone API key is not configured');
        } else {
            chatbot_log('✅ Pinecone API key found', [
                'key_length' => strlen(PINECONE_API_KEY)
            ]);
        }

        // Log start of query
        chatbot_log('🔍 Starting Pinecone query', [
            'keyword' => $keyword,
            'county' => $county ?? 'none',
            'city' => $city ?? 'none',
            'top_k' => $top_k
        ]);

        // Validate inputs
        if (empty($keyword)) {
            throw new PineconeSearchException('Search keyword cannot be empty');
        }

        if ($top_k < 1 || $top_k > 100) {
            chatbot_log('⚠️ Invalid top_k value, defaulting to 10');
            $top_k = 10;
        }

        // Clean and normalize inputs
        $keyword = strtolower(trim($keyword));
        $county = $county ? strtolower(trim($county)) : null;
        $city = $city ? strtolower(trim($city)) : null;

        if (strlen($keyword) < 2) {
            throw new PineconeSearchException('Search keyword must be at least 2 characters long');
        }

        chatbot_log('📊 Query parameters prepared', [
            'keyword' => $keyword,
            'county' => $county,
            'city' => $city,
            'top_k' => $top_k
        ]);

        // Create zero vector for metadata-only search
        $vector = array_fill(0, 1536, 0.0);

        // Build industry filter
        $industry_filter = [
            '$or' => [
                ['industry' => ['$in' => [$keyword]]],
                ['industry_synonyms' => ['$in' => [$keyword]]]
            ]
        ];

        $filter = $industry_filter;
        if ($county || $city) {
            $location_filters = [];

            if ($county) {
                if (strlen($county) < 2) {
                    throw new PineconeSearchException('County name must be at least 2 characters long');
                }
                $location_filters[] = ['county' => ['$eq' => $county]];
            }

            if ($city) {
                if (strlen($city) < 2) {
                    throw new PineconeSearchException('City name must be at least 2 characters long');
                }
                $location_filters[] = ['city' => ['$in' => [$city]]];
            }

            $filter = [
                '$and' => array_merge([$industry_filter], $location_filters)
            ];
        }

        chatbot_log('🔍 Pinecone filter constructed', [
            'filter' => $filter
        ]);

        // Define the request body
        $request_body = [
            'vector' => $vector,
            'topK' => $top_k,
            'includeMetadata' => true,
            'filter' => $filter,
            'includeValues' => false,
        ];

        // Make the Pinecone API request with retry logic
        $max_retries = 2;
        $retry_count = 0;
        $response = null;

        chatbot_log('🌐 Initiating Pinecone API request', [
            'attempt' => 1,
            'max_retries' => $max_retries,
            'endpoint' => 'company-search-pb9v2w7.svc.aped-4627-b74a.pinecone.io/query'
        ]);

        while ($retry_count <= $max_retries) {
            $response = wp_remote_post(
                'https://company-search-pb9v2w7.svc.aped-4627-b74a.pinecone.io/query',
                [
                    'headers' => [
                        'Api-Key' => PINECONE_API_KEY,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($request_body),
                    'timeout' => 15,
                ]
            );

            $response_code = wp_remote_retrieve_response_code($response);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($retry_count < $max_retries) {
                    chatbot_log('🔄 API request failed, retrying', [
                        'attempt' => $retry_count + 2,
                        'error' => $error_message,
                        'max_retries' => $max_retries
                    ]);
                    $retry_count++;
                    sleep(1);
                    continue;
                }
                throw new PineconeSearchException("Network error: {$error_message}");
            }

            switch ($response_code) {
                case 200:
                    chatbot_log('✅ Pinecone API request successful', [
                        'status_code' => 200,
                        'attempt' => $retry_count + 1
                    ]);
                    break 2;
                case 401:
                    chatbot_log('🚫 API Authentication failed', [
                        'status_code' => 401,
                        'attempt' => $retry_count + 1
                    ]);
                    throw new PineconeSearchException('Invalid Pinecone API key');
                case 400:
                    chatbot_log('❌ Invalid API request format', [
                        'status_code' => 400,
                        'attempt' => $retry_count + 1,
                        'request_body' => json_encode($request_body)
                    ]);
                    throw new PineconeSearchException('Invalid query format');
                case 429:
                    if ($retry_count < $max_retries) {
                        chatbot_log('⏳ Rate limit reached, retrying', [
                            'status_code' => 429,
                            'attempt' => $retry_count + 2,
                            'max_retries' => $max_retries
                        ]);
                        $retry_count++;
                        sleep(2);
                        continue;
                    }
                    throw new PineconeSearchException('Rate limit exceeded');
                case 500:
                case 502:
                case 503:
                case 504:
                    if ($retry_count < $max_retries) {
                        chatbot_log('⚠️ Pinecone server error, retrying', [
                            'status_code' => $response_code,
                            'attempt' => $retry_count + 2,
                            'max_retries' => $max_retries
                        ]);
                        $retry_count++;
                        sleep(1);
                        continue;
                    }
                    throw new PineconeSearchException("Pinecone server error: {$response_code}");
                default:
                    chatbot_log('❓ Unexpected API response', [
                        'status_code' => $response_code,
                        'attempt' => $retry_count + 1
                    ]);
                    throw new PineconeSearchException("Unexpected response code: {$response_code}");
            }
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            chatbot_log('🚨 Invalid JSON response', [
                'json_error' => json_last_error_msg()
            ]);
            throw new PineconeSearchException('Invalid JSON response from Pinecone');
        }

        if (!isset($data['matches'])) {
            chatbot_log('🚨 Unexpected response format', [
                'response_keys' => array_keys($data)
            ]);
            throw new PineconeSearchException('Unexpected response format from Pinecone');
        }

        if (!empty($data['matches'])) {
            foreach ($data['matches'] as $match) {
                chatbot_log('🎯 Match found', [
                    'company' => $match['metadata']['company'] ?? 'Unknown',
                    'score' => $match['score'] ?? 0
                ]);
            }
        } else {
            chatbot_log('ℹ️ No matches found for query');
        }

        return $data['matches'];

    } catch (PineconeSearchException $e) {
        chatbot_log('❌ Pinecone search error', [
            'error' => $e->getMessage(),
            'type' => 'PineconeSearchException'
        ]);
        return [
            'error' => true,
            'message' => $e->getMessage(),
            'matches' => []
        ];
    } catch (Exception $e) {
        chatbot_log('❌ Unexpected error in Pinecone search', [
            'error' => $e->getMessage(),
            'type' => get_class($e)
        ]);
        return [
            'error' => true,
            'message' => 'An unexpected error occurred',
            'matches' => []
        ];
    }
}

// Helper function to check if the response indicates an error
function is_pinecone_error($response) {
    return isset($response['error']) && $response['error'] === true;
}
