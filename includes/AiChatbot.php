<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

class AiChatbot {
    private $conn;
    private $userId;
    private $sessionId;
    
    public function __construct($userId = null, $sessionId = null) {
        global $conn;
        $this->conn = $conn;
        $this->userId = $userId ?: get_user_id();
        $this->sessionId = $sessionId ?: session_id();
    }
    
    /**
     * Process user message and generate AI response
     */
    public function processMessage($message) {
        $startTime = microtime(true);
        
        // Log user message
        $this->logMessage($message, 'user');
        
        // Analyze intent and extract entities
        $intent = $this->analyzeIntent($message);
        $entities = $this->extractEntities($message);
        
        // Generate response based on intent
        $response = $this->generateResponse($intent, $entities, $message);
        
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        // Log bot response
        $this->logMessage($response, 'bot', $intent, $responseTime);
        
        return [
            'response' => $response,
            'intent' => $intent,
            'confidence' => $this->getIntentConfidence($intent, $message),
            'response_time' => $responseTime
        ];
    }
    
    /**
     * Analyze user intent from message
     */
    private function analyzeIntent($message) {
        $message = strtolower(trim($message));
        
        // Define intent patterns
        $intents = [
            'product_search' => [
                'keywords' => ['search', 'find', 'looking for', 'show me', 'product', 'item'],
                'patterns' => ['/search for (.+)/i', '/find (.+)/i', '/show me (.+)/i']
            ],
            'product_info' => [
                'keywords' => ['details', 'information', 'specifications', 'features', 'about'],
                'patterns' => ['/tell me about (.+)/i', '/what is (.+)/i', '/details of (.+)/i']
            ],
            'order_status' => [
                'keywords' => ['order', 'tracking', 'status', 'delivery', 'shipment'],
                'patterns' => ['/order status/i', '/track order/i', '/where is my order/i']
            ],
            'pricing' => [
                'keywords' => ['price', 'cost', 'how much', 'discount', 'offer', 'deal'],
                'patterns' => ['/how much is (.+)/i', '/price of (.+)/i', '/cost of (.+)/i']
            ],
            'support' => [
                'keywords' => ['help', 'support', 'contact', 'issue', 'problem', 'complaint'],
                'patterns' => ['/help me/i', '/i need help/i', '/contact support/i']
            ],
            'account' => [
                'keywords' => ['account', 'profile', 'login', 'register', 'password'],
                'patterns' => ['/my account/i', '/profile/i', '/login issue/i']
            ],
            'payment' => [
                'keywords' => ['payment', 'checkout', 'credit card', 'paypal', 'razorpay'],
                'patterns' => ['/payment methods/i', '/how to pay/i', '/checkout process/i']
            ],
            'shipping' => [
                'keywords' => ['shipping', 'delivery', 'shipping cost', 'delivery time'],
                'patterns' => ['/shipping info/i', '/delivery time/i', '/shipping cost/i']
            ],
            'returns' => [
                'keywords' => ['return', 'refund', 'exchange', 'money back'],
                'patterns' => ['/return policy/i', '/how to return/i', '/refund process/i']
            ],
            'greeting' => [
                'keywords' => ['hello', 'hi', 'hey', 'good morning', 'good evening'],
                'patterns' => ['/^(hello|hi|hey)/i', '/good (morning|evening|afternoon)/i']
            ],
            'goodbye' => [
                'keywords' => ['bye', 'goodbye', 'see you', 'thanks', 'thank you'],
                'patterns' => ['/^(bye|goodbye)/i', '/see you/i', '/thank(s| you)/i']
            ]
        ];
        
        // Check patterns first (more specific)
        foreach ($intents as $intentName => $intentData) {
            foreach ($intentData['patterns'] as $pattern) {
                if (preg_match($pattern, $message)) {
                    return $intentName;
                }
            }
        }
        
        // Check keywords
        foreach ($intents as $intentName => $intentData) {
            foreach ($intentData['keywords'] as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    return $intentName;
                }
            }
        }
        
