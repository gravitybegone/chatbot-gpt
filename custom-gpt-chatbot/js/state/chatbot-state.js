// No export statements, everything global
window.ChatbotState = {
    GREETING: 'greeting',
    INDUSTRY_SELECTION: 'industry_selection',
    LOCATION_TYPE_SELECTION: 'location_type_selection',
    CITY_SELECTION: 'city_selection',
    COUNTY_SELECTION: 'county_selection',
    SHOWING_RESULTS: 'showing_results'
};

// Define ChatSession class globally
class ChatSession {
    constructor() {
        this.state = 'initial';
        this.selectedIndustry = null;
        this.selectedCounty = null;   // ✅ Replaced selectedLocation with selectedCounty
        this.selectedCity = null;     // ✅ Added selectedCity
        this.messages = [];
    }

    addMessage(message, isUser) {
        this.messages.push({
            text: message,
            isUser: isUser,
            timestamp: new Date()
        });
    }

    endSession() {
        this.state = 'ended';
        this.messages = [];
        this.selectedIndustry = null;
        this.selectedCounty = null;   // ✅ Reset selectedCounty on endSession
        this.selectedCity = null;     // ✅ Reset selectedCity on endSession
    }
}

// Make globally available
window.ChatSession = ChatSession;
