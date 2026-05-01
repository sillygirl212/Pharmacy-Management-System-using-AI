-- Pharmacy Management System Database Schema
-- Created: 2026

CREATE DATABASE IF NOT EXISTS pharmacy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacy_db;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('user', 'admin', 'editor') DEFAULT 'user',
    status ENUM('active', 'blocked') DEFAULT 'active',
    email_verified TINYINT(1) DEFAULT 0,
    reset_token VARCHAR(100),
    reset_token_expiry DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products Table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    short_description VARCHAR(500),
    category_id INT,
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2),
    stock_quantity INT DEFAULT 0,
    sku VARCHAR(50) UNIQUE,
    manufacturer VARCHAR(100),
    expiry_date DATE,
    batch_number VARCHAR(50),
    dosage VARCHAR(100),
    prescription_required TINYINT(1) DEFAULT 0,
    featured TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    meta_title VARCHAR(200),
    meta_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Product Images Table
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Cart Table
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(100),
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Coupons Table
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('percentage', 'fixed') NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    min_purchase DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2),
    usage_limit INT,
    usage_count INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders Table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_gateway VARCHAR(50),
    transaction_id VARCHAR(100),
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    coupon_code VARCHAR(50),
    tax DECIMAL(10,2) DEFAULT 0,
    shipping DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    billing_name VARCHAR(100),
    billing_email VARCHAR(100),
    billing_phone VARCHAR(20),
    billing_address TEXT,
    billing_city VARCHAR(50),
    billing_state VARCHAR(50),
    billing_zip VARCHAR(20),
    billing_country VARCHAR(50),
    shipping_name VARCHAR(100),
    shipping_address TEXT,
    shipping_city VARCHAR(50),
    shipping_state VARCHAR(50),
    shipping_zip VARCHAR(20),
    shipping_country VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order Items Table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(200),
    product_price DECIMAL(10,2),
    quantity INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Digital Downloads Table (for prescription files)
CREATE TABLE digital_downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    file_path VARCHAR(255),
    download_count INT DEFAULT 0,
    max_downloads INT DEFAULT 5,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Support Tickets Table
CREATE TABLE support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ticket_number VARCHAR(50) NOT NULL UNIQUE,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    category VARCHAR(50),
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Ticket Replies Table
CREATE TABLE ticket_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_staff TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- FAQ Table
CREATE TABLE faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50),
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Settings Table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Reviews Table
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Wishlist Table
CREATE TABLE wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id)
);

-- Admin Activity Log
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- AI Chatbot Tables
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

-- Insert Default Admin User (password: admin123)
INSERT INTO users (name, email, phone, password, role, status, email_verified) VALUES 
('Admin', 'tumpa540264@gmail.com', '+8801644076419', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 1);

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_name', 'Pharmacy Management System', 'general'),
('site_logo', '', 'general'),
('site_email', 'tumpa540264@gmail.com', 'general'),
('site_phone', '+8801644076419', 'general'),
('site_address', '123 Farmgate, Dhaka, Bangladesh', 'general'),
('currency', 'BDT', 'payment'),
('currency_symbol', '৳', 'payment'),
('tax_rate', '0', 'payment'),
('shipping_cost', '60', 'payment'),

-- Payment Method Toggles
('enable_bkash', '1', 'payment'),
('enable_nagad', '1', 'payment'),
('enable_cod', '1', 'payment'),

-- bKash Configuration
('bkash_app_key', '', 'payment'),
('bkash_app_secret', '', 'payment'),
('bkash_username', 'sandboxUserName', 'payment'),
('bkash_password', 'sandboxPassword', 'payment'),
('bkash_mode', 'sandbox', 'payment'),

-- Nagad Configuration
('nagad_merchant_id', '', 'payment'),
('nagad_merchant_number', '01XXXXXXXXX', 'payment'),
('nagad_mode', 'sandbox', 'payment'),

