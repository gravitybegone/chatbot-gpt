const chatbotFlow = {
    greeting: {
        message: "👋 Hi! I can help you find local businesses. What are you looking for?",
        suggestions: [
            "Marketing",
            "Web Design",
            "SEO",
            "Landscaping",
            // other common industries
        ]
    },
    
    afterIndustry: {
        message: "Great! How would you like to search?",
        buttons: [
            {
                text: "🏙️ Search by City",
                action: "show_cities"
            },
            {
                text: "🗺️ Search by County",
                action: "show_counties"
            }
        ]
    },
    
    cityFlow: {
        message: "Which city are you interested in?",
        suggestions: [
            // Dynamically populated based on most common cities
            "Jacksonville",
            "St. Augustine",
            "Orange Park",
            // etc.
        ]
    },
    
    countyFlow: {
        message: "Which county are you interested in?",
        buttons: [
            {
                text: "Clay County",
                action: "show_clay_cities"
            },
            {
                text: "Duval County",
                action: "show_duval_cities"
            },
            {
                text: "St. Johns County",
                action: "show_stjohns_cities"
            },
            {
                text: "Nassau County",
                action: "show_nassau_cities"
            },
            {
                text: "Flagler County",
                action: "show_flagler_cities"
            }
        ]
    }
};

const fallbackResponses = {
    noResults: {
        message: "I couldn't find any {industry} businesses in {location}. Would you like to:",
        buttons: [
            {
                text: "Try a different city",
                action: "show_cities"
            },
            {
                text: "Search entire county",
                action: "search_county"
            },
            {
                text: "Try different industry",
                action: "restart"
            }
        ]
    },
    
    typoSuggestion: {
        message: "Did you mean:",
        // Will show closest matches based on city/county names
    }
};