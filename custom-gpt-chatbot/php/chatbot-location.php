<?php
// chatbot-location.php

// Add this to chatbot-location.php

function extract_location_from_message($message) {
    $message = strtolower($message);
    
    // Initialize return array
    $location = [
        'city' => null,
        'county' => null,
        'keyword' => null
    ];
    
    // Extract "in" location pattern (e.g., "seo in clay")
    if (preg_match('/(.+?)\s+in\s+(.+)/', $message, $matches)) {
        $location['keyword'] = trim($matches[1]);
        $possible_location = trim($matches[2]);
        
        // Check if the location is a county
        if (strpos($possible_location, 'county') !== false || strpos($possible_location, ' co') !== false) {
            $location['county'] = str_replace(['county', ' co'], '', $possible_location);
        } else {
            // If not explicitly a county, treat as city
            $location['city'] = $possible_location;
        }
    } else {
        // If no location pattern found, treat entire message as keyword
        $location['keyword'] = $message;
    }
    
    // Clean up all values
    $location['keyword'] = trim($location['keyword']);
    $location['city'] = $location['city'] ? trim($location['city']) : null;
    $location['county'] = $location['county'] ? trim($location['county']) : null;
    
    return $location;
}

function extract_industry_from_message($message, $location = null) {
    // If location data is provided, remove it from the message
    if ($location) {
        if ($location['city']) {
            $message = str_replace('in ' . $location['city'], '', $message);
        }
        if ($location['county']) {
            $message = str_replace('in ' . $location['county'], '', $message);
            $message = str_replace('in ' . $location['county'] . ' county', '', $message);
        }
    }

    // Clean up the message
    $message = strtolower(trim($message));
    
    // Remove common words and prepositions
    $message = preg_replace('/\b(looking\s+for|need|want|find|search|for|a|an|the|in)\b/i', '', $message);
    
    // Clean up extra spaces
    $message = preg_replace('/\s+/', ' ', $message);
    
    // Return the cleaned industry keyword
    return trim($message);
}