        return 'general_query';
    }
    
    /**
     * Extract entities from message
     */
    private function extractEntities($message) {
        $entities = [];
        
        // Extract product names (basic pattern matching)
        if (preg_match_all('/(?:search|find|show me|tell me about|what is|details of)\s+(.+?)(?:\?|$)/i', $message, $matches)) {
            $entities['product_query'] = trim($matches[1][0]);
        }
        
        // Extract order numbers
        if (preg_match('/order\s*#?(\d+)/i', $message, $matches)) {
            $entities['order_id'] = $matches[1];
        }
        
        // Extract price ranges
        if (preg_match('/(?:under|below|less than)\s*\$?(\d+)/i', $message, $matches)) {
            $entities['max_price'] = $matches[1];
        }
        if (preg_match('/(?:over|above|more than)\s*\$?(\d+)/i', $message, $matches)) {
            $entities['min_price'] = $matches[1];
        }
        
        return $entities;
    }
    
    /**
     * Generate response based on intent
     */
    private function generateResponse($intent, $entities, $originalMessage) {
        switch ($intent) {
            case 'product_search':
                return $this->handleProductSearch($entities);
                
            case 'product_info':
                return $this->handleProductInfo($entities);
                
            case 'order_status':
                return $this->handleOrderStatus($entities);
                
            case 'pricing':
                return $this->handlePricing($entities);
                
            case 'support':
                return $this->handleSupport();
                
            case 'account':
                return $this->handleAccount();
                
            case 'payment':
                return $this->handlePayment();
                
            case 'shipping':
                return $this->handleShipping();
                
            case 'returns':
                return $this->handleReturns();
                
            case 'greeting':
                return $this->handleGreeting();
                
            case 'goodbye':
                return $this->handleGoodbye();
                
            default:
                return $this->handleGeneralQuery($originalMessage);
        }
    }
    
    /**
     * Handle product search queries
     */
    private function handleProductSearch($entities) {
        if (isset($entities['product_query'])) {
            $query = $entities['product_query'];
            
            // Search for products
            $stmt = mysqli_prepare($this->conn, "
                SELECT id, title, price, image, description
                FROM products 
                WHERE status = 'active' 
                AND (title LIKE ? OR description LIKE ?)
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $searchTerm = "%$query%";
            mysqli_stmt_bind_param($stmt, "ss", $searchTerm, $searchTerm);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                $response = "I found some products matching '$query':\n\n";
                while ($product = mysqli_fetch_assoc($result)) {
                    $response .= "🛍️ **{$product['title']}**\n";
                    $response .= "💰 Price: " . format_price($product['price']) . "\n";
                    $response .= "📝 " . substr(strip_tags($product['description']), 0, 100) . "...\n\n";
                }
                $response .= "Would you like more details about any of these products?";
            } else {
                $response = "I couldn't find any products matching '$query'. Would you like me to search for something else or show you our popular products?";
            }
        } else {
            $response = "I'd be happy to help you find products! What are you looking for? You can tell me about specific items, categories, or features you're interested in.";
        }
        
        return $response;
    }
    
    /**
     * Handle product information queries
     */
    private function handleProductInfo($entities) {
        if (isset($entities['product_query'])) {
            $query = $entities['product_query'];
            
            $stmt = mysqli_prepare($this->conn, "
                SELECT p.*, c.name as category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.status = 'active' 
                AND (p.title LIKE ? OR p.description LIKE ?)
                ORDER BY p.created_at DESC
                LIMIT 1
            ");
            $searchTerm = "%$query%";
            mysqli_stmt_bind_param($stmt, "ss", $searchTerm, $searchTerm);
            mysqli_stmt_execute($stmt);
            $product = mysqli_stmt_get_result($stmt)->fetch_assoc();
            
            if ($product) {
                $response = "📋 **Product Details: {$product['title']}**\n\n";
                $response .= "💰 **Price:** " . format_price($product['price']) . "\n";
                $response .= "📂 **Category:** {$product['category_name']}\n";
                $response .= "📝 **Description:** " . strip_tags($product['description']) . "\n\n";
                $response .= "Would you like to purchase this product or see similar items?";
            } else {
                $response = "I couldn't find specific information about '$query'. Let me help you search for it instead!";
            }
        } else {
            $response = "I can provide detailed information about any product! Just tell me which product you'd like to know more about.";
        }
        
        return $response;
    }
    
    /**
     * Handle order status queries
     */
    private function handleOrderStatus($entities) {
        if (!$this->userId) {
            return "To check your order status, please log in to your account first. Then I can help you track your orders!";
        }
        
        if (isset($entities['order_id'])) {
            $orderId = $entities['order_id'];
            
            $stmt = mysqli_prepare($this->conn, "
                SELECT * FROM orders 
                WHERE id = ? AND user_id = ?
            ");
            mysqli_stmt_bind_param($stmt, "ii", $orderId, $this->userId);
            mysqli_stmt_execute($stmt);
            $order = mysqli_stmt_get_result($stmt)->fetch_assoc();
            
            if ($order) {
                $response = "📦 **Order #{$order['id']} Status**\n\n";
                $response .= "📅 **Date:** " . date('F j, Y', strtotime($order['created_at'])) . "\n";
                $response .= "💰 **Total:** " . format_price($order['total_amount']) . "\n";
                $response .= "💳 **Payment Status:** " . ucfirst($order['payment_status']) . "\n";
                $response .= "🚚 **Order Status:** " . ucfirst($order['order_status']) . "\n";
                
                if ($order['transaction_id']) {
                    $response .= "🔗 **Transaction ID:** {$order['transaction_id']}\n";
                }
                
                $response .= "\nIs there anything specific about this order you'd like to know?";
            } else {
                $response = "I couldn't find order #$orderId in your account. Would you like me to show you your recent orders?";
            }
        } else {
            // Show recent orders
            $stmt = mysqli_prepare($this->conn, "
                SELECT id, total_amount, payment_status, order_status, created_at
                FROM orders 
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            mysqli_stmt_bind_param($stmt, "i", $this->userId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                $response = "📋 **Your Recent Orders:**\n\n";
                while ($order = mysqli_fetch_assoc($result)) {
                    $response .= "🛍️ **Order #{$order['id']}**\n";
                    $response .= "💰 " . format_price($order['total_amount']) . "\n";
                    $response .= "📊 Status: " . ucfirst($order['order_status']) . "\n";
                    $response .= "📅 " . date('M j, Y', strtotime($order['created_at'])) . "\n\n";
                }
                $response .= "Let me know if you'd like details about any specific order!";
            } else {
                $response = "You don't have any orders yet. Would you like to browse our products and place your first order?";
            }
        }
        
        return $response;
    }
    
    /**
     * Handle pricing queries
     */
    private function handlePricing($entities) {
        $response = "💰 **Pricing Information:**\n\n";
        $response .= "• All prices are clearly displayed on product pages\n";
        $response .= "• We offer secure payment options: Credit Card, PayPal, Razorpay\n";
        $response .= "• Discounts and coupon codes are available during checkout\n";
        $response .= "• Prices include all applicable taxes\n\n";
        
        if (isset($entities['product_query'])) {
            $response .= "For specific product pricing, please let me know which product you're interested in!";
        } else {
            $response .= "Would you like information about pricing for a specific product or category?";
        }
        
        return $response;
    }
    
    /**
     * Handle support queries
     */
    private function handleSupport() {
        $response = "🤝 **Customer Support Options:**\n\n";
        $response .= "💬 **Live Chat:** Available 24/7 (right here!)\n";
        $response .= "📧 **Email:** support@s3digital.com\n";
        $response .= "📞 **Phone:** +1-800-S3-DIGITAL\n";
        $response .= "📝 **Support Ticket:** Create a ticket in your account\n\n";
        $response .= "Common issues I can help with:\n";
        $response .= "• Order tracking and status\n";
        $response .= "• Product information and recommendations\n";
        $response .= "• Account and payment issues\n";
        $response .= "• Returns and refunds\n\n";
        $response .= "What specific issue can I help you with today?";
        
        return $response;
    }
    
    /**
     * Handle account queries
     */
    private function handleAccount() {
        if ($this->userId) {
            $response = "👤 **Your Account:**\n\n";
            $response .= "You're currently logged in! I can help you with:\n\n";
            $response .= "📋 **Profile Management:** Update your information\n";
            $response .= "🛍️ **Order History:** View past and current orders\n";
            $response .= "💳 **Payment Methods:** Manage payment options\n";
            $response .= "🔔 **Notifications:** Set preferences\n";
            $response .= "🔒 **Security:** Change password and settings\n\n";
            $response .= "What would you like to do with your account?";
        } else {
            $response = "🔐 **Account Access:**\n\n";
            $response .= "To access your account features, please:\n\n";
            $response .= "1. **Log In:** If you already have an account\n";
            $response .= "2. **Register:** Create a new account\n";
            $response .= "3. **Guest Checkout:** Continue without registration\n\n";
            $response .= "Account benefits include:\n";
            $response .= "• Order tracking\n";
            $response .= "• Faster checkout\n";
            $response .= "• Personalized recommendations\n";
            $response .= "• Exclusive offers\n\n";
            $response .= "Would you like help with logging in or creating an account?";
        }
        
        return $response;
    }
    
    /**
     * Handle payment queries
     */
    private function handlePayment() {
        $response = "💳 **Payment Information:**\n\n";
        $response .= "**Accepted Payment Methods:**\n";
        $response .= "• 💳 Credit/Debit Cards (Visa, Mastercard, Amex)\n";
        $response .= "• 🅿️ PayPal\n";
        $response .= "• 🚀 Razorpay\n";
        $response .= "• 💎 Digital Wallets\n\n";
        $response .= "**Security Features:**\n";
        $response .= "• 256-bit SSL encryption\n";
        $response .= "• PCI DSS compliant\n";
        $response .= "• Fraud protection\n";
        $response .= "• Secure checkout process\n\n";
        $response .= "**Payment Process:**\n";
        $response .= "1. Add items to cart\n";
        $response .= "2. Proceed to checkout\n";
        $response .= "3. Enter shipping details\n";
        $response .= "4. Select payment method\n";
        $response .= "5. Complete secure payment\n\n";
        $response .= "Have questions about a specific payment method?";
        
        return $response;
    }
    
    /**
     * Handle shipping queries
     */
    private function handleShipping() {
        $response = "🚚 **Shipping Information:**\n\n";
        $response .= "**Shipping Options:**\n";
        $response .= "• 📦 Standard Shipping (5-7 business days)\n";
        $response .= "• ⚡ Express Shipping (2-3 business days)\n";
        $response .= "• 🚀 Overnight Shipping (1 business day)\n\n";
        $response .= "**Shipping Costs:**\n";
        $response .= "• Free shipping on orders over $50\n";
        $response .= "• Standard: $4.99\n";
        $response .= "• Express: $9.99\n";
        $response .= "• Overnight: $19.99\n\n";
        $response .= "**Digital Products:**\n";
        $response .= "• Instant download after purchase\n";
        $response .= "• No shipping fees\n";
        $response .= "• Access from your account\n\n";
        $response .= "**International Shipping:**\n";
        $response .= "• Available to most countries\n";
        $response .= "• Customs fees may apply\n";
        $response .= "• Delivery times vary by location\n\n";
        $response .= "Need help with a specific shipping question?";
        
        return $response;
    }
    
    /**
     * Handle return/refund queries
     */
    private function handleReturns() {
        $response = "🔄 **Return & Refund Policy:**\n\n";
        $response .= "**Return Period:**\n";
        $response .= "• Physical products: 30 days from delivery\n";
        $response .= "• Digital products: 14 days from purchase\n\n";
        $response .= "**Return Conditions:**\n";
        $response .= "• Product must be unused and in original packaging\n";
        $response .= "• Include all accessories and documentation\n";
        $response .= "• Proof of purchase required\n\n";
        $response .= "**Refund Process:**\n";
        $response .= "1. Contact customer support\n";
        $response .= "2. Provide order details and reason\n";
        $response .= "3. Receive return authorization\n";
        $response .= "4. Ship item back (we provide label)\n";
        $response .= "5. Refund processed within 5-7 days\n\n";
        $response .= "**Digital Products:**\n";
        $response .= "• 14-day money-back guarantee\n";
        $response .= "• No return shipping required\n";
        $response .= "• Full refund if not satisfied\n\n";
        $response .= "Need to initiate a return or have questions?";
        
        return $response;
    }
    
    /**
     * Handle greetings
     */
    private function handleGreeting() {
        $greetings = [
            "Hello! 👋 Welcome to S3 Digital! How can I assist you today?",
            "Hi there! 😊 I'm here to help you find the perfect products and answer any questions!",
            "Greetings! 🎉 Welcome to S3 Digital! What can I help you with today?",
            "Hello! 🛍️ Ready to explore our amazing products? I'm here to help!"
        ];
        
        return $greetings[array_rand($greetings)];
    }
    
    /**
     * Handle goodbyes
     */
    private function handleGoodbye() {
        $goodbyes = [
            "Thank you for visiting S3 Digital! 👋 Have a wonderful day!",
            "Goodbye! 😊 Feel free to come back anytime you need assistance!",
            "Thanks for chatting with us! 🎉 See you again soon!",
            "Have a great day! 🛍️ We're always here to help!"
        ];
        
        return $goodbyes[array_rand($goodbyes)];
    }
    
    /**
     * Handle general queries
     */
    private function handleGeneralQuery($message) {
        $response = "I'm here to help! 🤖\n\n";
        $response .= "I can assist you with:\n\n";
        $response .= "🛍️ **Product Search:** Find specific items or categories\n";
        $response .= "📋 **Product Information:** Get details about products\n";
        $response .= "📦 **Order Status:** Track your orders\n";
        $response .= "💰 **Pricing:** Information about costs and discounts\n";
        $response .= "🤝 **Customer Support:** Help with any issues\n";
        $response .= "👤 **Account:** Manage your account\n";
        $response .= "💳 **Payment:** Payment methods and security\n";
        $response .= "🚚 **Shipping:** Delivery options and costs\n";
        $response .= "🔄 **Returns:** Return and refund policies\n\n";
        $response .= "Could you please be more specific about what you'd like to know?";
        
        return $response;
    }
    
    /**
     * Calculate intent confidence
     */
    private function getIntentConfidence($intent, $message) {
        // Simple confidence calculation based on keyword matches
        $confidence = 0.5; // Base confidence
        
        $intentPatterns = [
            'product_search' => ['search', 'find', 'looking for'],
            'product_info' => ['details', 'information', 'about'],
            'order_status' => ['order', 'tracking', 'status'],
            'pricing' => ['price', 'cost', 'how much'],
            'support' => ['help', 'support', 'issue'],
            'account' => ['account', 'profile', 'login'],
            'payment' => ['payment', 'checkout', 'pay'],
            'shipping' => ['shipping', 'delivery'],
            'returns' => ['return', 'refund'],
            'greeting' => ['hello', 'hi', 'hey'],
            'goodbye' => ['bye', 'goodbye', 'thanks']
        ];
        
        if (isset($intentPatterns[$intent])) {
            foreach ($intentPatterns[$intent] as $keyword) {
                if (stripos($message, $keyword) !== false) {
                    $confidence += 0.2;
                }
            }
        }
        
        return min(0.95, $confidence); // Cap at 95%
    }
    
    /**
     * Log chat messages
     */
    private function logMessage($message, $messageType, $intent = null, $responseTime = 0) {
        $stmt = mysqli_prepare($this->conn, "
            INSERT INTO ai_chat_conversations (user_id, session_id, message_type, message, intent, confidence, response_time)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $confidence = $this->getIntentConfidence($intent ?? 'general', $message);
        mysqli_stmt_bind_param($stmt, "issssdi", $this->userId, $this->sessionId, $messageType, $message, $intent, $confidence, $responseTime);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get chat history
     */
    public function getChatHistory($limit = 10) {
        $stmt = mysqli_prepare($this->conn, "
            SELECT message_type, message, intent, created_at
            FROM ai_chat_conversations
            WHERE (user_id = ? OR session_id = ?)
            ORDER BY created_at DESC
            LIMIT ?
        ");
        mysqli_stmt_bind_param($stmt, "isi", $this->userId, $this->sessionId, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
        
        return array_reverse($history); // Show oldest first
    }
}
?>
