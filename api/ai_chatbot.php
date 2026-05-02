<?php
/**
 * AI Chatbot API Endpoint
 * Handles chatbot conversations and AI integration
 */

require_once '../config.php';

header('Content-Type: application/json');

// Get database connection
$conn = getDBConnection();

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['message'])) {
    sendErrorResponse('Message is required');
}

$message = trim($data['message']);
$session_id = $data['session_id'] ?? session_id() ?? uniqid('chat_', true);
$user_id = $data['user_id'] ?? null;
$customer_id = $data['customer_id'] ?? null;

// Get AI configuration
$ai_config = getActiveAIConfig($conn);

// Detect intent from message
$intent = detectIntent($conn, $message);

// Generate response
$response_data = generateResponse($conn, $message, $intent, $ai_config);

// Save conversation to database
saveConversation($conn, $session_id, $user_id, $customer_id, $message, $response_data['response'], $intent, $response_data['source']);

// Return response
sendSuccessResponse([
    'response' => $response_data['response'],
    'intent' => $intent,
    'source' => $response_data['source'],
    'session_id' => $session_id,
    'data' => $response_data['data'] ?? null
]);

/**
 * Get active AI configuration
 */
function getActiveAIConfig($conn) {
    $sql = "SELECT * FROM ai_config WHERE is_active = 1 LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    // Return default Gemini config
    return [
        'provider' => 'gemini',
        'api_key' => 'AIzaSyCvnGQ1fTHV9_qykwwGxelpcKrN_exiIUg',
        'api_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent',
        'model_name' => 'gemini-pro',
        'settings' => '{"temperature": 0.7, "max_tokens": 1000}'
    ];
}

/**
 * Detect intent from user message
 */
function detectIntent($conn, $message) {
    $message_lower = strtolower($message);
    
    // FIRST: Check if message contains any medicine name from database
    $medicine_keywords = ['napa', 'seclo', 'monas', 'flora', 'biotin', 'metformin', 'amlo', 'paracetamol', 'omeprazole', 
                         'montelukast', 'loratadine', 'caffeine', 'thermometer', 'blood pressure', 'medicine', 'drug', 'tablet', 'capsule'];
    
    $has_medicine_name = false;
    foreach ($medicine_keywords as $med) {
        if (strpos($message_lower, $med) !== false) {
            $has_medicine_name = true;
            break;
        }
    }
    
    // If medicine name found, determine specific intent
    if ($has_medicine_name) {
        // Check for side effects, dosage, warnings keywords
        if (strpos($message_lower, 'side effect') !== false || 
            strpos($message_lower, 'dosage') !== false || 
            strpos($message_lower, 'how to use') !== false ||
            strpos($message_lower, 'warning') !== false ||
            strpos($message_lower, 'precaution') !== false) {
            return [
                'intent' => 'medicine_details',
                'response' => 'Here is the detailed information about the medicine.',
                'requires_data' => true,
                'data_source' => 'medicine_details'
            ];
        }
        
        // Check for price keywords
        if (strpos($message_lower, 'price') !== false || 
            strpos($message_lower, 'cost') !== false || 
            strpos($message_lower, 'how much') !== false ||
            strpos($message_lower, 'tk') !== false ||
            strpos($message_lower, 'taka') !== false ||
            strpos($message_lower, '৳') !== false) {
            return [
                'intent' => 'medicine_price',
                'response' => 'Here is the price information.',
                'requires_data' => true,
                'data_source' => 'medicines'
            ];
        }
        
        // Check for availability/stock keywords
        if (strpos($message_lower, 'available') !== false || 
            strpos($message_lower, 'stock') !== false || 
            strpos($message_lower, 'have') !== false ||
            strpos($message_lower, 'left') !== false ||
            strpos($message_lower, 'quantity') !== false) {
            return [
                'intent' => 'medicine_availability',
                'response' => 'Let me check the availability for you.',
                'requires_data' => true,
                'data_source' => 'medicines'
            ];
        }
        
        // Default to availability if medicine name mentioned without specific intent
        return [
            'intent' => 'medicine_availability',
            'response' => 'Here is the information about this medicine.',
            'requires_data' => true,
            'data_source' => 'medicines'
        ];
    }
    
    // Get all active knowledge base entries for other intents
    $sql = "SELECT * FROM chatbot_knowledge WHERE is_active = 1 ORDER BY priority ASC";
    $result = $conn->query($sql);
    
    $best_match = null;
    $best_score = 0;
    
    while ($row = $result->fetch_assoc()) {
        $keywords = explode(',', $row['keywords']);
        $score = 0;
        
        foreach ($keywords as $keyword) {
            $keyword = trim(strtolower($keyword));
            if (empty($keyword)) continue;
            
            // Exact match
            if ($message_lower === $keyword) {
                $score += 10;
            }
            // Contains keyword
            elseif (strpos($message_lower, $keyword) !== false) {
                $score += 5;
            }
            // Fuzzy match (keyword contains message word)
            elseif (strpos($keyword, $message_lower) !== false) {
                $score += 3;
            }
        }
        
        if ($score > $best_score) {
            $best_score = $score;
            $best_match = $row;
        }
    }
    
    // Return intent if score is high enough
    if ($best_match && $best_score >= 3) {
        return $best_match;
    }
    
    // Default to generic response
    return [
        'intent' => 'generic',
        'response' => 'I can help you with:\n- Medicine availability & prices (e.g., "Is Napa available?")\n- Medicine details & side effects (e.g., "Napa side effects")\n- Stock reports and sales info\n- Order status and delivery\n\nWhat would you like to know?',
        'requires_data' => false,
        'data_source' => null
    ];
}

