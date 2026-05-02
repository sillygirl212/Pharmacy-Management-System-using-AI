-- Pharmacy Management System Database Schema (COMPLETE with AI Chatbot)
-- Create Database
CREATE DATABASE IF NOT EXISTS pharmacy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacy;

-- Drop existing tables to ensure clean import
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS chatbot_conversations;
DROP TABLE IF EXISTS chatbot_knowledge;
DROP TABLE IF EXISTS stock_alerts;
DROP TABLE IF EXISTS ai_config;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS purchase_items;
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS medicines;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Users/Admin Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('admin', 'pharmacist', 'cashier', 'user') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers Table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Medicines Table
CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200),
    category_id INT,
    supplier_id INT,
    description TEXT,
    side_effects TEXT,
    dosage TEXT,
    warnings TEXT,
    image_path VARCHAR(255),
    unit_price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2),
    stock_quantity INT DEFAULT 0,
    reorder_level INT DEFAULT 10,
    unit VARCHAR(20) DEFAULT 'pieces',
    expiry_date DATE,
    batch_number VARCHAR(50),
    barcode VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    password VARCHAR(255),
    address TEXT,
    date_of_birth DATE,
    allergies TEXT,
    total_purchases DECIMAL(10,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sales/Orders Table
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT,
    user_id INT,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'insurance', 'bkash', 'nagad', 'cod') DEFAULT 'cash',
    payment_phone VARCHAR(20),
    transaction_id VARCHAR(100),
    payment_status ENUM('pending', 'completed', 'refunded', 'pending_delivery') DEFAULT 'completed',
    notes TEXT,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Sale Items Table
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- Inventory/Stock Movements Table
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference_type ENUM('purchase', 'sale', 'return', 'adjustment', 'expired') NOT NULL,
    reference_id INT,
    batch_number VARCHAR(50),
    expiry_date DATE,
    notes TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Purchases Table (for stock-in from suppliers)
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_code VARCHAR(20) NOT NULL UNIQUE,
    supplier_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'received', 'cancelled') DEFAULT 'pending',
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_date TIMESTAMP NULL,
    notes TEXT,
    user_id INT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Purchase Items Table
CREATE TABLE IF NOT EXISTS purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    batch_number VARCHAR(50),
    expiry_date DATE,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- ============================================
-- AI CHATBOT TABLES
-- ============================================

-- Chatbot Conversations Table
CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    user_id INT NULL,
    customer_id INT NULL,
    message TEXT NOT NULL,
    response TEXT NOT NULL,
    intent VARCHAR(50),
    confidence FLOAT,
    source ENUM('ai', 'rule_based', 'hybrid') DEFAULT 'hybrid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_created_at (created_at)
);

-- Chatbot Knowledge Base Table
CREATE TABLE IF NOT EXISTS chatbot_knowledge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intent VARCHAR(50) NOT NULL,
    keywords TEXT NOT NULL,
    response TEXT NOT NULL,
    requires_data BOOLEAN DEFAULT FALSE,
    data_source VARCHAR(50),
    priority INT DEFAULT 5,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_intent (intent),
    INDEX idx_active (is_active)
);

-- Stock Alerts Table
CREATE TABLE IF NOT EXISTS stock_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    alert_type ENUM('low_stock', 'expiry', 'expired') NOT NULL,
    threshold_value INT,
    current_value INT,
    status ENUM('active', 'resolved', 'ignored') DEFAULT 'active',
    notification_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_type (alert_type)
);

-- AI Configuration Table
CREATE TABLE IF NOT EXISTS ai_config (
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

-- System Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);

-- ============================================
-- INSERT SAMPLE DATA
-- ============================================

