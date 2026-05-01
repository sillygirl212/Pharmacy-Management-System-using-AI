<?php
/**
 * AI Chatbot Configuration and Core Functions
 * Supports Gemini API and OpenAI (ChatGPT) API
 */

require_once 'functions.php';

class PharmacyAIChatbot {
    private $db;
    private $ai_config;
    private $session_id;
    private $user_id;
    
    public function __construct($user_id = null, $session_id = null) {
        $this->db = getDB();
        $this->user_id = $user_id;
        $this->session_id = $session_id ?: $this->generateSessionId();
        $this->loadAIConfig();
    }
    
    private function generateSessionId() {
        return 'chat_' . uniqid() . '_' . time();
    }
    
    private function loadAIConfig() {
        $stmt = $this->db->prepare("SELECT * FROM ai_config WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $this->ai_config = $stmt->fetch();
        
        if (!$this->ai_config) {
            // Fallback to default config
            $this->ai_config = [
                'provider' => 'gemini',
                'api_key' => '',
                'api_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent',
                'model_name' => 'gemini-pro'
            ];
        }
    }
    
    /**
     * Main chat processing function
     */
    public function processMessage($message) {
        // Save user message
        $this->saveMessage($message, 'user');
        
        // Check for intent in knowledge base first
        $intent = $this->detectIntent($message);
        
        // If specific intent found with high confidence, use rule-based response
        if ($intent && $intent['confidence'] > 0.8 && !$intent['requires_api']) {
            $response = $this->processIntent($intent, $message);
        } else {
            // Use AI API for complex queries
            $response = $this->callAIAPI($message, $intent);
        }
        
        // Save bot response
        $this->saveMessage($response, 'bot', $intent);
        
        return [
            'response' => $response,
            'intent' => $intent['category'] ?? 'general',
            'session_id' => $this->session_id
        ];
    }
    
    /**
     * Detect intent from user message
     */
    private function detectIntent($message) {
        $message_lower = strtolower($message);
        
        // Get knowledge base patterns
        $stmt = $this->db->prepare("SELECT * FROM chatbot_knowledge WHERE is_active = 1 ORDER BY priority DESC");
        $stmt->execute();
        $patterns = $stmt->fetchAll();
        
        $best_match = null;
        $highest_confidence = 0;
        
        foreach ($patterns as $pattern) {
            $confidence = $this->calculateSimilarity($message_lower, strtolower($pattern['question_pattern']));
            
            if ($confidence > $highest_confidence) {
                $highest_confidence = $confidence;
                $best_match = $pattern;
            }
        }
        
        if ($best_match) {
            return [
                'category' => $best_match['category'],
                'pattern' => $best_match['question_pattern'],
                'template' => $best_match['answer_template'],
                'requires_api' => $best_match['requires_api'],
                'confidence' => $highest_confidence
            ];
        }
        
        return null;
    }
    
    /**
     * Calculate string similarity
     */
    private function calculateSimilarity($str1, $str2) {
        similar_text($str1, $str2, $percent);
        return $percent / 100;
    }
    
    /**
     * Process detected intent
     */
    private function processIntent($intent, $message) {
        $response = $intent['template'];
        
        // Extract medicine name if present
        $medicine_name = $this->extractMedicineName($message);
        
        switch ($intent['category']) {
            case 'stock':
                if ($medicine_name) {
                    $response = $this->checkMedicineStock($medicine_name);
                } else {
                    $response = "Could you please specify the medicine name you'd like to check?";
                }
                break;
                
            case 'price':
                if ($medicine_name) {
                    $response = $this->getMedicinePrice($medicine_name);
                } else {
                    $response = "Please tell me which medicine's price you'd like to know.";
                }
                break;
                
            case 'report':
                if (isAdmin()) {
                    $response = $this->generateQuickReport($message);
                } else {
                    $response = "I'm sorry, I can only provide reports to authorized administrators.";
                }
                break;
        }
        
        return $response;
    }
    
    /**
     * Extract medicine name from message
     */
    private function extractMedicineName($message) {
        // Common patterns for medicine names
        $patterns = [
            '/is\s+([a-zA-Z\s]+)\s+available/i',
            '/do\s+you\s+have\s+([a-zA-Z\s]+)/i',
            '/price\s+of\s+([a-zA-Z\s]+)/i',
            '/how\s+much\s+is\s+([a-zA-Z\s]+)/i',
            '/([a-zA-Z\s]+)\s+stock/i',
            '/([a-zA-Z\s]+)\s+price/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Check medicine stock
     */
    private function checkMedicineStock($medicine_name) {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE (p.name LIKE ? OR p.slug LIKE ?) 
            AND p.status = 'active'
            LIMIT 1
        ");
        $search = "%{$medicine_name}%";
        $stmt->execute([$search, $search]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return "I'm sorry, I couldn't find '{$medicine_name}' in our inventory. Would you like to check a different medicine or speak with a pharmacist?";
        }
        
        $stock = $product['stock_quantity'];
        $product_name = $product['name'];
        
        if ($stock <= 0) {
            return "❌ **{$product_name}** is currently **OUT OF STOCK**.\n\nWould you like me to notify you when it becomes available? You can also contact our pharmacy at " . getSetting('site_phone') . " for alternatives.";
        } elseif ($stock < 10) {
            return "⚠️ **{$product_name}** is available but running **LOW** (only {$stock} units left).\n\nPrice: " . formatPrice($product['sale_price'] ?? $product['price']) . "\n\nI recommend ordering soon to avoid disappointment!";
        } else {
            return "✅ **{$product_name}** is **IN STOCK** ({$stock} units available).\n\nPrice: " . formatPrice($product['sale_price'] ?? $product['price']) . "\n\nYou can order now and get it delivered to your doorstep!";
        }
    }
    
    /**
     * Get medicine price
     */
    private function getMedicinePrice($medicine_name) {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE (p.name LIKE ? OR p.slug LIKE ?) 
            AND p.status = 'active'
            LIMIT 1
        ");
        $search = "%{$medicine_name}%";
        $stmt->execute([$search, $search]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return "I couldn't find '{$medicine_name}' in our database. Please check the spelling or ask about a different medicine.";
        }
        
        $price = $product['sale_price'] ?? $product['price'];
        $original_price = $product['sale_price'] ? $product['price'] : null;
        
        $response = "💊 **{$product['name']}**\n\n";
        $response .= "💰 Price: " . formatPrice($price);
        
        if ($original_price) {
            $discount = round((($original_price - $price) / $original_price) * 100);
            $response .= " ~~" . formatPrice($original_price) . "~~ ({$discount}% OFF)";
        }
        
        $response .= "\n📦 Stock: " . ($product['stock_quantity'] > 0 ? $product['stock_quantity'] . ' units' : 'Out of stock');
        
        if ($product['prescription_required']) {
            $response .= "\n⚕️ **Prescription Required** - You'll need a valid prescription to purchase this medicine.";
        }
        
        $response .= "\n\nWould you like to add this to your cart?";
        
        return $response;
    }
    
    /**
     * Generate quick report
     */
    private function generateQuickReport($message) {
        $today = date('Y-m-d');
        
        // Sales today
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue 
            FROM orders 
            WHERE DATE(created_at) = ? AND payment_status = 'paid'
        ");
        $stmt->execute([$today]);
        $today_sales = $stmt->fetch();
        
        // Low stock items
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM products 
            WHERE stock_quantity < 10 AND status = 'active'
        ");
        $low_stock = $stmt->fetch();
        
        // Out of stock
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM products 
            WHERE stock_quantity = 0 AND status = 'active'
        ");
        $out_of_stock = $stmt->fetch();
        
        $report = "📊 **Daily Quick Report**\n\n";
        $report .= "📅 Date: " . date('F d, Y') . "\n\n";
        $report .= "💰 **Today's Sales**\n";
        $report .= "   Orders: {$today_sales['orders']}\n";
        $report .= "   Revenue: " . formatPrice($today_sales['revenue']) . "\n\n";
        $report .= "📦 **Inventory Status**\n";
        $report .= "   Low Stock: {$low_stock['count']} items\n";
        $report .= "   Out of Stock: {$out_of_stock['count']} items\n\n";
        
        if ($low_stock['count'] > 0 || $out_of_stock['count'] > 0) {
            $report .= "⚠️ Attention needed! Check the Products section for restocking.";
        } else {
            $report .= "✅ All inventory levels are healthy!";
        }
        
        return $report;
    }
    
    /**
     * Call AI API (Gemini or OpenAI)
     */
    private function callAIAPI($message, $context = null) {
        if (empty($this->ai_config['api_key']) || $this->ai_config['api_key'] === 'YOUR_GEMINI_API_KEY_HERE') {
            return $this->getFallbackResponse($message);
        }
        
        $provider = $this->ai_config['provider'];
        
        if ($provider === 'gemini') {
            return $this->callGeminiAPI($message, $context);
        } elseif ($provider === 'openai') {
            return $this->callOpenAIAPI($message, $context);
        }
        
        return $this->getFallbackResponse($message);
    }
    
    /**
     * Call Google Gemini API
     */
    private function callGeminiAPI($message, $context) {
        $api_key = $this->ai_config['api_key'];
        $endpoint = $this->ai_config['api_endpoint'];
        
        // Build context-aware prompt
        $system_prompt = $this->buildSystemPrompt();
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $system_prompt . "\n\nUser: " . $message]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1000
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint . '?key=' . $api_key);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $this->cleanAIResponse($data['candidates'][0]['content']['parts'][0]['text']);
            }
        }
        