/**
 * Generate response based on intent - FAST database-first approach
 */
function generateResponse($conn, $message, $intent, $ai_config) {
    $response = '';
    $source = 'rule_based';
    $data = null;
    
    // Check if intent requires database data
    if ($intent['requires_data'] && $intent['data_source']) {
        $data = fetchDataFromSource($conn, $intent['data_source'], $message);
        
        // Use database response directly (FAST - no Gemini delay)
        if ($data && !empty($data)) {
            $response = formatDataResponse($intent['intent'], $data);
            $source = 'hybrid';
        }
    }
    
    // If no specific data found, use quick predefined responses
    if (empty($response)) {
        if ($intent['intent'] !== 'generic') {
            $response = $intent['response'];
            $source = 'rule_based';
        } else {
            // Quick fallback response (no slow AI call)
            $response = 'I can help you with:\n💊 Medicines: "Is Napa available?"\n💰 Prices: "Napa price"\n⚠️ Details: "Napa side effects"\n📦 Stock: "Show stock report"\n💳 Payment: "bKash payment"\n\nWhat would you like to know?';
            $source = 'rule_based';
        }
    }
    
    return [
        'response' => $response,
        'source' => $source,
        'data' => $data
    ];
}

/**
 * Extract medicine name from user message
 */
function extractMedicineName($message) {
    $message_lower = strtolower($message);
    
    // Common words to remove
    $common_words = [
        'is', 'available', 'price', 'cost', 'how', 'much', 'what', 'the', 'of', 'for',
        'are', 'there', 'any', 'do', 'you', 'have', 'get', 'can', 'i', 'buy', 'order',
        'about', 'tell', 'me', 'show', 'check', 'stock', 'quantity', 'left', 'in',
        'side', 'effects', 'dosage', 'warning', 'precaution', 'use', 'details',
        'information', 'info', 'where', 'find', 'need', 'want', 'like', 'would',
        'please', 'could', 'can', 'will', 'should', 'does', 'it', 'have', 'has',
        '?', '.', '!', ',', '(', ')'
    ];
    
    // Known medicine names to check for (from our database)
    $known_medicines = ['napa', 'seclo', 'monas', 'flora', 'biotin', 'metformin', 'amlo', 
                        'amloc', 'paracetamol', 'omeprazole', 'montelukast', 'loratadine', 
                        'caffeine', 'thermometer', 'blood pressure', 'monitor'];
    
    // First check if any known medicine name is in the message
    foreach ($known_medicines as $med) {
        if (strpos($message_lower, $med) !== false) {
            return $med;
        }
    }
    
    // If no known medicine found, remove common words and return the longest remaining word
    $words = explode(' ', $message_lower);
    $filtered_words = [];
    
    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word) && !in_array($word, $common_words) && strlen($word) > 2) {
            $filtered_words[] = $word;
        }
    }
    
    // Return the longest word as it's likely the medicine name
    if (!empty($filtered_words)) {
        usort($filtered_words, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        return $filtered_words[0];
    }
    
    // Fallback: return original message if nothing found
    return $message;
}