-- Insert default admin user with PROPERLY HASHED password (admin123)
-- Password hash generated for: admin123
INSERT INTO users (username, password, email, full_name, phone, role, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tumpa540264@gmail.com', 'Administrator', '+8801644076419', 'admin', TRUE);

-- Insert categories
INSERT INTO categories (name, description) VALUES
('Pain Relief', 'Medications for pain management including headaches, muscle pain, and fever'),
('Antibiotics', 'Prescription medications to treat bacterial infections'),
('Vitamins & Supplements', 'Nutritional supplements and vitamins'),
('Antihistamine', 'Allergy medications and antihistamines'),
('Gastrointestinal', 'Digestive health medications'),
('Cardiovascular', 'Heart and blood pressure medications'),
('Diabetes Care', 'Diabetes management medications and supplies'),
('Skin Care', 'Dermatological preparations and skin treatments'),
('Medical Devices', 'Healthcare devices and equipment'),
('Baby Care', 'Baby and infant care products');

-- Insert suppliers
INSERT INTO suppliers (supplier_code, name, contact_person, email, phone, address, status) VALUES
('SUP-001', 'MediPharm Inc.', 'Robert Wilson', 'orders@medipharm.com', '+1 555-0201', '123 Medical Supply St, New York, NY', 'active'),
('SUP-002', 'Global Health Supplies', 'Emily Chen', 'sales@globalhealth.com', '+1 555-0202', '456 Healthcare Ave, Los Angeles, CA', 'active'),
('SUP-003', 'PharmaDirect Ltd.', 'David Martinez', 'contact@pharmadirect.com', '+1 555-0203', '789 Distribution Blvd, Chicago, IL', 'pending'),
('SUP-004', 'MedSupply Co.', 'Sarah Johnson', 'info@medsupply.com', '+1 555-0204', '321 Wholesale Way, Houston, TX', 'active'),
('SUP-005', 'Bangladesh Pharma', 'Tumpa Akter', 'tumpa540264@gmail.com', '+8801644076419', 'Dhaka, Bangladesh', 'active');

-- Insert medicines with Bangladeshi context and full details
INSERT INTO medicines (medicine_code, name, generic_name, category_id, supplier_id, description, side_effects, dosage, warnings, unit_price, cost_price, stock_quantity, reorder_level, unit, expiry_date, batch_number, barcode, is_active) VALUES
('MED-001', 'Napa 500mg (Paracetamol)', 'Paracetamol', 1, 5, 'Pain reliever and fever reducer - Popular in Bangladesh for headaches, body pain, and fever.', 'Rare: Nausea, stomach upset, allergic reactions. Liver damage if overdosed.', 'Adults: 1-2 tablets every 4-6 hours. Max 8 tablets/day. Children: As per doctor prescription.', 'Do not exceed recommended dose. Consult doctor if pain persists >3 days. Not for liver disease patients.', 15.00, 10.00, 500, 50, 'tablets', '2027-12-31', 'BD-BATCH-001', '8940123456789', TRUE),
('MED-002', 'Seclo 20mg (Omeprazole)', 'Omeprazole', 5, 5, 'Acid reducer for heartburn, gastric ulcers, and acid reflux. Take before meals.', 'Headache, diarrhea, nausea, stomach pain, dizziness in some patients.', 'Take 1 capsule daily before breakfast, or as prescribed by doctor. Swallow whole, do not crush.', 'Long-term use may affect bone health. Tell doctor if you have liver disease. Not for immediate relief.', 25.00, 15.00, 200, 30, 'capsules', '2026-11-30', 'BD-BATCH-002', '8940123456790', TRUE),
('MED-003', 'Monas 10mg (Montelukast)', 'Montelukast', 4, 5, 'Allergy and asthma prevention medication. Controls wheezing and breathing difficulty.', 'Drowsiness, headache, stomach upset, mood changes in rare cases.', 'Take 1 tablet daily at bedtime for allergies, or as prescribed for asthma.', 'Not for acute asthma attacks. Seek emergency care for breathing difficulty. Report mood changes to doctor.', 120.00, 80.00, 150, 20, 'tablets', '2027-06-30', 'BD-BATCH-003', '8940123456791', TRUE),
('MED-004', 'Napa Extra (Paracetamol+Caffeine)', 'Paracetamol + Caffeine', 1, 5, 'Extra strength pain relief with caffeine boost for faster action on severe headaches and migraines.', 'Insomnia, nervousness, increased heart rate, stomach upset if empty stomach.', 'Adults: 1 tablet every 6 hours. Max 4 tablets/day. Take after food.', 'Avoid if you have heart problems, anxiety, or high blood pressure. Not for pregnant women without doctor advice.', 20.00, 12.00, 400, 40, 'tablets', '2026-07-20', 'BD-BATCH-004', '8940123456792', TRUE),
('MED-005', 'Flora 4mg (Loratadine)', 'Loratadine', 4, 2, 'Non-drowsy allergy relief for sneezing, runny nose, itchy eyes, and skin allergies.', 'Very rare: Drowsiness (non-drowsy formula), headache, dry mouth.', 'Adults & Children 6+: 1 tablet daily. Best taken in morning.', 'May cause mild drowsiness in some people. Avoid alcohol. Consult doctor for children under 6 years.', 45.00, 28.00, 250, 35, 'tablets', '2026-06-15', 'BD-BATCH-005', '8940123456793', TRUE),
('MED-006', 'Biotin Plus', 'Biotin + Zinc', 3, 2, 'Hair, skin and nail health supplement. Supports growth and strength.', 'Generally safe. Rare: Nausea, skin rash if allergic.', 'Adults: 1 capsule daily after meal. Use for 3-6 months for best results.', 'Not a medicine for disease treatment. Consult doctor if pregnant or nursing. Keep away from children.', 180.00, 100.00, 100, 15, 'capsules', '2026-08-10', 'BD-BATCH-006', '8940123456794', TRUE),
('MED-007', 'Metformin 500mg', 'Metformin', 7, 1, 'Diabetes medication for blood sugar control in Type 2 diabetes.', 'Stomach upset, diarrhea, nausea, metallic taste. Usually improves over time.', 'Start with 1 tablet with meals, increase as doctor advises. Take with food to reduce stomach upset.', 'Not for Type 1 diabetes. Kidney function must be checked. Avoid excess alcohol. Tell doctor before surgery.', 8.50, 5.00, 300, 40, 'tablets', '2027-03-15', 'BD-BATCH-007', '8940123456795', TRUE),
('MED-008', 'Amloc 5mg (Amlodipine)', 'Amlodipine', 6, 1, 'Blood pressure and chest pain (angina) medication. Relaxes blood vessels.', 'Ankle swelling, headache, dizziness, flushing, fatigue.', 'Take 1 tablet daily at same time. Can take with or without food.', 'Do not stop suddenly. Check blood pressure regularly. Avoid grapefruit juice. Tell dentist/doctor before procedures.', 12.00, 7.50, 180, 25, 'tablets', '2027-01-20', 'BD-BATCH-008', '8940123456796', TRUE),
('MED-009', 'Digital Thermometer', 'Digital Device', 9, 4, 'Fast accurate digital thermometer for body temperature measurement.', 'None (device). Clean with alcohol before/after use.', 'Place under tongue or armpit. Wait for beep. Read display. Clean after use.', 'Keep battery away from children. Do not submerge in water. Replace battery when low.', 350.00, 200.00, 50, 10, 'pieces', '2028-12-01', 'BD-BATCH-009', '8940123456797', TRUE),
('MED-010', 'Blood Pressure Monitor', 'BP Device', 9, 4, 'Automatic digital BP monitor for home blood pressure tracking.', 'None (device). Ensure cuff fits properly.', 'Sit quietly 5 min. Wrap cuff on bare arm at heart level. Press start. Wait for reading.', 'Check cuff size fits your arm. Sit properly during measurement. Calibrate annually. Keep batteries fresh.', 2800.00, 1800.00, 25, 5, 'pieces', '2028-04-25', 'BD-BATCH-010', '8940123456798', TRUE);

-- Insert sample customers
INSERT INTO customers (customer_code, first_name, last_name, email, phone, address, date_of_birth, total_purchases, is_active) VALUES
('CUST-001', 'Rahim', 'Khan', 'rahim.khan@gmail.com', '+8801712345678', 'Mirpur, Dhaka', '1985-03-15', 5250.00, TRUE),
('CUST-002', 'Fatima', 'Begum', 'fatima.b@gmail.com', '+8801812345678', 'Gulshan, Dhaka', '1990-07-22', 3890.50, TRUE),
('CUST-003', 'Karim', 'Hossain', 'karim.h@gmail.com', '+8801912345678', 'Dhanmondi, Dhaka', '1978-11-08', 7150.00, TRUE),
('CUST-004', 'Nasrin', 'Akter', 'nasrin.a@gmail.com', '+8801512345678', 'Uttara, Dhaka', '1992-05-30', 1450.00, TRUE),
('CUST-005', 'Jamal', 'Uddin', 'jamal.u@gmail.com', '+8801612345678', 'Mohammadpur, Dhaka', '1983-09-12', 5680.00, TRUE);

-- Insert sample sales
INSERT INTO sales (invoice_number, customer_id, user_id, subtotal, tax_amount, discount_amount, total_amount, payment_method, payment_status, notes, sale_date) VALUES
('INV-001', 1, 1, 125.00, 0.00, 0.00, 125.00, 'bkash', 'completed', 'Paid via bKash', '2026-05-01 10:30:00'),
('INV-002', 2, 1, 78.50, 0.00, 0.00, 78.50, 'nagad', 'completed', 'Paid via Nagad', '2026-05-01 11:45:00'),
('INV-003', 3, 1, 245.00, 0.00, 10.00, 235.00, 'cash', 'completed', 'Cash payment with discount', '2026-04-30 14:20:00'),
('INV-004', 4, 1, 56.00, 0.00, 0.00, 56.00, 'cod', 'completed', 'Cash on delivery', '2026-04-30 16:00:00'),
('INV-005', 1, 1, 189.99, 0.00, 0.00, 189.99, 'bkash', 'completed', 'Repeat customer - bKash', '2026-04-29 09:15:00');

-- Insert sale items
INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, total_price) VALUES
(1, 1, 5, 15.00, 75.00),
(1, 4, 2, 20.00, 40.00),
(1, 5, 1, 45.00, 45.00),
(2, 3, 2, 120.00, 240.00),
(2, 6, 1, 180.00, 180.00),
(3, 7, 10, 8.50, 85.00),
(3, 8, 5, 12.00, 60.00),
(3, 9, 1, 350.00, 350.00),
(4, 2, 2, 25.00, 50.00),
(5, 10, 1, 2800.00, 2800.00);

