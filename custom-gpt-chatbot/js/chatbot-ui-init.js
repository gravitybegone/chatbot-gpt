// js/chatbot-ui-init.js

document.addEventListener('DOMContentLoaded', () => {
    console.group('🔄 Initializing chatbot UI');
    console.log('DOM loaded, starting initialization...');

    // Check if chatbot is already initialized
    if (window.activeChatbot) {
        console.log('⚠️ Chatbot already initialized, skipping...');
        console.groupEnd();
        return;
    }

    try {
        // Initialize chatbot
        window.activeChatbot = new ChatbotMessageHandler();
        
        // Add event listeners for UI elements
        const sendButton = document.getElementById('gpt-chatbot-send');
        const inputField = document.getElementById('gpt-chatbot-input');
        const endSessionButton = document.getElementById('gpt-chatbot-end-session');
        const confirmPopupWrapper = document.getElementById('chatbot-confirm-popup-wrapper');
        const confirmEndButton = document.getElementById('confirm-end');
        const cancelEndButton = document.getElementById('cancel-end');

        // Handle suggestion button clicks
        document.querySelectorAll('.suggestion-btn').forEach(button => {
            button.addEventListener('click', () => {
                const text = button.textContent;
                inputField.value = text;
                window.activeChatbot.handleUserInput();
            });
        });

        // Handle send button clicks
        if (sendButton) {
            sendButton.addEventListener('click', () => {
                window.activeChatbot.handleUserInput();
            });
        }

        // Handle enter key in input
        if (inputField) {
            inputField.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    window.activeChatbot.handleUserInput();
                }
            });
        }

        // Handle end session
        if (endSessionButton && confirmPopupWrapper) {
            endSessionButton.addEventListener('click', () => {
                confirmPopupWrapper.style.display = 'flex';
            });
        }

        // Handle confirm/cancel end session
        if (confirmEndButton && confirmPopupWrapper) {
            confirmEndButton.addEventListener('click', () => {
                window.activeChatbot.endSession();
                confirmPopupWrapper.style.display = 'none';
            });
        }

        if (cancelEndButton && confirmPopupWrapper) {
            cancelEndButton.addEventListener('click', () => {
                confirmPopupWrapper.style.display = 'none';
            });
        }

        console.log('✅ Chatbot UI initialized successfully');
    } catch (error) {
        console.error('❌ Error initializing chatbot UI:', error);
    }
    console.groupEnd();
});