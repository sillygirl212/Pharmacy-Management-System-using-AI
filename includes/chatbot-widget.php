<?php
/**
 * AI Chatbot Widget - Include this in footer.php
 */
?>

<!-- Chatbot Widget -->
<div id="chatbot-widget" class="chatbot-widget">
    <!-- Chat Button -->
    <button id="chatbot-toggle" class="chatbot-toggle" onclick="toggleChatbot()">
        <i class="fas fa-robot"></i>
        <span class="chatbot-label">AI Assistant</span>
    </button>
    
    <!-- Chat Window -->
    <div id="chatbot-window" class="chatbot-window">
        <div class="chatbot-header">
            <div class="chatbot-title">
                <i class="fas fa-robot me-2"></i>
                <span>Pharmacy AI Assistant</span>
            </div>
            <button class="chatbot-close" onclick="toggleChatbot()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="chatbot-messages" id="chatbot-messages">
            <div class="message bot-message">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <p>👋 Hello! I'm your AI Pharmacy Assistant.</p>
                    <p>I can help you with:</p>
                    <ul>
                        <li>🔍 Checking medicine availability</li>
                        <li>💰 Finding medicine prices</li>
                        <li>📦 Order information</li>
                        <li>❓ General questions</li>
                    </ul>
                    <p>What can I help you with today?</p>
                </div>
            </div>
        </div>
        
        <div class="chatbot-typing" id="chatbot-typing" style="display: none;">
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
        
        <div class="chatbot-input-area">
            <form id="chatbot-form" onsubmit="sendChatMessage(event)">
                <div class="chatbot-input-group">
                    <input 
                        type="text" 
                        id="chatbot-input" 
                        class="chatbot-input" 
                        placeholder="Type your message..."
                        autocomplete="off"
                    >
                    <button type="submit" class="chatbot-send">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Chatbot Widget Styles */
.chatbot-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
}

.chatbot-toggle {
    background: linear-gradient(135deg, #1266f1 0%, #00b74a 100%);
    color: white;
    border: none;
    border-radius: 50px;
    padding: 15px 25px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(18, 102, 241, 0.4);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.chatbot-toggle:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(18, 102, 241, 0.5);
}

.chatbot-toggle i {
    font-size: 24px;
}

.chatbot-window {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 380px;
    height: 500px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    display: none;
    flex-direction: column;
    overflow: hidden;
}

.chatbot-window.active {
    display: flex;
}

.chatbot-header {
    background: linear-gradient(135deg, #1266f1 0%, #00b74a 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chatbot-title {
    display: flex;
    align-items: center;
    font-weight: 600;
}

.chatbot-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
}

.chatbot-close:hover {
    background: rgba(255,255,255,0.3);
}

.chatbot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f8f9fa;
}

.message {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1266f1 0%, #00b74a 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.user-message .message-avatar {
    background: #6c757d;
    order: 2;
}

.user-message {
    flex-direction: row-reverse;
}

.message-content {
    background: white;
    padding: 12px 16px;
    border-radius: 18px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    max-width: 80%;
}

.user-message .message-content {
    background: linear-gradient(135deg, #1266f1 0%, #00b74a 100%);
    color: white;
}

.message-content p {
    margin-bottom: 8px;
    line-height: 1.5;
}

.message-content p:last-child {
    margin-bottom: 0;
}

.message-content ul {
    margin: 10px 0;
    padding-left: 20px;
}

.message-content li {
    margin-bottom: 5px;
}

.chatbot-typing {
    padding: 0 20px 10px;
}

.typing-indicator {
    display: flex;
    gap: 5px;
    padding: 12px 16px;
    background: white;
    border-radius: 18px;
    width: fit-content;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: #1266f1;
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-10px); }
}

.chatbot-input-area {
    padding: 15px 20px;
    background: white;
    border-top: 1px solid #e9ecef;
}

.chatbot-input-group {
    display: flex;
    gap: 10px;
}

.chatbot-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 25px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.chatbot-input:focus {
    outline: none;
    border-color: #1266f1;
}

