// js/custom-gpt-chatbot.js
console.log('🤖 Enhanced Chatbot JS loaded');
let chatOpened = false;
let chatState = JSON.parse(localStorage.getItem('gpt_chat_history')) || [];
const CHAT_HISTORY_KEY = 'gpt_chat_history';
let allBotResults = [];
let visibleCount = 3;

// Add new state management for flow
const ChatbotState = {
    GREETING: 'greeting',
    INDUSTRY_SELECTION: 'industry_selection',
    LOCATION_TYPE_SELECTION: 'location_type_selection',
    CITY_SELECTION: 'city_selection',
    COUNTY_SELECTION: 'county_selection',
    SHOWING_RESULTS: 'showing_results'
};

// Configuration object
const ChatbotConfig = {
    industries: [
        'Marketing',
        'Web Design',
        'SEO',
        'Landscaping',
        // Add more industries...
    ],
    counties: [
        { id: 'clay', name: 'Clay County' },
        { id: 'duval', name: 'Duval County' },
        { id: 'st. johns', name: 'St. Johns County' },
        { id: 'nassau', name: 'Nassau County' },
        { id: 'flagler', name: 'Flagler County' }
    ]
};

class ChatSession {
    constructor() {
        this.messages = [];
        this.state = ChatbotState.GREETING;
        this.selectedIndustry = null;
        this.selectedLocationType = null;
        this.selectedLocation = null;
    }

    addMessage(message, isUser = false) {
        this.messages.push({
            content: message,
            timestamp: new Date(),
            isUser: isUser
        });
    }

    reset() {
        this.messages = [];
        this.state = ChatbotState.GREETING;
        this.selectedIndustry = null;
        this.selectedLocationType = null;
        this.selectedLocation = null;
    }
}

// Export for use in other files
window.ChatSession = ChatSession;


function updateDebugBanner() {
  const banner = document.getElementById('chatbot-debug-banner');
  if (!banner) return;

  const state = {
    awaitingLocation: chatState.awaitingLocation || false,
    lastQuery: chatState.lastQuery || '',
    chatOpened,
    historyCount: chatState.length
  };

  banner.innerHTML = `
    <strong>Debug:</strong>
    Awaiting Location: <code>${state.awaitingLocation}</code> |
    Last Query: <code>${state.lastQuery}</code> |
    Open: <code>${state.chatOpened}</code> |
    History: <code>${state.historyCount} messages</code>
  `;

  banner.style.display = 'block';
}

function appendMessage(sender, message, skipSave = false) {
  const body = document.getElementById('gpt-chatbot-body');
  const msg = document.createElement('div');
  msg.className = 'chatbot-msg ' + (sender === 'user' ? 'user' : 'bot');
  msg.innerHTML = `<strong>${sender === 'user' ? 'You' : 'Townie'}:</strong> ${message}`;
  body.appendChild(msg);
  body.scrollTop = body.scrollHeight;

  if (!skipSave && message !== '...typing') {
    chatState.push({ sender, message });
    localStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(chatState));
  }
}

function restoreChatHistory() {
  console.log('📦 Restoring chat history');
  chatState.forEach(({ sender, message }) => appendMessage(sender, message, true));
  updateDebugBanner(); // ✅ Optional but helpful
}

function showTypingIndicator() {
  appendMessage('Townie', '...typing', true);
}

function replaceLastBotTypingWith(message) {
  const last = document.querySelector('.chatbot-msg.bot:last-child');
  if (last && last.innerText.includes('...typing')) {
    last.innerHTML = `<strong>Townie:</strong> ${message}`;
  } else {
    appendMessage('Townie', message);
    return;
  }
  chatState.push({ sender: 'Townie', message });
  localStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(chatState));
  updateDebugBanner();
}

function showGreeting() {
  console.log('👋 showGreeting() was called');

  const greetings = [
    "🌟 Hey there! I’m Townie, your local legend finder. Drop a keyword like 'taco truck' or 'wedding photographer' and I’ll track down the best nearby.",
    "👋 Yo! I’m Townie — your small-town sidekick. Searching for a roofer, hair stylist, or handyman? Just drop a keyword and I’ll get to work.",
    "🏡 Sup! I’m Townie, and I know the neighborhood better than the GPS. Type in what you need — like 'SEO help' or 'chiropractor' — and I’ll connect you fast.",
    "😎 Welcome to MySmallTowns — I’m Townie, your local plug. Looking for a mechanic? Massage therapist? Just type a keyword and I’ll scout your options.",
    "🎯 Hi hi! I’m Townie — kind of like Yelp’s cooler cousin. Enter something like 'family dentist' or 'AC repair' and I’ll show you who’s nearby.",
    "🤖 Hey hey! I’m Townie 👋 — your friendly small-town matchmaker. Drop a keyword — like 'pizza', 'tattoo artist', or 'business coach' — and I’ll find your match."
  ];

  const lastIndex = parseInt(localStorage.getItem('last_greeting_index')) || -1;
  let newIndex;
  do {
    newIndex = Math.floor(Math.random() * greetings.length);
  } while (newIndex === lastIndex && greetings.length > 1);
  localStorage.setItem('last_greeting_index', newIndex);

  appendMessage('Townie', greetings[newIndex] + '<br><br>What can I help you find today?');
  updateDebugBanner();
}

