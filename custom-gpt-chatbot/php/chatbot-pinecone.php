<?php
// chatbot-pinecone.php

class PineconeSearchException extends Exception {}

function query_pinecone_by_metadata($keyword, $county = null, $city = null, $top_k = 10) {
    try {
        // Validate required configuration
        if (!defined('PINECONE_API_KEY')) {
            throw new PineconeSearchException('Pinecone API key is not configured');
        }

        // 🔥 Enhanced Start Log
        error_log(sprintf(
            '[Chatbot][%s][User:%s] 🔍 Starting Pinecone query - Keyword: %s, County: %s, City: %s',
            '2025-04-27 22:30:08',
            'gravitybegone',
            $keyword,
            $county ?? 'none',
            $city ?? 'none'
        ));

        // Validate inputs
        if (empty($keyword)) {
            throw new PineconeSearchException('Search keyword cannot be empty');
        }

        if ($top_k < 1 || $top_k > 100) {
            error_log('⚠️ Invalid top_k value, defaulting to 10');
            $top_k = 10;
        }

        // Clean and normalize inputs
        $keyword = strtolower(trim($keyword));
        $county = $county ? strtolower(trim($county)) : null;
        $city = $city ? strtolower(trim($city)) : null;

        if (strlen($keyword) < 2) {
            throw new PineconeSearchException('Search keyword must be at least 2 characters long');
        }

        error_log('📊 Query parameters: ' . json_encode([
            'keyword' => $keyword,
            'county' => $county,
            'city' => $city,
            'top_k' => $top_k
        ]));

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

        error_log('🔍 Pinecone filter payload: ' . json_encode($filter));

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
                    error_log("⚠️ Retry {$retry_count + 1}/{$max_retries}: {$error_message}");
                    $retry_count++;
                    sleep(1);
                    continue;
                }
                throw new PineconeSearchException("Network error: {$error_message}");
            }

            switch ($response_code) {
                case 200:
                    break 2;
                case 401:
                    throw new PineconeSearchException('Invalid Pinecone API key');
                case 400:
                    throw new PineconeSearchException('Invalid query format');
                case 429:
                    if ($retry_count < $max_retries) {
                        error_log("⚠️ Rate limit hit, retry {$retry_count + 1}/{$max_retries}");
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
                        error_log("⚠️ Server error {$response_code}, retry {$retry_count + 1}/{$max_retries}");
                        $retry_count++;
                        sleep(1);
                        continue;
                    }
                    throw new PineconeSearchException("Pinecone server error: {$response_code}");
                default:
                    throw new PineconeSearchException("Unexpected response code: {$response_code}");
            }
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PineconeSearchException('Invalid JSON response from Pinecone');
        }

        if (!isset($data['matches'])) {
            throw new PineconeSearchException('Unexpected response format from Pinecone');
        }

        if (!empty($data['matches'])) {
            foreach ($data['matches'] as $match) {
                $company = $match['metadata']['company'] ?? 'Unknown';
                $score = $match['score'] ?? 0;
                error_log(sprintf('🎯 Match: %s (Score: %f)', $company, $score));
            }
        } else {
            error_log('ℹ️ No matches found for query');
        }

        return $data['matches'];

    } catch (PineconeSearchException $e) {
        error_log(sprintf(
            '[Chatbot][%s][User:%s] ❌ Pinecone search error: %s',
            '2025-04-27 22:30:08',
            'gravitybegone',
            $e->getMessage()
        ));
        return [
            'error' => true,
            'message' => $e->getMessage(),
            'matches' => []
        ];
    } catch (Exception $e) {
        error_log(sprintf(
            '[Chatbot][%s][User:%s] ❌ Unexpected error in Pinecone search: %s',
            '2025-04-27 22:30:08',
            'gravitybegone',
            $e->getMessage()
        ));
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

// Usage example:
/*
$results = query_pinecone_by_metadata('restaurant', 'example county', 'example city');
if (is_pinecone_error($results)) {
    // Handle error
    $error_message = $results['message'];
} else {
    // Process results
    foreach ($results as $match) {
        // Process each match
    }
}
*/