/**
 * Fetch data from database based on source
 */
function fetchDataFromSource($conn, $source, $message) {
    $message_lower = strtolower($message);
    $data = [];
    
    switch ($source) {
        case 'medicines':
            // Extract medicine name from message - remove common words
            $medicine_name = extractMedicineName($message);
            $search_term = '%' . $medicine_name . '%';
            $sql = "SELECT m.*, c.name as category_name 
                    FROM medicines m 
                    LEFT JOIN categories c ON m.category_id = c.id 
                    WHERE (m.name LIKE ? OR m.generic_name LIKE ? OR m.medicine_code LIKE ?)
                    AND m.is_active = 1
                    LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $search_term, $search_term, $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'stock_report':
            // Get stock report
            $sql = "SELECT m.name, m.medicine_code, m.stock_quantity, m.reorder_level,
                    CASE 
                        WHEN m.stock_quantity = 0 THEN 'out_of_stock'
                        WHEN m.stock_quantity < m.reorder_level THEN 'low_stock'
                        ELSE 'normal'
                    END as stock_status
                    FROM medicines m
                    WHERE m.is_active = 1
                    ORDER BY m.stock_quantity ASC
                    LIMIT 10";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'sales':
            // Get sales summary
            $today = date('Y-m-d');
            $this_month = date('Y-m-01');
            
            $sql = "SELECT 
                    (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(sale_date) = ? AND payment_status = 'completed') as today_sales,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE sale_date >= ? AND payment_status = 'completed') as month_sales,
                    (SELECT COUNT(*) FROM sales WHERE DATE(sale_date) = ? AND payment_status = 'completed') as today_orders";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $today, $this_month, $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            break;
            
        case 'orders':
            // Search for orders
            $search_term = '%' . $message . '%';
            $sql = "SELECT s.*, c.first_name, c.last_name, c.email, c.phone
                    FROM sales s
                    LEFT JOIN customers c ON s.customer_id = c.id
                    WHERE s.invoice_number LIKE ? 
                    OR c.email LIKE ? 
                    OR c.phone LIKE ?
                    ORDER BY s.sale_date DESC
                    LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $search_term, $search_term, $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'medicine_details':
            // Get detailed medicine info including side effects, dosage
            $medicine_name = extractMedicineName($message);
            $search_term = '%' . $medicine_name . '%';
            $sql = "SELECT m.*, c.name as category_name 
                    FROM medicines m 
                    LEFT JOIN categories c ON m.category_id = c.id 
                    WHERE (m.name LIKE ? OR m.generic_name LIKE ? OR m.medicine_code LIKE ?)
                    AND m.is_active = 1
                    LIMIT 3";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $search_term, $search_term, $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'total_stock':
            // Get total stock summary
            $sql = "SELECT 
                    COUNT(*) as total_medicines,
                    SUM(stock_quantity) as total_units,
                    SUM(stock_quantity * unit_price) as stock_value,
                    COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
                    COUNT(CASE WHEN stock_quantity < reorder_level AND stock_quantity > 0 THEN 1 END) as low_stock
                    FROM medicines WHERE is_active = 1";
            $result = $conn->query($sql);
            $data = $result->fetch_assoc();
            break;
            
        case 'low_stock':
            // Get low stock and reorder suggestions
            $sql = "SELECT name, medicine_code, stock_quantity, reorder_level,
                    (reorder_level - stock_quantity) as needed_quantity
                    FROM medicines 
                    WHERE is_active = 1 AND stock_quantity < reorder_level
                    ORDER BY (stock_quantity / reorder_level) ASC
                    LIMIT 15";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'expiry':
            // Get medicines expiring soon (next 30 days)
            $sql = "SELECT name, medicine_code, batch_number, expiry_date,
                    DATEDIFF(expiry_date, CURDATE()) as days_until_expiry,
                    stock_quantity
                    FROM medicines 
                    WHERE is_active = 1 
                    AND expiry_date IS NOT NULL
                    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    AND expiry_date >= CURDATE()
                    ORDER BY expiry_date ASC
                    LIMIT 15";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
    }
    
    return $data;
}

