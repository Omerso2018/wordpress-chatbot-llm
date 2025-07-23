<?php
/*
Plugin Name: Chatbot TogetherAI Backend with Memory
Description: REST API and chatbot UI integration with Together AI, including conversation memory.
Version: 1.2
Author: YourName (Updated by Manus)
*/

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Ensure session is started for REST API requests (needed for guest users)
add_action('init', function() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
});

// Register REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('chatbot/v1', '/message', array(
        'methods' => 'POST',
        'callback' => 'chatbot_get_response_with_memory',
        'permission_callback' => '__return_true' // Allow public access
    ));
});

// Handle chatbot message, maintain history, and forward to Together.ai
function chatbot_get_response_with_memory($request) {
    // Ensure session is available
    if (!session_id() && !headers_sent()) {
        session_start();
    }

    $message = sanitize_text_field($request->get_param('message'));
    if (empty($message)) {
        return new WP_Error('empty_message', 'Message is required', array('status' => 400));
    }

    // --- Configuration ---
    $api_key = 'your-api-key'; // Replace with your actual API key securely (e.g., WP options)
    $model = 'your-model-name';
    $max_history_messages = 10; // Number of messages (user + assistant) to keep in history
    $max_tokens = 300;
    $temperature = 0.7;
    $top_p = 0.9;

    // System prompt - customize this
    $system_prompt = "You are a helpful AI assistant for [Your Website/Company Name]. You should:
- Be friendly, professional, and helpful
- Keep responses concise and clear
- If asked about [Your Business], explain that [add your business description here]
- For technical questions, provide accurate and helpful information
- If you don't know something, admit it rather than guessing
- Always maintain a positive and supportive tone
- Use the conversation history to provide contextually relevant answers.

Your main goal is to assist users and provide valuable information about our services and general topics based on the ongoing conversation.";
    // --- End Configuration ---

    // Initialize or retrieve conversation history from session
    $history_key = 'chatbot_conversation_history';
    if (!isset($_SESSION[$history_key]) || !is_array($_SESSION[$history_key])) {
        $_SESSION[$history_key] = [];
    }
    $conversation_history = $_SESSION[$history_key];

    // Prepare the messages array for the API
    $messages_for_api = [];
    $messages_for_api[] = ['role' => 'system', 'content' => $system_prompt];

    // Add historical messages (up to the limit)
    $messages_for_api = array_merge($messages_for_api, $conversation_history);

    // Add the current user message
    $current_user_message = ['role' => 'user', 'content' => $message];
    $messages_for_api[] = $current_user_message;

    // Make the API call to your Endpoint (openrouter, TogetherAi,..)
    $response = wp_remote_post('https:// your-endpoint-here', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => $model,
            'messages' => $messages_for_api,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'top_p' => $top_p,
            'stop' => null
        )),
        'timeout' => 30,
    ));

    // Handle API response errors
    if (is_wp_error($response)) {
        error_log('Chatbot API Error: ' . $response->get_error_message());
        return rest_ensure_response(array(
            'success' => false,
            'message' => 'Failed to contact AI service. Please try again later.'
        ));
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($http_code !== 200) {
        error_log('Chatbot API Error: HTTP ' . $http_code . ' - Body: ' . $body);
        return rest_ensure_response(array(
            'success' => false,
            'message' => 'AI service temporarily unavailable (Code: ' . $http_code . '). Please try again later.'
        ));
    }

    // Process successful response
    $body_decoded = json_decode($body, true);

    if (isset($body_decoded['choices'][0]['message']['content'])) {
        $ai_response_content = trim($body_decoded['choices'][0]['message']['content']);
        $current_ai_message = ['role' => 'assistant', 'content' => $ai_response_content];

        // Add current user message and AI response to history
        $conversation_history[] = $current_user_message;
        $conversation_history[] = $current_ai_message;

        // Trim history if it exceeds the maximum length
        if (count($conversation_history) > $max_history_messages) {
            // Remove the oldest messages (keeping the most recent ones)
            $conversation_history = array_slice($conversation_history, -$max_history_messages);
        }

        // Save updated history back to session
        $_SESSION[$history_key] = $conversation_history;

        // Return success response
        return rest_ensure_response(array(
            'success' => true,
            'message' => $ai_response_content
        ));
    } else {
        error_log('Chatbot API Error: Unexpected response format - Body: ' . $body);
        return rest_ensure_response(array(
            'success' => false,
            'message' => 'No valid response received from AI service.'
        ));
    }
}