        return $this->getFallbackResponse($message);
    }
    
    /**
     * Call OpenAI API
     */
    private function callOpenAIAPI($message, $context) {
        $api_key = $this->ai_config['api_key'];
        $endpoint = $this->ai_config['api_endpoint'];
        
        $system_prompt = $this->buildSystemPrompt();
        
        $payload = [
            'model' => $this->ai_config['model_name'] ?? 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $message]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1000
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['choices'][0]['message']['content'])) {
                return $this->cleanAIResponse($data['choices'][0]['message']['content']);
            }
        }
        
        return $this->getFallbackResponse($message);
    }
    
    /**
     * Build system prompt with pharmacy context
     */
    private function buildSystemPrompt() {
        $site_name = getSetting('site_name', 'Pharmacy');
        $phone = getSetting('site_phone', '');
        $address = getSetting('site_address', '');
        
        return "You are an AI assistant for {$site_name}. You help customers with:\n" .
               "- Medicine availability and stock information\n" .
               "- Medicine prices and details\n" .
               "- Order status and delivery information\n" .
               "- General pharmacy questions\n" .
               "- Connecting customers with pharmacists when needed\n\n" .
               "Pharmacy Contact: {$phone}\n" .
               "Address: {$address}\n\n" .
               "Important:\n" .
               "- Always be professional, helpful, and empathetic\n" .
               "- Never provide medical advice - always refer to pharmacists or doctors\n" .
               "- Keep responses concise but informative\n" .
               "- Use clear formatting with emojis where appropriate\n" .
               "- If you don't know something, say so honestly\n" .
               "- For emergencies, advise calling emergency services immediately\n";
    }
    
    /**
     * Clean AI response
     */
    private function cleanAIResponse($response) {
        // Remove any markdown that might not render well
        $response = preg_replace('/```[\s\S]*?```/', '', $response);
        return trim($response);
    }
    
    /**
     * Get fallback response when AI is unavailable
     */
    private function getFallbackResponse($message) {
        $fallbacks = [
            'stock' => "I can help you check medicine availability. Please provide the exact medicine name.",
            'price' => "I can help you find medicine prices. What medicine are you looking for?",
            'general' => "Thank you for your message. For detailed assistance, please contact our pharmacy at " . getSetting('site_phone') . " or speak with a pharmacist."
        ];
        
        return $fallbacks['general'];
    }
    
    /**
     * Save conversation message
     */
    private function saveMessage($message, $sender, $intent = null) {
        $stmt = $this->db->prepare("
            INSERT INTO chatbot_conversations 
            (user_id, session_id, user_message, bot_response, intent, confidence, context_data) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $user_msg = $sender === 'user' ? $message : '';
        $bot_response = $sender === 'bot' ? $message : '';
        
        $stmt->execute([
            $this->user_id,
            $this->session_id,
            $user_msg,
            $bot_response,
            $intent['category'] ?? null,
            $intent['confidence'] ?? null,
            json_encode($intent)
        ]);
    }
    
    /**
     * Get chat history
     */
    public function getChatHistory($limit = 50) {
        $stmt = $this->db->prepare("
            SELECT * FROM chatbot_conversations 
            WHERE session_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$this->session_id, $limit]);
        return array_reverse($stmt->fetchAll());
    }
    
    /**
     * Check stock alerts and send notifications
     */
    public function checkStockAlerts() {
        // Find low stock products
        $stmt = $this->db->query("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.stock_quantity < 10 AND p.status = 'active'
        ");
        $low_stock = $stmt->fetchAll();
        
        $alerts = [];
        
        foreach ($low_stock as $product) {
            // Check if alert already exists and not resolved
            $stmt = $this->db->prepare("
                SELECT id FROM stock_alerts 
                WHERE product_id = ? AND alert_type = 'low_stock' AND resolved_at IS NULL
            ");
            $stmt->execute([$product['id']]);
            
            if (!$stmt->fetch()) {
                // Create new alert
                $stmt = $this->db->prepare("
                    INSERT INTO stock_alerts (product_id, alert_type, threshold_value, is_triggered) 
                    VALUES (?, 'low_stock', ?, 1)
                ");
                $stmt->execute([$product['id'], $product['stock_quantity']]);
                
                $alerts[] = [
                    'product' => $product['name'],
                    'stock' => $product['stock_quantity'],
                    'alert_id' => $this->db->lastInsertId()
                ];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get analytics for admin dashboard
     */
    public function getAnalytics($days = 7) {
        // Total conversations
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT session_id) as total_sessions,
                   COUNT(*) as total_messages
            FROM chatbot_conversations 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $conversations = $stmt->fetch();
        
        // Top intents
        $stmt = $this->db->prepare("
            SELECT intent, COUNT(*) as count 
            FROM chatbot_conversations 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
            AND intent IS NOT NULL
            GROUP BY intent 
            ORDER BY count DESC 
            LIMIT 5
        ");
        $stmt->execute([$days]);
        $intents = $stmt->fetchAll();
        
        return [
            'sessions' => $conversations['total_sessions'],
            'messages' => $conversations['total_messages'],
            'top_intents' => $intents
        ];
    }
}
?>