/**
 * Format data into readable response
 */
function formatDataResponse($intent, $data) {
    $response = '';
    
    switch ($intent) {
        case 'medicine_availability':
            if (empty($data)) {
                $response = "I couldn't find any medicines matching your search. Please check the spelling or try a different name.";
            } else {
                $response = "Here are the medicines I found:\n\n";
                foreach ($data as $medicine) {
                    $status = $medicine['stock_quantity'] > 0 ? 
                        "✅ In Stock ({$medicine['stock_quantity']} units)" : 
                        "❌ Out of Stock";
                    $response .= "📦 {$medicine['name']}\n";
                    $response .= "   Code: {$medicine['medicine_code']}\n";
                    $response .= "   Price: ৳{$medicine['unit_price']}\n";
                    $response .= "   Status: {$status}\n\n";
                }
            }
            break;
            
        case 'medicine_price':
            if (empty($data)) {
                $response = "I couldn't find pricing information for that medicine. Please check the name and try again.";
            } else {
                $response = "Here are the prices:\n\n";
                foreach ($data as $medicine) {
                    $response .= "💊 {$medicine['name']}\n";
                    $response .= "   Price: ৳{$medicine['unit_price']}\n";
                    if ($medicine['cost_price'] && $medicine['cost_price'] < $medicine['unit_price']) {
                        $profit = $medicine['unit_price'] - $medicine['cost_price'];
                        $response .= "   Profit: ৳{$profit}\n";
                    }
                    $response .= "\n";
                }
            }
            break;
            
        case 'stock_report':
            if (empty($data)) {
                $response = "📊 Stock Report:\n\nNo stock data available.";
            } else {
                $low_stock = [];
                $out_of_stock = [];
                $normal = [];
                
                foreach ($data as $item) {
                    if ($item['stock_status'] === 'out_of_stock') {
                        $out_of_stock[] = $item;
                    } elseif ($item['stock_status'] === 'low_stock') {
                        $low_stock[] = $item;
                    } else {
                        $normal[] = $item;
                    }
                }
                
                $response = "📊 Stock Status Report:\n\n";
                
                if (!empty($out_of_stock)) {
                    $response .= "❌ OUT OF STOCK:\n";
                    foreach ($out_of_stock as $item) {
                        $response .= "   • {$item['name']} ({$item['medicine_code']})\n";
                    }
                    $response .= "\n";
                }
                
                if (!empty($low_stock)) {
                    $response .= "⚠️ LOW STOCK:\n";
                    foreach ($low_stock as $item) {
                        $response .= "   • {$item['name']} - {$item['stock_quantity']} left\n";
                    }
                    $response .= "\n";
                }
                
                $response .= "✅ Normal Stock: " . count($normal) . " medicines\n";
                $response .= "⚠️ Low Stock: " . count($low_stock) . " medicines\n";
                $response .= "❌ Out of Stock: " . count($out_of_stock) . " medicines";
            }
            break;
            
        case 'sales_report':
            if (is_array($data)) {
                $response = "📈 Sales Report:\n\n";
                $response .= "Today's Sales: ৳" . number_format($data['today_sales'], 2) . "\n";
                $response .= "Today's Orders: " . $data['today_orders'] . "\n";
                $response .= "This Month: ৳" . number_format($data['month_sales'], 2) . "\n";
            }
            break;
            
        case 'medicine_details':
            if (empty($data)) {
                $response = "I couldn't find detailed information for that medicine. Please check the name and try again.";
            } else {
                foreach ($data as $medicine) {
                    $response .= "💊 {$medicine['name']}\n\n";
                    $response .= "📋 Description:\n{$medicine['description']}\n\n";
                    
                    if (!empty($medicine['side_effects'])) {
                        $response .= "⚠️ Side Effects:\n{$medicine['side_effects']}\n\n";
                    }
                    
                    if (!empty($medicine['dosage'])) {
                        $response .= "💉 Dosage:\n{$medicine['dosage']}\n\n";
                    }
                    
                    if (!empty($medicine['warnings'])) {
                        $response .= "🚨 Warnings:\n{$medicine['warnings']}\n\n";
                    }
                    
                    $response .= "💰 Price: ৳{$medicine['unit_price']}\n";
                    $response .= "📦 Stock: {$medicine['stock_quantity']} units available\n";
                    $response .= "---\n\n";
                }
            }
            break;
            
        case 'total_stock':
            if (is_array($data)) {
                $response = "📊 TOTAL STOCK OVERVIEW\n\n";
                $response .= "📦 Total Medicines: " . number_format($data['total_medicines']) . "\n";
                $response .= "📊 Total Units: " . number_format($data['total_units']) . "\n";
                $response .= "💰 Stock Value: ৳" . number_format($data['stock_value'], 2) . "\n";
                $response .= "❌ Out of Stock: " . $data['out_of_stock'] . "\n";
                $response .= "⚠️ Low Stock: " . $data['low_stock'] . "\n\n";
                $response .= "Use 'low stock' to see reorder suggestions.";
            }
            break;
            
        case 'low_stock':
            if (empty($data)) {
                $response = "✅ Great! No medicines need reordering. All stocks are at healthy levels.";
            } else {
                $response = "⚠️ LOW STOCK ALERT - Reorder Needed:\n\n";
                foreach ($data as $item) {
                    $response .= "📦 {$item['name']} ({$item['medicine_code']})\n";
                    $response .= "   Current: {$item['stock_quantity']} | Reorder: {$item['reorder_level']}\n";
                    $response .= "   🔴 Need: {$item['needed_quantity']} units\n\n";
                }
                $response .= "Contact suppliers to restock these items.";
            }
            break;
            
        case 'expiry':
            if (empty($data)) {
                $response = "✅ No medicines expiring in the next 30 days. All good!";
            } else {
                $response = "⏰ EXPIRY ALERT - Next 30 Days:\n\n";
                foreach ($data as $item) {
                    $days = $item['days_until_expiry'];
                    $emoji = $days <= 7 ? '🔴' : ($days <= 14 ? '🟡' : '🟢');
                    $response .= "{$emoji} {$item['name']} ({$item['medicine_code']})\n";
                    $response .= "   Expires: {$item['expiry_date']} ({$days} days)\n";
                    $response .= "   Stock: {$item['stock_quantity']} units\n\n";
                }
                $response .= "⚠️ Red = Urgent (≤7 days), Yellow = Soon (≤14 days), Green = Later (≤30 days)";
            }
            break;
            
        default:
            $response = "Here's what I found:\n" . json_encode($data, JSON_PRETTY_PRINT);
    }
    
    return $response;
}