// Inject chatbot UI in footer (No changes needed here for memory functionality)
add_action('wp_footer', function () {
?>
<style>
/* --- CSS Styles (Unchanged) --- */
* {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        #chatbot {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 380px;
            max-width: calc(100vw - 40px);
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1), 0 8px 16px rgba(0, 0, 0, 0.06);
            z-index: 9999;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        #chatbot.hidden {
            transform: translateY(100%);
            opacity: 0;
            pointer-events: none;
        }

        #chatbot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-left::before {
            content: "💬";
            font-size: 20px;
        }

        #close-button {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.2s ease;
            line-height: 1;
        }

        #close-button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        #close-button:active {
            transform: scale(0.95);
        }

        #chatbot-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border: none;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
            z-index: 9998;
            transition: all 0.3s ease;
        }

        #chatbot-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 32px rgba(102, 126, 234, 0.4);
        }

        #chatbot-toggle.show {
            display: flex;
        }

        #chatbot-messages {
            height: 280px;
            overflow-y: auto;
            padding: 20px;
            background: #fafbfc;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        #chatbot-messages::-webkit-scrollbar {
            width: 6px;
        }

        #chatbot-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        #chatbot-messages::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }

        #chatbot-messages::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .message {
            display: flex;
            margin-bottom: 12px;
            animation: slideIn 0.3s ease-out;
        }

        .message-user {
            justify-content: flex-end;
        }

        .message-bot {
            justify-content: flex-start;
        }

        .message-content {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        .message-user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 6px;
        }

        .message-bot .message-content {
            background: white;
            color: #374151;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin: 0 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            align-self: flex-end;
        }

        .message-user .message-avatar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            order: 2;
        }

        .message-bot .message-avatar {
            background: #f3f4f6;
            color: #6b7280;
        }

        #chatbot-input-container {
            padding: 20px;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        #chatbot-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
            background: #f9fafb;
        }

        #chatbot-input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        #send-button {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 16px;
        }

        #send-button:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        #send-button:active {
            transform: scale(0.95);
        }

        #send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .typing-indicator {
            display: none;
            padding: 12px 16px;
            background: white;
            border-radius: 18px;
            border-bottom-left-radius: 6px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            max-width: 75%;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #9ca3af;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        .error-message {
            color: #dc2626;
            font-style: italic;
        }

        @keyframes typing {
            0%, 80%, 100% {
                transform: scale(0.8);
                opacity: 0.5;
            }
            40% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 480px) {
            #chatbot {
                bottom: 10px;
                right: 10px;
                left: 10px;
                width: auto;
                max-width: none;
            }

            #chatbot-toggle {
                bottom: 10px;
                right: 10px;
            }

            #chatbot-messages {
                height: 240px;
                padding: 16px;
            }

            #chatbot-header {
                padding: 16px;
            }

            #chatbot-input-container {
                padding: 16px;
            }
        }
</style>

<div id="chatbot" class="hidden">
    <div id="chatbot-header">
        <div class="header-left">
            AI Assistant
        </div>
        <button id="close-button">×</button>
    </div>
    <div id="chatbot-messages">
        <div class="message message-bot">
            <div class="message-avatar">🤖</div>
            <div class="message-content">Hello! How can I help you today?</div>
        </div>
        <div class="typing-indicator">
            <div class="typing-dots">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
        </div>
    </div>
    <div id="chatbot-input-container">
        <input type="text" id="chatbot-input" placeholder="Type your message...">
        <button id="send-button">➤</button>
    </div>
</div>

<button id="chatbot-toggle" class="show">💬</button>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('chatbot-input');
    const messages = document.getElementById('chatbot-messages');
    const sendButton = document.getElementById('send-button');
    const typingIndicator = document.querySelector('.typing-indicator');
    const chatbot = document.getElementById('chatbot');
    const closeButton = document.getElementById('close-button');
    const toggleButton = document.getElementById('chatbot-toggle');

    // --- JavaScript (Unchanged) ---
    function addMessage(content, isUser = false, isError = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isUser ? 'message-user' : 'message-bot'}`;

        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.textContent = isUser ? '👤' : '🤖';

        const messageContent = document.createElement('div');
        messageContent.className = `message-content ${isError ? 'error-message' : ''}`;
        messageContent.textContent = content;

        messageDiv.appendChild(avatar);
        messageDiv.appendChild(messageContent);

        messages.insertBefore(messageDiv, typingIndicator);
        messages.scrollTop = messages.scrollHeight;
    }

    function showTyping() {
        typingIndicator.style.display = 'block';
        messages.scrollTop = messages.scrollHeight;
    }

    function hideTyping() {
        typingIndicator.style.display = 'none';
    }

    function closeChatbot() {
        chatbot.classList.add('hidden');
        toggleButton.classList.add('show');
    }

    function openChatbot() {
        chatbot.classList.remove('hidden');
        toggleButton.classList.remove('show');
        input.focus();
    }

    function sendMessage() {
        const msg = input.value.trim();
        if (msg === '') return;

        addMessage(msg, true);
        input.value = '';
        sendButton.disabled = true;
        showTyping();

        // The frontend still sends only the current message.
        // The backend now handles the history.
        fetch('/wp-json/chatbot/v1/message', { // Endpoint remains the same
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg })
        })
        .then(res => {
            if (!res.ok) {
                // Handle HTTP errors (like 500 Internal Server Error)
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.json();
        })
        .then(data => {
            setTimeout(() => {
                hideTyping();
                if (data.success) {
                    addMessage(data.message);
                } else {
                    addMessage(data.message || 'Sorry, something went wrong. Please try again.', false, true);
                }
                sendButton.disabled = false;
            }, 500); // Simulate slight delay for realism
        })
        .catch(error => {
            console.error('Chatbot Fetch Error:', error);
            hideTyping();
            addMessage('Sorry, an error occurred while connecting. Please try again later.', false, true);
            sendButton.disabled = false;
        });
    }

    input.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    sendButton.addEventListener('click', sendMessage);
    closeButton.addEventListener('click', closeChatbot);
    toggleButton.addEventListener('click', openChatbot);

    // Optionally focus input when chatbot opens
    // input.focus(); // Might be annoying if it opens automatically
});
</script>
<?php });

