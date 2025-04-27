// Remove any export statements
window.ChatbotState = {
    GREETING: 'greeting',
    INDUSTRY_SELECTION: 'industry_selection',
    LOCATION_TYPE_SELECTION: 'location_type_selection',
    CITY_SELECTION: 'city_selection',
    COUNTY_SELECTION: 'county_selection',
    SHOWING_RESULTS: 'showing_results'
};

// Define ChatSession class globally
window.ChatSession = class ChatSession {
    constructor() {
        this.state = ChatbotState.GREETING;
        this.selectedIndustry = null;
        this.selectedLocationType = null;
        this.selectedLocation = null;
        this.messages = [];
        this.lastQuery = null;
    }

    addMessage(message, isUser) {
        this.messages.push({
            content: message,
            isUser: isUser,
            timestamp: new Date()
        });
    }

    reset() {
        this.state = ChatbotState.GREETING;
        this.selectedIndustry = null;
        this.selectedLocationType = null;
        this.selectedLocation = null;
        this.messages = [];
        this.lastQuery = null;
    }
};