function offerRetryOptions(query) {
  appendMessage('Townie', "Would you like to 🔁 Search another county or 🔄 Try a new industry?");

  const container = document.createElement('div');
  container.className = 'chatbot-options';

  const countyBtn = document.createElement('button');
  countyBtn.textContent = '🔁 Search Another County';
  countyBtn.onclick = () => handleCountyRetry();
  container.appendChild(countyBtn);

  const industryBtn = document.createElement('button');
  industryBtn.textContent = '🔄 Try New Industry';
  industryBtn.onclick = () => handleIndustryRetry();
  container.appendChild(industryBtn);

  document.getElementById('gpt-chatbot-body').appendChild(container);
  chatState.awaitingLocation = true;
  chatState.lastQuery = query;
  localStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(chatState));

  showCountyButtons(); // 🧩 add this here
  updateDebugBanner();
}

function handleCountyRetry() {
  console.log('🧭 Retrying county search with lastQuery:', chatState.lastQuery);
  showCountyButtons();
updateDebugBanner();
}

function handleIndustryRetry() {
  chatState = [];
  localStorage.removeItem(CHAT_HISTORY_KEY);
  document.getElementById('gpt-chatbot-body').innerHTML = '';
  showGreeting();
  updateDebugBanner();
}

function sendMessage() {
  const input = document.getElementById('gpt-chatbot-input');
  const msg = input.value.trim();
  if (!msg) return;

  appendMessage('user', msg);
  input.value = '';
  showTypingIndicator();

  fetch(customGptChatbotAjax.ajax_url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'custom_gpt_chatbot_message',
      nonce: customGptChatbotAjax.nonce,
      message: msg
    })
  })
    .then(res => res.json())
    .then(data => {
      console.log('🧠 Chatbot response:', data);

      if (data.success) {
        if (typeof data.data === 'string') {
          let message = data.data;

          // Check for server-side fallback signal
          const hasRetryToken = message.includes('<!--RETRY-OPTIONS-->');
          if (hasRetryToken) {
            message = message.replace('<!--RETRY-OPTIONS-->', '');
            offerRetryOptions(chatState.lastQuery || msg); // ✅ always pass clean original
          }

          // Also check for GPT phrasing fallback
          if (
            message.toLowerCase().includes("couldn't find anything relevant nearby") ||
            message.toLowerCase().includes("don't be discouraged")
          ) {
            offerRetryOptions(chatState.lastQuery || msg);
          }
          
          if (message.toLowerCase().includes("which city or county")) {
          chatState.awaitingLocation = true;
          chatState.lastQuery = msg;
          showCountyButtons(); // 👈 This was missing
        }

          replaceLastBotTypingWith(message);
          updateDebugBanner();
          return;
        }

        allBotResults = splitIntoChunks(data.data);
        visibleCount = 3;
        renderVisibleResults();
        new Audio('https://mysmalltowns.com/wp-content/uploads/2025/04/mykey-3158.mp3').play();
        updateDebugBanner();
      } else {
        replaceLastBotTypingWith('Something went wrong.');
        updateDebugBanner();
      }
    })
    .catch(err => {
      console.error('❌ Fetch failed:', err);
      replaceLastBotTypingWith('Error reaching server.');
      updateDebugBanner();
    });
}

function sendMessageWithLocation(keyword, county) {
  appendMessage('user', county);
  showTypingIndicator();

  const combined = `${keyword} in ${county}`;

  fetch(customGptChatbotAjax.ajax_url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'custom_gpt_chatbot_message',
      nonce: customGptChatbotAjax.nonce,
      message: combined
    })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        replaceLastBotTypingWith(data.data);
        chatState.awaitingLocation = false;
        chatState.lastQuery = '';
      } else {
        replaceLastBotTypingWith('Something went wrong.');
      }
      document.getElementById('chatbot-county-buttons').style.display = 'none';
      updateDebugBanner(); // ✅ update here after handling success/failure
    })
    .catch(err => {
      console.error('❌ Fetch failed:', err);
      replaceLastBotTypingWith('Error reaching server.');
      document.getElementById('chatbot-county-buttons').style.display = 'none';
      updateDebugBanner(); // ✅ and here too
    });
}

