-- AI Chatbot Tables

-- Chatbot Conversations Table
CREATE TABLE chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(100),
    user_message TEXT NOT NULL,
    bot_response TEXT NOT NULL,
    intent VARCHAR(50),
    confidence FLOAT,
    context_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_created (created_at)
);

-- Chatbot Knowledge Base
CREATE TABLE chatbot_knowledge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    question_pattern VARCHAR(255) NOT NULL,
    answer_template TEXT NOT NULL,
    requires_api TINYINT(1) DEFAULT 0,
    api_endpoint VARCHAR(100),
    priority INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_pattern (question_pattern)
);

-- Stock Alerts Table
CREATE TABLE stock_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    alert_type ENUM('low_stock', 'out_of_stock', 'expiry') NOT NULL,
    threshold_value INT,
    is_triggered TINYINT(1) DEFAULT 0,
    notification_sent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_triggered (is_triggered),
    INDEX idx_type (alert_type)
);

-- AI API Configuration
CREATE TABLE ai_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    api_endpoint VARCHAR(255),
    model_name VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Chatbot Sessions for Analytics
CREATE TABLE chatbot_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL UNIQUE,
    user_id INT,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    total_messages INT DEFAULT 0,
    satisfaction_rating INT,
    was_helpful TINYINT(1),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session_id (session_id)
);

-- Insert Default Knowledge Base
INSERT INTO chatbot_knowledge (category, question_pattern, answer_template, requires_api, priority) VALUES
('stock', 'is {medicine} available', 'Let me check the availability of {medicine} for you.', 1, 10),
('stock', 'do you have {medicine}', 'I\'ll check if {medicine} is in stock.', 1, 10),
('stock', '{medicine} stock', 'Checking {medicine} availability...', 1, 10),
('price', 'price of {medicine}', 'Let me find the price for {medicine}.', 1, 9),
('price', 'how much is {medicine}', 'Checking the price of {medicine}...', 1, 9),
('price', 'cost of {medicine}', 'The price for {medicine} is:', 1, 9),
('general', 'what are your hours', 'Our pharmacy is open 24/7 for online orders. Delivery hours are 9 AM to 9 PM.', 0, 5),
('general', 'delivery time', 'We offer same-day delivery for orders placed before 3 PM. Standard delivery is within 24-48 hours.', 0, 5),
('general', 'prescription required', 'Prescription medicines require a valid prescription from a licensed doctor. You can upload it during checkout.', 0, 5),
('general', 'return policy', 'Medicines cannot be returned due to safety regulations. Please check your order carefully before purchasing.', 0, 5),
('report', 'sales report', 'I can generate a sales report for you. What time period would you like?', 1, 8),
('report', 'stock report', 'Let me generate a stock status report for you.', 1, 8),
('greeting', 'hello', 'Hello! Welcome to our Pharmacy. How can I assist you today?', 0, 1),
('greeting', 'hi', 'Hi there! How can I help you with your medical needs today?', 0, 1),
('help', 'help', 'I can help you with:\n- Checking medicine availability\n- Finding medicine prices\n- Order status\n- General pharmacy information\nWhat do you need help with?', 0, 3);

-- Insert Default AI Config (placeholder - user must update with real API key)
INSERT INTO ai_config (provider, api_key, api_endpoint, model_name, settings) VALUES
('gemini', 'AIzaSyCvnGQ1fTHV9_qykwwGxelpcKrN_exiIUg', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent', 'gemini-pro', '{"temperature": 0.7, "max_tokens": 1000}'),

