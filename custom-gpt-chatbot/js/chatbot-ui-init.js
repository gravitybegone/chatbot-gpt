document.addEventListener('DOMContentLoaded', () => {
    console.group('🔄 Initializing chatbot UI');
    console.log('DOM loaded, starting initialization...');

    try {
        // Initialize chatbot
        window.activeChatbot = new ChatbotMessageHandler();
        console.log('✅ Chatbot UI initialized successfully');
    } catch (error) {
        console.error('❌ Error initializing chatbot UI:', error);
    }
    console.groupEnd();
});
