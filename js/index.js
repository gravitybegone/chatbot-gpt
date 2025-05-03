class ChatbotMessageHandler {
    constructor() {
        console.group('🤖 Initializing ChatbotMessageHandler');

        this.isDebugMode = window.customGptChatbotAjax?.debug || false;
        console.log('Debug mode:', this.isDebugMode);

        this.lastShownIndustries = new Set(); // 🆕 Track previously shown industries

        const requiredElements = {
            'chatbot-toggle': document.getElementById('chatbot-toggle'),
            'custom-gpt-chatbot-container': document.getElementById('custom-gpt-chatbot-container'),
            'gpt-chatbot-body': document.getElementById('gpt-chatbot-body'),
            'gpt-chatbot-input': document.getElementById('gpt-chatbot-input'),
            'gpt-chatbot-send': document.getElementById('gpt-chatbot-send'),
            'chatbot-debug-banner': document.getElementById('chatbot-debug-banner')
        };

        const missingElements = Object.entries(requiredElements)
            .filter(([_, el]) => !el)
            .map(([name]) => name);

        if (missingElements.length > 0) {
            console.error('❌ Missing required elements:', missingElements);
            console.groupEnd();
            return;
        }

        try {
            this.session = new window.ChatSession();
            this.chatBody = requiredElements['gpt-chatbot-body'];
            this.input = requiredElements['gpt-chatbot-input'];
            this.sendButton = requiredElements['gpt-chatbot-send'];
            this.debugBanner = requiredElements['chatbot-debug-banner'];
            this.container = requiredElements['custom-gpt-chatbot-container'];

            requiredElements['chatbot-toggle'].addEventListener('click', () => {
                this.container.classList.toggle('visible');
                this.updateDebugBanner();
            });

            this.setupInputHandlers();
            this.loadGreeting();

            console.log('✅ ChatbotMessageHandler initialized successfully');
        } catch (error) {
            console.error('❌ Error in initialization:', error);
        }
        console.groupEnd();
    }

    setupInputHandlers() {
        this.sendButton.addEventListener('click', () => this.handleUserInput());
        this.input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.handleUserInput();
            }
        });
    }

    loadGreeting() {
        const greetings = [
            "🌟 Hey there! I'm Townie, your local legend finder...",
            "👋 Yo! I'm Townie — your small-town sidekick...",
            "🏡 Sup! I'm Townie, and I know the neighborhood...",
            "😎 Welcome to MySmallTowns — I'm Townie...",
            "🎯 Hi hi! I'm Townie...",
            "🤖 Hey hey! I'm Townie 👋..."
        ];

        let lastIndex = parseInt(localStorage.getItem('last_greeting_index')) || -1;
        let newIndex;
        do {
            newIndex = Math.floor(Math.random() * greetings.length);
        } while (newIndex === lastIndex && greetings.length > 1);
        localStorage.setItem('last_greeting_index', newIndex);

        this.displayBotMessage(greetings[newIndex]);
        setTimeout(() => this.addSuggestionButtons(), 500);
    }

    handleUserInput(textInput) {
        const text = textInput || this.input.value.trim();
        if (text) {
            this.displayUserMessage(text);
            if (!textInput) this.input.value = '';
            this.processUserInput(text);
        }
    }

    processUserInput(text) {
        let response;

        if (!this.session.selectedIndustry) {
            this.session.selectedIndustry = text;
            console.log('🔄 Industry selected:', { industry: text });

            const countyOptions = ChatbotConfig.counties.map(c => c.name);
            response = this.createButtonResponse(`Great! You're looking for ${text}.`, countyOptions);
        } else if (!this.session.selectedCounty) {
            const selectedCounty = ChatbotConfig.counties.find(c =>
                c.name.toLowerCase() === text.toLowerCase() ||
                c.id.toLowerCase() === text.toLowerCase()
            );
            if (selectedCounty) {
                this.session.selectedCounty = selectedCounty.id;
                const cities = ChatbotConfig.cities[selectedCounty.id] || [];
                response = this.createButtonResponse(`Which city in ${selectedCounty.name} are you interested in?`, cities);
            } else {
                const countyOptions = ChatbotConfig.counties.map(c => c.name);
                response = this.createButtonResponse(`I couldn't find that county. Please choose:`, countyOptions);
            }
        } else if (!this.session.selectedCity) {
            const availableCities = ChatbotConfig.cities[this.session.selectedCounty] || [];
            if (availableCities.map(c => c.toLowerCase()).includes(text.toLowerCase())) {
                this.session.selectedCity = text;
                response = `Great! I'll search for ${this.session.selectedIndustry} in ${text}.`;
                this.sendAjaxRequest(text);
            } else {
                response = this.createButtonResponse(`I couldn't find that city. Please choose:`, availableCities);
            }
        }

        setTimeout(() => {
            this.displayBotMessage(response);
            this.attachSuggestionHandlers();
        }, 500);
    }

    sendAjaxRequest(message) {
        console.group('🌐 AJAX Request');
        console.log('Config:', customGptChatbotAjax);
        console.log('Message:', message);
        console.log('Session State:', {
            industry: this.session.selectedIndustry,
            county: this.session.selectedCounty,
            city: this.session.selectedCity
        });

        if (!customGptChatbotAjax?.ajax_url) {
            console.error('❌ AJAX URL missing');
            console.groupEnd();
            return;
        }

        jQuery.ajax({
            url: customGptChatbotAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'custom_gpt_chatbot_message',
                message: message,
                nonce: customGptChatbotAjax.nonce,
                industry: this.session.selectedIndustry,
                county: this.session.selectedCounty,
                city: this.session.selectedCity
            },
            success: (response) => {
                console.log('📥 Response:', response);
                if (response.success) {
                    this.displayBotMessage(response.data.reply);
                } else {
                    this.displayBotMessage(response.data.reply || 'Sorry, something went wrong.');
                }
            },
            error: (xhr, status, error) => {
                console.error('❌ AJAX Error:', error, status, xhr.responseText);
                this.displayBotMessage('Sorry, I encountered an error.');
            },
            complete: () => console.groupEnd()
        });
    }

    displayUserMessage(message) {
        const div = document.createElement('div');
        div.className = 'chatbot-msg user';
        div.textContent = message;
        this.chatBody.appendChild(div);
        this.scrollToBottom();
        this.session.addMessage(message, true);
        this.updateDebugBanner();
    }

    displayBotMessage(message) {
        const div = document.createElement('div');
        div.className = 'chatbot-msg bot';
        div.innerHTML = `<strong>Townie:</strong> ${message}`;
        this.chatBody.appendChild(div);
        this.scrollToBottom();
        this.session.addMessage(message, false);
        this.updateDebugBanner();
    }

    // 🆕 Method to get non-repeating industries
    getRandomIndustries(count = 6) {
        let available = ChatbotConfig.industries.filter(
            industry => !this.lastShownIndustries.has(industry)
        );

        if (available.length < count) {
            this.lastShownIndustries.clear();
            available = ChatbotConfig.industries;
        }

        const selected = [...available]
            .sort(() => Math.random() - 0.5)
            .slice(0, count);

        this.lastShownIndustries = new Set(selected);
        return selected;
    }

    // ✅ Updated to use getRandomIndustries
    addSuggestionButtons() {
        const selectedIndustries = this.getRandomIndustries(6);

        const suggestions = selectedIndustries.map(industry =>
            `<button class="suggestion-btn">${industry}</button>`
        ).join('');

        const html = `<div class="chatbot-suggestions">
            <div class="suggestion-label">Quick Suggestions:</div>
            <div class="chatbot-suggestion-buttons">${suggestions}</div>
        </div>`;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;

        this.chatBody.appendChild(wrapper.firstElementChild);
        this.attachSuggestionHandlers();
    }

    attachSuggestionHandlers() {
        this.chatBody.querySelectorAll('.suggestion-btn').forEach(button => {
            button.addEventListener('click', () => {
                const text = button.textContent;
                this.handleUserInput(text);
            });
        });
    }

    createButtonResponse(message, options) {
        const html = `
            <div class="chatbot-button-response">
                <div class="bot-message">${message}</div>
                <div class="chatbot-buttons">
                    ${options.map(option => 
                        `<button class="suggestion-btn">${option}</button>`
                    ).join('')}
                </div>
            </div>
        `;
        return html;
    }

    updateDebugBanner() {
        if (this.isDebugMode && this.debugBanner) {
            const industry = this.session.selectedIndustry || '(awaiting)';
            const county = (() => {
                const selected = this.session.selectedCounty;
                if (!selected) return '(awaiting)';
                const countyObj = ChatbotConfig.counties.find(c => c.id === selected);
                return countyObj ? countyObj.name : selected;
            })();
            const city = this.session.selectedCity || '(awaiting)';
            const messages = this.session.messages.length;

            this.debugBanner.textContent = `Current: Industry=${industry} | County=${county} | City=${city} | Messages=${messages}`;
            this.debugBanner.style.display = 'block';
        }
    }

    scrollToBottom() {
        this.chatBody.scrollTop = this.chatBody.scrollHeight;
    }
}

// Initialize globally
window.ChatbotMessageHandler = ChatbotMessageHandler;
document.addEventListener('DOMContentLoaded', () => {
    window.activeChatbot = new ChatbotMessageHandler();
});