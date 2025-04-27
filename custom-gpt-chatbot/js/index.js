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
        // Process user input and generate response based on current state
        let response;
        
        switch(this.session.state) {
            case ChatbotState.INDUSTRY_SELECTION:
                this.session.selectedIndustry = text;
                this.session.state = ChatbotState.LOCATION_TYPE_SELECTION;
                response = "Great! Now, would you like to search by city or county?";
                break;
            case ChatbotState.LOCATION_TYPE_SELECTION:
                if (text.toLowerCase().includes('city')) {
                    this.session.state = ChatbotState.CITY_SELECTION;
                    response = "Which city are you interested in?";
                } else if (text.toLowerCase().includes('county')) {
                    this.session.state = ChatbotState.COUNTY_SELECTION;
                    response = "Which county should I look in?";
                } else {
                    response = "Sorry, I didn't catch that. Please specify if you want to search by city or county.";
                }
                break;
            default:
                response = "I'll help you with your request about: " + text;
        }
        
        setTimeout(() => {
            this.displayBotMessage(response);
        }, 500);
    }

    displayUserMessage(message) {
        console.log('👤 User message:', message);
        try {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chatbot-msg user';
            messageDiv.textContent = message;
            this.chatBody.appendChild(messageDiv);
            this.scrollToBottom();
            if (this.session) {
                this.session.addMessage(message, true);
            }
            this.updateDebugBanner();
        } catch (error) {
            console.error('❌ Error displaying user message:', error);
        }
    }

    displayBotMessage(message) {
        console.log('🤖 Bot message:', message);
        try {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chatbot-msg bot';
            messageDiv.innerHTML = `<strong>Townie:</strong> ${message}`;
            this.chatBody.appendChild(messageDiv);
            this.scrollToBottom();
            if (this.session) {
                this.session.addMessage(message, false);
            }
            this.updateDebugBanner();
        } catch (error) {
            console.error('❌ Error displaying bot message:', error);
        }
    }

    // ... [existing addSuggestionButtons and handleSuggestionClick methods] ...

    endSession() {
        if (this.session) {
            this.session.endSession();
            this.chatBody.innerHTML = ''; // Clear chat
            this.displayBotMessage("Session ended. How can I help you today?");
            this.addSuggestionButtons();
        }
        this.updateDebugBanner();
    }

    scrollToBottom() {
        try {
            if (this.chatBody) {
                this.chatBody.scrollTop = this.chatBody.scrollHeight;
                console.log('✅ Scrolled to bottom');
            }
        } catch (error) {
            console.error('❌ Error scrolling to bottom:', error);
        }
    }

    updateDebugBanner() {
        if (this.isDebugMode && this.debugBanner && this.session) {
            this.debugBanner.textContent = `Current State: ${this.session.state} | Messages: ${this.session.messages.length}`;
            this.debugBanner.style.display = 'block';
        }
    }
}

// Make available globally
window.ChatbotMessageHandler = ChatbotMessageHandler;