/**
 * Get enhanced AI response from Gemini with database context
 */
function getEnhancedAIResponse($message, $raw_data, $intent, $ai_config) {
    if ($ai_config['provider'] !== 'gemini' || empty($ai_config['api_key'])) {
        return null;
    }
    
    $api_key = $ai_config['api_key'];
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$api_key}";
    
    // Create context-aware prompt based on intent
    $context_prompt = "";
    switch ($intent) {
        case 'medicine_availability':
            $context_prompt = "You are a friendly pharmacy assistant. A customer asked about medicine availability. 
                             Use this accurate data from our database to answer:\n{$raw_data}\n\n
                             Please provide a helpful, conversational response in Bengali/English mix as Bangladeshi pharmacies do.
                             Keep it under 150 words. Be warm and professional.";
            break;
            
        case 'medicine_price':
            $context_prompt = "You are a helpful pharmacy assistant. A customer asked about medicine prices.
                             Use this pricing data from our database:\n{$raw_data}\n\n
                             Provide a clear response with prices. Mention that prices are in Bangladeshi Taka (৳).
                             Keep it under 100 words. Be friendly and professional.";
            break;
            
        case 'medicine_details':
            $context_prompt = "You are a knowledgeable pharmacy assistant. A customer asked about medicine details.
                             Use this detailed information from our database:\n{$raw_data}\n\n
                             Present this information in a well-organized, easy-to-read format.
                             Use emojis for sections. Be helpful and clear. Keep it under 200 words.";
            break;
            
        case 'low_stock':
            $context_prompt = "You are a pharmacy inventory manager. Someone asked about low stock items.
                             Use this data:\n{$raw_data}\n\n
                             Provide a clear summary and suggest reordering. Be professional and action-oriented.";
            break;
            
        case 'expiry':
            $context_prompt = "You are a pharmacy inventory manager. Someone asked about expiring medicines.
                             Use this data:\n{$raw_data}\n\n
                             Highlight urgent items and provide clear dates. Be professional.";
            break;
            
        default:
            $context_prompt = "You are a helpful pharmacy assistant for a Bangladeshi pharmacy.
                             The customer asked: '{$message}'
                             Use this information: {$raw_data}
                             Provide a helpful response under 150 words.";
    }
    
    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $context_prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 800,
            'topP' => 0.8,
            'topK' => 40
        ]
    ];
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($data['candidates'][0]['content']['parts'][0]['text']);
        }
    }
    
    // Log error for debugging
    error_log("Gemini API Error: HTTP {$http_code}, Response: {$response}, Curl Error: {$curl_error}");
    
    return null;
}

