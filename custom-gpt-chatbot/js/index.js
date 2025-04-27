class ChatbotMessageHandler {
    constructor() {
        console.group('🤖 Initializing ChatbotMessageHandler');
        
        this.isDebugMode = window.customGptChatbotAjax?.debug || false;
        console.log('Debug mode:', this.isDebugMode);

        const requiredElements = {
            'chatbot-toggle': document.getElementById('chatbot-toggle'),
            'custom-gpt-chatbot-container': document.getElementById('custom-gpt-chatbot-container'),
            'gpt-chatbot-body': document.getElementById('gpt-chatbot-body'),
            'gpt-chatbot-input': document.getElementById('gpt-chatbot-input'),
            'gpt-chatbot-send': document.getElementById('gpt-chatbot-send'),
            'chatbot-debug-banner': document.getElementById('chatbot-debug-banner')
        };

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

            const chatbotToggle = requiredElements['chatbot-toggle'];

            if (chatbotToggle && this.container) {
                chatbotToggle.addEventListener('click', () => {
                    this.container.classList.toggle('visible');
                    this.updateDebugBanner();
                });
            }

            this.setupInputHandlers();

            const existingSuggestions = this.chatBody.querySelector('.chatbot-suggestions');
            if (existingSuggestions) {
                existingSuggestions.remove();
            }

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
        if (this.sendButton) {
            this.sendButton.addEventListener('click', () => {
                this.handleUserInput();
            });
        }

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
            this.input.value = '';
            this.processUserInput(text);
        }
    }

    processUserInput(text) {
        let response;

        if (!this.session.selectedIndustry) {
            this.session.selectedIndustry = text;
            const countyOptions = ChatbotConfig.counties.map(county => county.name).join(', ');
            response = `Great! You're looking for ${text}. Which county should I look in? (${countyOptions})`;
        } 
        else if (!this.session.selectedCounty) {
            const selectedCounty = ChatbotConfig.counties.find(county => 
                county.name.toLowerCase() === text.toLowerCase() ||
                county.id.toLowerCase() === text.toLowerCase()
            );

            if (selectedCounty) {
                this.session.selectedCounty = selectedCounty.id;
                const cities = ChatbotConfig.cities[selectedCounty.id] || [];
                response = `Which city in ${selectedCounty.name} are you interested in? (${cities.join(', ')})`;
            } else {
                response = `I couldn't find that county. Please choose from: ${ChatbotConfig.counties.map(c => c.name).join(', ')}`;
            }
        } 
        else if (!this.session.selectedCity) {
            const availableCities = ChatbotConfig.cities[this.session.selectedCounty] || [];
            if (availableCities.map(city => city.toLowerCase()).includes(text.toLowerCase())) {
                this.session.selectedCity = text;
                response = `Great! I'll search for ${this.session.selectedIndustry} in ${text}.`;
                // Here you would trigger the Pinecone search
            } else {
                response = `I couldn't find that city. Please choose from: ${availableCities.join(', ')}`;
            }
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

    addSuggestionButtons() {
        console.log('🎯 Adding suggestion buttons...');
        const suggestionsHtml = `
            <div class="chatbot-suggestions">
                <div class="suggestion-label">Quick Suggestions:</div>
                <div class="chatbot-suggestion-buttons">
                    ${ChatbotConfig.industries.map(industry => 
                        `<button class="suggestion-btn">${industry}</button>`
                    ).join('')}
                </div>
            </div>
        `;

        try {
            const suggestionsDiv = document.createElement('div');
            suggestionsDiv.innerHTML = suggestionsHtml;

            const existingSuggestions = this.chatBody.querySelectorAll('.chatbot-suggestions');
            existingSuggestions.forEach(el => el.remove());

            const suggestionElement = suggestionsDiv.firstElementChild;
            if (!suggestionElement) {
                console.error('❌ Failed to create suggestion element');
                return;
            }

            this.chatBody.appendChild(suggestionElement);
            console.log('✅ Suggestion buttons added successfully');

            const buttons = this.chatBody.querySelectorAll('.suggestion-btn');
            console.log(`📍 Found ${buttons.length} suggestion buttons`);

            buttons.forEach(button => {
                button.addEventListener('click', () => {
                    const text = button.textContent;
                    console.log('🔘 Suggestion button clicked:', text);
                    this.handleSuggestionClick(text);
                });
            });
        } catch (error) {
            console.error('❌ Error adding suggestion buttons:', error);
        }
    }

    handleSuggestionClick(text) {
        this.displayUserMessage(text);
        this.session.selectedIndustry = text;
        
        const countyOptions = ChatbotConfig.counties.map(county => county.name).join(', ');
        const response = `Great! You're looking for ${text}. Which county should I look in? (${countyOptions})`;

        setTimeout(() => {
            this.displayBotMessage(response);
        }, 500);
    }

    endSession() {
        if (this.session) {
            this.session.endSession();
            this.chatBody.innerHTML = '';
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

// 🔥 Consolidated Initialization
document.addEventListener('DOMContentLoaded', () => {
    console.group('🔄 Initializing chatbot UI');
    console.log('DOM loaded, starting initialization...');

    try {
        window.activeChatbot = new ChatbotMessageHandler();
        console.log('✅ Chatbot UI initialized successfully');
    } catch (error) {
        console.error('❌ Error initializing chatbot UI:', error);
    }

    console.groupEnd();
});
