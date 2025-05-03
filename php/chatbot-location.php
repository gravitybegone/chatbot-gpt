<?php
// chatbot-location.php

// 🧠 Define your known counties (lowercase, simple matching)
function get_known_counties() {
    return [
        'clay county',
        'duval county',
        'st. johns county',
        'nassau county',
        'flagler county'
    ];
}

// 🧠 Define your known cities (lowercase, simple matching)
function get_known_cities() {
    return [
        // Clay County
        'orange park',
        'middleburg',
        'fleming island',
        'green cove springs',
        'penney farms',
        'keystone heights',
        'oakleaf',
        'doctors inlet',
        'lake asbury',
        'belmore',

        // Duval County
        'jacksonville',
        'jacksonville beach',
        'neptune beach',
        'atlantic beach',
        'baldwin',
        'mayport',

        // St. Johns County
        'st. augustine',
        'st. augustine beach',
        'ponte vedra',
        'ponte vedra beach',
        'nocatee',
        'fruit cove',
        'switzerland',
        'hastings',
        'world golf village',
        'palencia',
        'butler beach',
        'crescent beach',

        // Nassau County
        'fernandina beach',
        'yulee',
        'callahan',
        'hilliard',
        'bryceville',
        'amelia island',

        // Flagler County
        'palm coast',
        'bunnell',
        'flagler beach',
        'beverly beach',
        'marineland',
        'hammock'
    ];
}

// 🧠 Extract location parts from a message
function extract_location_from_message($message) {
    $message = strtolower($message);
    
    $location = [
        'city' => null,
        'county' => null,
        'keyword' => null
    ];
    
    if (preg_match('/(.+?)\s+in\s+(.+)/', $message, $matches)) {
        $location['keyword'] = trim($matches[1]);
        $possible_location = trim($matches[2]);
        
        if (strpos($possible_location, 'county') !== false || strpos($possible_location, ' co') !== false) {
            $location['county'] = str_replace(['county', ' co'], '', $possible_location);
        } else {
            $location['city'] = $possible_location;
        }
    } else {
        $location['keyword'] = $message;
    }
    
    $location['keyword'] = trim($location['keyword']);
    $location['city'] = $location['city'] ? trim($location['city']) : null;
    $location['county'] = $location['county'] ? trim($location['county']) : null;
    
    return $location;
}

// 🔎 Extract just the industry from a message
function extract_industry_from_message($message, $location = null) {
    if ($location) {
        if ($location['city']) {
            $message = str_replace('in ' . $location['city'], '', $message);
        }
        if ($location['county']) {
            $message = str_replace('in ' . $location['county'], '', $message);
            $message = str_replace('in ' . $location['county'] . ' county', '', $message);
        }
    }

    $message = strtolower(trim($message));
    
    $message = preg_replace('/\b(looking\s+for|need|want|find|search|for|a|an|the|in)\b/i', '', $message);
    
    $message = preg_replace('/\s+/', ' ', $message);
    
    return trim($message);
}
?>