/**
 * Get AI response from Gemini API for general questions
 */
function getAIResponse($message, $ai_config) {
    if ($ai_config['provider'] !== 'gemini' || empty($ai_config['api_key'])) {
        return null;
    }
    
    $api_key = $ai_config['api_key'];
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$api_key}";
    
    $prompt = "You are a helpful, friendly pharmacy assistant for a Bangladeshi pharmacy called PharmaCare.
    
               IMPORTANT RULES:
               1. Answer questions about medicines, health, and pharmacy services professionally
               2. For medical advice, always suggest consulting a doctor
               3. Mention that we accept bKash, Nagad, and Cash on Delivery
               4. Keep responses under 150 words
               5. Be warm and conversational like a helpful Bangladeshi pharmacist
               6. Use occasional Banglish (Bangla + English) phrases naturally if appropriate
               7. If unsure, suggest visiting the pharmacy or calling +8801644076419
               
               Customer message: {$message}";
    
    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.6,
            'maxOutputTokens' => 800,
            'topP' => 0.9,
            'topK' => 40
        ]
    ];
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($data['candidates'][0]['content']['parts'][0]['text']);
        }
    }
    
    return null;
}

/**
 * Save conversation to database
 */
function saveConversation($conn, $session_id, $user_id, $customer_id, $message, $response, $intent, $source) {
    $sql = "INSERT INTO chatbot_conversations (session_id, user_id, customer_id, message, response, intent, source) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $intent_name = is_array($intent) ? ($intent['intent'] ?? 'unknown') : 'unknown';
    $stmt->bind_param("siissss", $session_id, $user_id, $customer_id, $message, $response, $intent_name, $source);
    $stmt->execute();
}