-- Insert stock movements
INSERT INTO stock_movements (medicine_id, type, quantity, reference_type, reference_id, batch_number, expiry_date, notes, user_id) VALUES
(1, 'in', 1000, 'purchase', 1, 'BD-BATCH-001', '2027-12-31', 'Initial stock', 1),
(1, 'out', 500, 'sale', 1, 'BD-BATCH-001', '2027-12-31', 'Sales', 1),
(2, 'in', 500, 'purchase', 1, 'BD-BATCH-002', '2026-11-30', 'Initial stock', 1),
(2, 'out', 300, 'sale', 1, 'BD-BATCH-002', '2026-11-30', 'Sales', 1);

-- ============================================
-- AI CHATBOT DATA
-- ============================================

-- Insert AI Configuration (Gemini API)
INSERT INTO ai_config (provider, api_key, api_endpoint, model_name, is_active, settings) VALUES
('gemini', 'AIzaSyCvnGQ1fTHV9_qykwwGxelpcKrN_exiIUg', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent', 'gemini-pro', 1, '{"temperature": 0.7, "max_tokens": 1000}'),
('openai', 'YOUR_OPENAI_API_KEY_HERE', 'https://api.openai.com/v1/chat/completions', 'gpt-3.5-turbo', 0, '{"temperature": 0.7, "max_tokens": 1000}');

-- Insert Chatbot Knowledge Base
INSERT INTO chatbot_knowledge (intent, keywords, response, requires_data, data_source, priority, is_active) VALUES
('medicine_availability', 'available,do you have,any left,quantity,in stock', 'I can check the availability of medicines for you. Please tell me which medicine you are looking for.', TRUE, 'medicines', 1, TRUE),
('medicine_price', 'price,cost,how much,tk,taka,৳', 'I can provide pricing information for medicines. Which medicine would you like to know the price for?', TRUE, 'medicines', 1, TRUE),
('stock_report', 'stock,stock report,inventory,stock status', 'Let me generate a stock status report for you.', TRUE, 'stock_report', 5, TRUE),
('sales_report', 'sales report,revenue,today sales,monthly sales', 'I can generate sales reports. What time period would you like to see?', TRUE, 'sales', 8, TRUE),
('greeting', 'hello,hi,hey,good morning,good afternoon,good evening', 'Hello! Welcome to our Pharmacy. How can I assist you today?', FALSE, NULL, 1, TRUE),
('help', 'help,what can you do,support,assist', 'I can help you with:
- Checking medicine availability and prices
- Stock status and reports
- Sales information
- Order status
- General pharmacy information
What do you need help with?', FALSE, NULL, 3, TRUE),
('order_status', 'order status,where is my order,track order,delivery', 'I can help you check your order status. Please provide your order number or registered email/phone.', TRUE, 'orders', 2, TRUE),
('payment_methods', 'payment,pay,bkash,nagad,cash,cod', 'We accept the following payment methods:
- bKash
- Nagad
- Cash on Delivery (COD)
- Cash at store
Which payment method would you like to use?', FALSE, NULL, 2, TRUE),
('delivery_time', 'delivery,shipping,how long,when will arrive,delivery time', 'We offer:
- Same-day delivery for orders placed before 3 PM (Dhaka city)
- Next-day delivery for other areas
- Standard delivery within 24-48 hours
Delivery is free for orders over ৳500!', FALSE, NULL, 2, TRUE),
('prescription', 'prescription,rx,doctor note,medical prescription', 'Prescription medicines require a valid prescription from a licensed doctor. You can upload it during checkout or bring it to our store.', FALSE, NULL, 2, TRUE),
('medicine_details', 'side effects,dosage,how to use,warning,precaution,usage,directions', 'I can provide detailed information about medicines including side effects, dosage, and warnings. Which medicine would you like to know about?', TRUE, 'medicine_details', 1, TRUE),
('total_stock', 'total stock,all stock,inventory summary,total medicines,stock count', 'Let me get the total stock overview for you.', TRUE, 'total_stock', 8, TRUE),
('low_stock_alert', 'low stock,stock reminder,need to reorder,reorder alert', 'Let me check for medicines that need reordering.', TRUE, 'low_stock', 8, TRUE),
('expiry_alert', 'expiring soon,expiry date,about to expire,expiry alert', 'Let me check for medicines expiring soon.', TRUE, 'expiry', 8, TRUE),
('customer_help', 'help customer,customer support,buy medicine,order medicine', 'I can help you:
- Find available medicines
- Check prices
- View medicine details (side effects, dosage)
- Check delivery options
- Track orders
What do you need?', FALSE, NULL, 3, TRUE),
('admin_help', 'admin help,admin features,manage pharmacy,admin commands', 'Admin commands:
- Total stock report
- Low stock alerts
- Sales reports (daily/monthly)
- Expiring medicines
- Stock reorder suggestions
What would you like to see?', FALSE, NULL, 3, TRUE),
('thanks', 'thank you,thanks,thankyou,ty,appreciate', 'You\'re welcome! If you have any other questions, feel free to ask. Have a healthy day!', FALSE, NULL, 1, TRUE),
('goodbye', 'bye,goodbye,see you,later,take care', 'Goodbye! Take care of your health. We\'re here whenever you need us!', FALSE, NULL, 1, TRUE);

-- Insert System Settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_name', 'Pharmacy Management System', 'general'),
('site_email', 'tumpa540264@gmail.com', 'general'),
('site_phone', '+8801644076419', 'general'),
('site_address', 'Dhaka, Bangladesh', 'general'),
('currency', 'BDT', 'payment'),
('currency_symbol', '৳', 'payment'),
('tax_rate', '0', 'payment'),
('shipping_cost', '60', 'payment'),
('enable_bkash', '1', 'payment'),
('enable_nagad', '1', 'payment'),
('enable_cod', '1', 'payment'),
('chatbot_enabled', '1', 'chatbot'),
('chatbot_welcome_message', 'Hello! I am your AI pharmacy assistant. How can I help you today?', 'chatbot'),
('low_stock_threshold', '10', 'inventory'),
('expiry_alert_days', '30', 'inventory');

-- ============================================
-- CREATE INDEXES FOR PERFORMANCE
-- ============================================

CREATE INDEX idx_medicines_category ON medicines(category_id);
CREATE INDEX idx_medicines_supplier ON medicines(supplier_id);
CREATE INDEX idx_medicines_code ON medicines(medicine_code);
CREATE INDEX idx_medicines_stock ON medicines(stock_quantity);
CREATE INDEX idx_medicines_expiry ON medicines(expiry_date);
CREATE INDEX idx_medicines_active ON medicines(is_active);
CREATE INDEX idx_sales_customer ON sales(customer_id);
CREATE INDEX idx_sales_date ON sales(sale_date);
CREATE INDEX idx_sale_items_sale ON sale_items(sale_id);
CREATE INDEX idx_sale_items_medicine ON sale_items(medicine_id);
CREATE INDEX idx_stock_movements_medicine ON stock_movements(medicine_id);
CREATE INDEX idx_customers_code ON customers(customer_code);
CREATE INDEX idx_customers_email ON customers(email);
CREATE INDEX idx_suppliers_code ON suppliers(supplier_code);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_conversations_session ON chatbot_conversations(session_id);
CREATE INDEX idx_conversations_created ON chatbot_conversations(created_at);

-- Migration: Add image_path column if not exists (for backward compatibility)
SET @dbname = DATABASE();
SET @tablename = 'medicines';
SET @columnname = 'image_path';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(255) AFTER description;')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
