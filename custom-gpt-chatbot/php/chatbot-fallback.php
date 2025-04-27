<?php
// chatbot-fallback.php

function generate_fallback_message($query, $location = []) {
    // Clean and format the search terms
    $query = trim(strip_tags($query));
    $county = isset($location['county']) ? trim(strip_tags($location['county'])) : '';
    $city = isset($location['city']) ? trim(strip_tags($location['city'])) : '';
    
    // Determine location text for the message
    $location_text = '';
    if ($city) {
        $location_text = $city;
    } elseif ($county) {
        $location_text = "$county county";
    } else {
        $location_text = "your area";
    }

    // Build the base message
    $message = sprintf(
        "I couldn't find any matches for '%s' in %s.",
        esc_html($query),
        esc_html($location_text)
    );

    // Add suggestion bullets
    $suggestions = [
        "Try a different keyword or business type",
        "Check the spelling of your search terms",
        "Search in a nearby city or county",
        "Use a broader search term (e.g., 'restaurant' instead of 'pizzeria')"
    ];

    $message .= "\n\nHere are some suggestions:";
    foreach ($suggestions as $suggestion) {
        $message .= "\n• " . $suggestion;
    }

    // Add location prompt if no location was specified
    if (empty($county) && empty($city)) {
        $message .= "\n\nTo help narrow down your search, please specify which city or county you're looking in. Choose from the options below:";
    }

    error_log(sprintf(
        '📝 Generated fallback message for query: "%s" in location: "%s" at %s',
        $query,
        $location_text,
        date('Y-m-d H:i:s')
    ));

    return $message;
}
