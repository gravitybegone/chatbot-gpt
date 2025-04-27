<?php
// chatbot-pinecone.php

function query_pinecone_by_metadata($keyword, $county = null, $city = null, $top_k = 10) {
  // Add to beginning of query_pinecone_by_metadata function
error_log('🔍 Starting Pinecone query at ' . '2025-04-26 17:49:29');
error_log('📊 Query parameters: ' . json_encode([
    'keyword' => $keyword,
    'county' => $county,
    'city' => $city,
    'top_k' => $top_k
]));
    $keyword = strtolower(trim($keyword));
    
    // Generate embedding for semantic search
    $embedding = generate_embedding($keyword);
    if (!$embedding) {
        error_log('⚠️ Failed to generate embedding, falling back to metadata-only search');
        $vector = array_fill(0, 1536, 0.0); // Fallback to zero vector
    } else {
        $vector = $embedding;
    }
    
    // Industry array search
    $industry_filter = [
        '$or' => [
            ['industry' => ['$in' => [$keyword]]],
            ['industry_synonyms' => ['$in' => [$keyword]]]
        ]
    ];

    // Location logic - Updated for array handling
    $location_filters = [];
    if ($county) {
        $location_filters[] = ['county' => ['$eq' => strtolower($county)]];
    }
    if ($city) {
        $location_filters[] = ['city' => ['$in' => [strtolower($city)]]]; // Changed to $in for array search
    }

    // Combine filters
    if (!empty($location_filters)) {
        $filter = [
            '$and' => array_merge([$industry_filter], $location_filters)
        ];
    } else {
        $filter = $industry_filter;
    }

    error_log('🔍 Pinecone filter payload: ' . json_encode($filter));

    // Main query with both vector and metadata
    $response = wp_remote_post('https://company-search-pb9v2w7.svc.aped-4627-b74a.pinecone.io/query', [
        'headers' => [
            'Api-Key' => PINECONE_API_KEY,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'vector' => $vector,
            'topK' => $top_k,
            'includeMetadata' => true,
            'filter' => $filter,
            'includeValues' => false,
        ])
    ]);

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    error_log('📊 Pinecone Response Code: ' . $response_code);
    error_log('📄 Pinecone Response Body: ' . $response_body);

    if (is_wp_error($response)) {
        error_log('❌ Pinecone metadata query failed: ' . $response->get_error_message());
        return [];
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Log match scores for debugging
    if (!empty($data['matches'])) {
        foreach ($data['matches'] as $match) {
            error_log(sprintf(
                '🎯 Match: %s (Score: %f)', 
                $match['metadata']['company'] ?? 'Unknown',
                $match['score'] ?? 0
            ));
        }
    }

    return $data['matches'] ?? [];
}