.chatbot-send {
    background: linear-gradient(135deg, #1266f1 0%, #00b74a 100%);
    color: white;
    border: none;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chatbot-send:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 10px rgba(18, 102, 241, 0.3);
}

/* Quick Actions */
.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.quick-action-btn {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.quick-action-btn:hover {
    background: #1266f1;
    color: white;
    border-color: #1266f1;
}

/* Responsive */
@media (max-width: 480px) {
    .chatbot-window {
        width: calc(100vw - 40px);
        height: calc(100vh - 150px);
        right: 0;
        left: 0;
        margin: 0 auto;
    }
    
    .chatbot-toggle {
        padding: 12px 20px;
    }
    
    .chatbot-label {
        display: none;
    }
}
</style>

<script>
let chatbotSessionId = localStorage.getItem('chatbot_session_id') || null;
let chatbotMessages = document.getElementById('chatbot-messages');

function toggleChatbot() {
    const window = document.getElementById('chatbot-window');
    window.classList.toggle('active');
    
    if (window.classList.contains('active')) {
        document.getElementById('chatbot-input').focus();
    }
}

function sendChatMessage(event) {
    event.preventDefault();
    
    const input = document.getElementById('chatbot-input');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message
    addMessage(message, 'user');
    input.value = '';
    
    // Show typing indicator
    showTyping();
    
    // Send to server
    fetch('ajax/chatbot.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `message=${encodeURIComponent(message)}&session_id=${encodeURIComponent(chatbotSessionId || '')}`
    })
    .then(response => response.json())
    .then(data => {
        hideTyping();
        
        if (data.success) {
            // Save session ID
            if (data.session_id) {
                chatbotSessionId = data.session_id;
                localStorage.setItem('chatbot_session_id', chatbotSessionId);
            }
            
            // Add bot response
            addMessage(data.response, 'bot');
        } else {
            addMessage("I'm sorry, I couldn't process your message. Please try again or contact our pharmacy directly.", 'bot');
        }
    })
    .catch(error => {
        hideTyping();
        addMessage("I'm having trouble connecting. Please try again later.", 'bot');
        console.error('Chatbot error:', error);
    });
}

function addMessage(text, sender) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}-message`;
    
    const avatar = sender === 'bot' 
        ? '<i class="fas fa-robot"></i>' 
        : '<i class="fas fa-user"></i>';
    
    // Convert newlines to HTML
    const formattedText = text.replace(/\n/g, '<br>');
    
    messageDiv.innerHTML = `
        <div class="message-avatar">${avatar}</div>
        <div class="message-content">${formattedText}</div>
    `;
    
    chatbotMessages.appendChild(messageDiv);
    scrollToBottom();
}

function showTyping() {
    document.getElementById('chatbot-typing').style.display = 'block';
    scrollToBottom();
}

function hideTyping() {
    document.getElementById('chatbot-typing').style.display = 'none';
}

function scrollToBottom() {
    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
}

// Quick action buttons
function addQuickActions() {
    const actions = [
        { text: "Check Stock", query: "Is Paracetamol available?" },
        { text: "Price Check", query: "Price of Vitamin D3" },
        { text: "My Orders", query: "Where is my order?" },
        { text: "Contact", query: "How can I contact you?" }
    ];
    
    const actionsDiv = document.createElement('div');
    actionsDiv.className = 'quick-actions';
    
    actions.forEach(action => {
        const btn = document.createElement('button');
        btn.className = 'quick-action-btn';
        btn.textContent = action.text;
        btn.onclick = () => {
            document.getElementById('chatbot-input').value = action.query;
            document.getElementById('chatbot-form').dispatchEvent(new Event('submit'));
        };
        actionsDiv.appendChild(btn);
    });
    
    return actionsDiv;
}

// Add quick actions to first bot message
window.addEventListener('load', function() {
    const firstMessage = document.querySelector('.bot-message .message-content');
    if (firstMessage) {
        firstMessage.appendChild(addQuickActions());
    }
});

// Handle Enter key
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const window = document.getElementById('chatbot-window');
        if (window.classList.contains('active')) {
            toggleChatbot();
        }
    }
});
</script>
