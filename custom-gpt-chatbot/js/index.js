class ChatbotMessageHandler {
    constructor() {
        console.group('🤖 Initializing ChatbotMessageHandler');
        
        // Get debug mode from localized data
        this.isDebugMode = window.customGptChatbotAjax?.debug || false;
        console.log('Debug mode:', this.isDebugMode);
        
        // Debug check for required elements
        const requiredElements = {
            'chatbot-toggle': document.getElementById('chatbot-toggle'),
            'custom-gpt-chatbot-container': document.getElementById('custom-gpt-chatbot-container'),
            'gpt-chatbot-body': document.getElementById('gpt-chatbot-body'),
            'gpt-chatbot-input': document.getElementById('gpt-chatbot-input'),
            'gpt-chatbot-send': document.getElementById('gpt-chatbot-send'),
            'chatbot-debug-banner': document.getElementById('chatbot-debug-banner')
        };

        // Check if all elements exist
        const missingElements = Object.entries(requiredElements)
            .filter(([name, element]) => !element)
            .map(([name]) => name);

        if (missingElements.length > 0) {
            console.error('❌ Missing required elements:', missingElements);
            console.groupEnd();
            return;
        }

        try {
            this.session = new ChatSession();
            this.chatBody = requiredElements['gpt-chatbot-body'];
            this.input = requiredElements['gpt-chatbot-input'];
            this.sendButton = requiredElements['gpt-chatbot-send'];
            this.debugBanner = requiredElements['chatbot-debug-banner'];
            this.container = requiredElements['custom-gpt-chatbot-container'];
            
            // Add chatbox toggle functionality
            const chatbotToggle = requiredElements['chatbot-toggle'];
            
            if (chatbotToggle && this.container) {
                chatbotToggle.addEventListener('click', () => {
                    this.container.classList.toggle('visible');
                    this.updateDebugBanner();
                });
            }

            // Setup input handlers
            this.setupInputHandlers();

            // Remove existing suggestions if any
            const existingSuggestions = this.chatBody.querySelector('.chatbot-suggestions');
            if (existingSuggestions) {
                existingSuggestions.remove();
            }

            // Start conversation with greeting
            const greetings = [
                "🌟 Hey there! I'm Townie, your local legend finder...",
                "👋 Yo! I'm Townie — your small-town sidekick...",
                "🏡 Sup! I'm Townie, and I know the neighborhood...",
                "😎 Welcome to MySmallTowns — I'm Townie...",
                "🎯 Hi hi! I'm Townie...",
                "🤖 Hey hey! I'm Townie 👋..."
            ];
            
            const lastIndex = parseInt(localStorage.getItem('last_greeting_index')) || -1;
            let newIndex;
            do {
                newIndex = Math.floor(Math.random() * greetings.length);
            } while (newIndex === lastIndex && greetings.length > 1);
            localStorage.setItem('last_greeting_index', newIndex);
            
            this.displayBotMessage(greetings[newIndex]);

            // Add suggestions after greeting with delay
            setTimeout(() => {
                this.addSuggestionButtons();
            }, 500);

            this.updateDebugBanner();
            
            console.log('✅ ChatbotMessageHandler initialized successfully');
        } catch (error) {
            console.error('❌ Error in initialization:', error);
        }
        console.groupEnd();
    }

    setupInputHandlers() {
        // Handle send button clicks
        if (this.sendButton) {
            this.sendButton.addEventListener('click', () => {
                this.handleUserInput();
            });
        }

        // Handle enter key in input
        if (this.input) {
            this.input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.handleUserInput();
                }
            });
        }
    }

    handleUserInput() {
        const text = this.input.value.trim();
        if (text) {
            this.displayUserMessage(text);
            this.input.value = ''; // Clear input
            this.processUserInput(text);
        }
    }

    processUserInput(text) {
        // Process user input and generate response
        let response = "I'll help you with your request about: " + text;
        
        setTimeout(() => {
            this.displayBotMessage(response);
        }, 500);
    }

    addSuggestionButtons() {
        const suggestionsHtml = `
            <div class="chatbot-suggestions">
                <div class="suggestion-label">Quick Suggestions:</div>
                <div class="chatbot-suggestion-buttons">
                    <button class="suggestion-btn">Find a Restaurant</button>
                    <button class="suggestion-btn">Local Services</button>
                    <button class="suggestion-btn">Business Directory</button>
                    <button class="suggestion-btn">Community Events</button>
                </div>
            </div>
        `;

        const suggestionsDiv = document.createElement('div');
        suggestionsDiv.innerHTML = suggestionsHtml;
        this.chatBody.appendChild(suggestionsDiv.firstElementChild);

        // Add click handlers to suggestion buttons
        const buttons = this.chatBody.querySelectorAll('.suggestion-btn');
        buttons.forEach(button => {
            button.addEventListener('click', () => {
                const text = button.textContent;
                this.handleSuggestionClick(text);
            });
        });
    }

    handleSuggestionClick(text) {
        // Display user's selection
        this.displayUserMessage(text);

        // Process different suggestions
        let response;
        switch(text.toLowerCase()) {
            case 'find a restaurant':
                response = "I'd be happy to help you find a restaurant! What type of cuisine are you interested in?";
                break;
            case 'local services':
                response = "What kind of local service are you looking for? (e.g., plumber, electrician, mechanic)";
                break;
            case 'business directory':
                response = "I can help you browse our local business directory. What category interests you?";
                break;
            case 'community events':
                response = "Let me check what's happening in the community. Are you interested in this week's events?";
                break;
            default:
                response = "I'll help you find information about " + text + ". Could you provide more details?";
        }

        // Add slight delay before bot response
        setTimeout(() => {
            this.displayBotMessage(response);
        }, 500);
    }

    // ... [rest of your existing methods remain the same] ...
}

// Make available globally
window.ChatbotMessageHandler = ChatbotMessageHandler;