function showCountyButtons() {
  const container = document.getElementById('chatbot-county-buttons');
  container.innerHTML = ''; // Clear any previous buttons

  const counties = ['Clay', 'Duval', 'St. Johns', 'Nassau', 'Flagler'];

  counties.forEach(county => {
    const btn = document.createElement('button');
    btn.textContent = county;
    btn.onclick = () => sendMessageWithLocation(chatState.lastQuery, county);
    btn.style.margin = '5px';
    btn.className = 'chatbot-county-button';
    container.appendChild(btn);
  });

  container.style.display = 'block';
}

function splitIntoChunks(rawHtml) {
  const temp = document.createElement('div');
  temp.innerHTML = rawHtml;
  const cards = temp.querySelectorAll('.chatbot-card');
  return Array.from(cards);
}

function renderVisibleResults(filterCounty = null) {
  const body = document.getElementById('gpt-chatbot-body');
  const resultsToShow = allBotResults.filter(card => {
    if (!filterCounty) return true;
    return card.outerHTML.toLowerCase().includes(filterCounty.toLowerCase());
  });

  const visible = resultsToShow.slice(0, visibleCount);
  visible.forEach(card => {
    const wrapper = document.createElement('div');
    wrapper.className = 'chatbot-msg bot';
    wrapper.innerHTML = `<strong>Townie:</strong> ${card.outerHTML}`;
    body.appendChild(wrapper);
    chatState.push({ sender: 'Townie', message: card.outerHTML });
  });
  localStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(chatState));

  if (visibleCount < resultsToShow.length) {
    const showMoreBtn = document.createElement('button');
    showMoreBtn.textContent = 'Show More';
    showMoreBtn.className = 'chatbot-more-btn';
    showMoreBtn.onclick = () => {
      visibleCount += 3;
      document.querySelectorAll('.chatbot-msg.bot').forEach(el => el.remove());
      renderVisibleResults(filterCounty);
    };
    body.appendChild(showMoreBtn);
  }

  // Auto show county filter options
  if (!filterCounty) {
    renderCountyFilterOptions();
  }

  // 🔁 Retry options shown at the end of listings
  const retryOptions = document.createElement('div');
  retryOptions.className = 'chatbot-options';

  const countyBtn = document.createElement('button');
  countyBtn.textContent = '🔁 Search Another County';
  countyBtn.onclick = () => handleCountyRetry();
  retryOptions.appendChild(countyBtn);

  const industryBtn = document.createElement('button');
  industryBtn.textContent = '🔄 Try New Industry';
  industryBtn.onclick = () => handleIndustryRetry();
  retryOptions.appendChild(industryBtn);

  body.appendChild(retryOptions);

  body.scrollTop = body.scrollHeight;
}

function renderCountyFilterOptions() {
  const counties = ['Clay', 'Duval', 'Flagler', 'Nassau', 'St. Johns'];
  const body = document.getElementById('gpt-chatbot-body');
  const wrapper = document.createElement('div');
  wrapper.className = 'county-filters';

  counties.forEach(county => {
    const btn = document.createElement('button');
    btn.textContent = county;
    btn.className = 'county-btn';
    btn.onclick = () => {
      visibleCount = 3;
      document.querySelectorAll('.chatbot-msg.bot').forEach(el => el.remove());
      renderVisibleResults(county);

      // Add filter reset button
      const resetBtn = document.createElement('button');
      resetBtn.textContent = '🔁 Filter Another Area';
      resetBtn.className = 'chatbot-show-more';
      resetBtn.onclick = () => {
        document.querySelectorAll('.chatbot-msg.bot').forEach(el => el.remove());
        renderVisibleResults();
        resetBtn.remove();
      };
      body.appendChild(resetBtn);
    };
    wrapper.appendChild(btn);
  });

  body.appendChild(wrapper);

  // Search another company
  const restartBtn = document.createElement('button');
  restartBtn.textContent = '🔄 Search Another Company';
  restartBtn.className = 'chatbot-show-more';
  restartBtn.onclick = () => {
    chatState = [];
    localStorage.removeItem(CHAT_HISTORY_KEY);
    document.getElementById('gpt-chatbot-body').innerHTML = '';
    showGreeting();
  };
  body.appendChild(restartBtn);

  body.scrollTop = body.scrollHeight;
}