('smtp_host', '', 'email'),
('smtp_port', '587', 'email'),
('smtp_username', '', 'email'),
('smtp_password', '', 'email'),
('smtp_encryption', 'tls', 'email');

-- Insert Sample Categories
INSERT INTO categories (name, slug, description, status) VALUES
('Medicines', 'medicines', 'Prescription and over-the-counter medicines', 'active'),
('Health Supplements', 'supplements', 'Vitamins and dietary supplements', 'active'),
('Personal Care', 'personal-care', 'Personal hygiene and care products', 'active'),
('Medical Devices', 'medical-devices', 'Blood pressure monitors, thermometers, etc.', 'active'),
('Baby Care', 'baby-care', 'Products for infants and babies', 'active');

-- Insert Sample Products
INSERT INTO products (name, slug, description, short_description, category_id, price, sale_price, stock_quantity, sku, manufacturer, prescription_required, featured, status) VALUES
('Paracetamol 500mg', 'paracetamol-500mg', 'Effective pain relief and fever reduction. Paracetamol is a widely used over-the-counter analgesic (pain reliever) and antipyretic (fever reducer).', 'Pain relief and fever reduction tablets', 1, 5.99, 4.99, 500, 'MED-PARA-500', 'PharmaCorp', 0, 1, 'active'),
('Vitamin D3 1000IU', 'vitamin-d3-1000iu', 'High-strength Vitamin D3 supplement for bone health and immune support. Essential for calcium absorption and maintaining strong bones.', 'High-strength Vitamin D3 for bone health', 2, 12.99, NULL, 200, 'SUP-D3-1000', 'NutriWell', 0, 1, 'active'),
('Digital Thermometer', 'digital-thermometer', 'Fast and accurate digital thermometer for body temperature measurement. Features fever alarm and memory function.', 'Fast accurate temperature measurement', 4, 24.99, 19.99, 50, 'DEV-THERM-01', 'MediTech', 0, 0, 'active'),
('Baby Diapers Large', 'baby-diapers-large', 'Soft and absorbent baby diapers with wetness indicator. Hypoallergenic and dermatologically tested for sensitive skin.', 'Soft absorbent diapers for babies', 5, 29.99, NULL, 150, 'BABY-DIAP-L', 'BabyCare', 0, 0, 'active'),
('Amoxicillin 250mg', 'amoxicillin-250mg', 'Broad-spectrum antibiotic used to treat various bacterial infections. Requires prescription.', 'Antibiotic for bacterial infections', 1, 15.99, NULL, 100, 'MED-AMOX-250', 'PharmaCorp', 1, 0, 'active');

-- Insert Sample FAQ
INSERT INTO faq (question, answer, category, status) VALUES
('How do I place an order?', 'Browse products, add to cart, proceed to checkout, and complete payment. You will receive an order confirmation email.', 'orders', 'active'),
('Do I need a prescription?', 'Some medicines require a valid prescription. Products marked with "Prescription Required" cannot be purchased without one.', 'products', 'active'),
('What payment methods do you accept?', 'We accept credit/debit cards, PayPal, and various local payment methods through our secure payment gateways.', 'payment', 'active'),
('How long does delivery take?', 'Standard delivery takes 3-5 business days. Express delivery options are available at checkout.', 'shipping', 'active'),
('Can I return products?', 'Unopened products can be returned within 14 days. Medicines and personal care items cannot be returned for safety reasons.', 'returns', 'active');

-- Insert Default Knowledge Base for AI Chatbot
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

-- Insert Default AI Config (User's Gemini API Key)
INSERT INTO ai_config (provider, api_key, api_endpoint, model_name, settings) VALUES
('gemini', 'AIzaSyCvnGQ1fTHV9_qykwwGxelpcKrN_exiIUg', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent', 'gemini-pro', '{"temperature": 0.7, "max_tokens": 1000}'),
('openai', 'YOUR_OPENAI_API_KEY_HERE', 'https://api.openai.com/v1/chat/completions', 'gpt-3.5-turbo', '{"temperature": 0.7, "max_tokens": 1000}');
