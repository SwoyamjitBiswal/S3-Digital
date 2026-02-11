<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

class AiContentGenerator {
    private $conn;
    private $adminId;
    
    public function __construct($adminId = null) {
        global $conn;
        $this->conn = $conn;
        $this->adminId = $adminId ?: $_SESSION['admin_id'] ?? null;
    }
    
    /**
     * Generate product description using AI
     */
    public function generateProductDescription($productId, $options = []) {
        // Get product details
        $product = $this->getProductDetails($productId);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found'];
        }
        
        // Generate description based on product information
        $description = $this->createProductDescription($product, $options);
        
        // Save generated content
        $contentId = $this->saveGeneratedContent('product_description', $productId, $product['description'], $description, $options);
        
        return [
            'success' => true,
            'content_id' => $contentId,
            'original' => $product['description'],
            'generated' => $description,
            'product' => $product
        ];
    }
    
    /**
     * Generate category description
     */
    public function generateCategoryDescription($categoryId, $options = []) {
        // Get category details
        $category = $this->getCategoryDetails($categoryId);
        if (!$category) {
            return ['success' => false, 'error' => 'Category not found'];
        }
        
        // Generate description based on category information
        $description = $this->createCategoryDescription($category, $options);
        
        // Save generated content
        $contentId = $this->saveGeneratedContent('category_description', $categoryId, $category['description'], $description, $options);
        
        return [
            'success' => true,
            'content_id' => $contentId,
            'original' => $category['description'],
            'generated' => $description,
            'category' => $category
        ];
    }
    
    /**
     * Generate blog post content
     */
    public function generateBlogPost($topic, $options = []) {
        $blogPost = $this->createBlogPost($topic, $options);
        
        // Save generated content
        $contentId = $this->saveGeneratedContent('blog_post', 0, '', $blogPost, $options);
        
        return [
            'success' => true,
            'content_id' => $contentId,
            'generated' => $blogPost,
            'topic' => $topic
        ];
    }
    
    /**
     * Generate email template
     */
    public function generateEmailTemplate($templateType, $context = [], $options = []) {
        $template = $this->createEmailTemplate($templateType, $context, $options);
        
        // Save generated content
        $contentId = $this->saveGeneratedContent('email_template', 0, '', $template, $options);
        
        return [
            'success' => true,
            'content_id' => $contentId,
            'generated' => $template,
            'type' => $templateType,
            'context' => $context
        ];
    }
    
    /**
     * Create product description
     */
    private function createProductDescription($product, $options) {
        $tone = $options['tone'] ?? 'professional';
        $length = $options['length'] ?? 'medium';
        $features = $options['include_features'] ?? true;
        $benefits = $options['include_benefits'] ?? true;
        $specifications = $options['include_specifications'] ?? false;
        
        $description = '';
        
        // Opening hook
        $hooks = [
            'professional' => [
                "Introducing the {$product['title']}, a premium solution designed for excellence.",
                "Discover the {$product['title']}, engineered to meet your highest standards.",
                "Experience innovation with the {$product['title']}, where quality meets functionality."
            ],
            'casual' => [
                "Get ready to love the {$product['title']}! This amazing product is exactly what you've been looking for.",
                "Say hello to your new favorite thing - the {$product['title']}!",
                "You're going to be obsessed with the {$product['title']}! Trust us on this one."
            ],
            'technical' => [
                "The {$product['title']} represents a significant advancement in product engineering and design.",
                "Engineered with precision, the {$product['title']} delivers exceptional performance metrics.",
                "Utilizing cutting-edge technology, the {$product['title']} sets new industry standards."
            ],
            'persuasive' => [
                "Transform your experience with the {$product['title']} - the choice of discerning customers worldwide.",
                "Don't settle for less when you can have the {$product['title']} - the ultimate solution you deserve.",
                "Join thousands of satisfied customers who have made the {$product['title']} their top choice."
            ]
        ];
        
        $selectedHooks = $hooks[$tone] ?? $hooks['professional'];
        $description .= $selectedHooks[array_rand($selectedHooks)] . "\n\n";
        
        // Main description
        if ($length === 'short') {
            $description .= "This exceptional product combines innovative design with practical functionality. ";
            $description .= "Perfect for both personal and professional use, it delivers outstanding performance and reliability. ";
        } elseif ($length === 'medium') {
            $description .= "This exceptional product combines innovative design with practical functionality to deliver an outstanding user experience. ";
            $description .= "Crafted with attention to detail and built to last, it meets the highest standards of quality and performance. ";
            $description .= "Whether you're a professional or enthusiast, you'll appreciate the thoughtful engineering and premium materials. ";
        } else { // long
            $description .= "This exceptional product represents the perfect fusion of innovative design, advanced technology, and practical functionality. ";
            $description .= "Meticulously crafted with premium materials and engineered to exceed expectations, it delivers an unparalleled user experience. ";
            $description .= "Every aspect has been carefully considered and optimized to provide maximum value, reliability, and satisfaction. ";
            $description .= "From its elegant aesthetics to its robust performance capabilities, this product sets a new standard in its category. ";
        }
        
        // Features section
        if ($features) {
            $description .= "\n\n**Key Features:**\n";
            $featuresList = $this->generateProductFeatures($product, $tone);
            $description .= $featuresList;
        }
        
        // Benefits section
        if ($benefits) {
            $description .= "\n\n**Why Choose This Product:**\n";
            $benefitsList = $this->generateProductBenefits($product, $tone);
            $description .= $benefitsList;
        }
        
        // Specifications section
        if ($specifications) {
            $description .= "\n\n**Technical Specifications:**\n";
            $specsList = $this->generateProductSpecifications($product, $tone);
            $description .= $specsList;
        }
        
        // Closing statement
        $closings = [
            'professional' => [
                "Invest in quality and excellence with the {$product['title']}. Your satisfaction is guaranteed.",
                "Choose the {$product['title']} for unmatched performance and reliability.",
                "Experience the difference that premium quality makes with the {$product['title']}."
            ],
            'casual' => [
                "You'll absolutely love the {$product['title']} - we promise!",
                "Go ahead and treat yourself to the {$product['title']}. You deserve it!",
                "The {$product['title']} is waiting for you. Don't wait too long!"
            ],
            'technical' => [
                "The {$product['title']} meets all industry standards and exceeds performance expectations.",
                "For technical excellence and reliability, choose the {$product['title']}.",
                "Engineering excellence is embodied in every aspect of the {$product['title']}."
            ],
            'persuasive' => [
                "Don't wait - experience the excellence of the {$product['title']} today!",
                "Join the smart shoppers who have already discovered the {$product['title']}.",
                "Your perfect solution is just a click away - get the {$product['title']} now!"
            ]
        ];
        
        $selectedClosings = $closings[$tone] ?? $closings['professional'];
        $description .= "\n\n" . $selectedClosings[array_rand($selectedClosings)];
        
        return $description;
    }
    
    /**
     * Generate product features
     */
    private function generateProductFeatures($product, $tone) {
        $features = [];
        
        // Generate features based on product category and title
        $category = strtolower($product['category_name'] ?? '');
        $title = strtolower($product['title'] ?? '');
        
        if (strpos($category, 'software') !== false || strpos($title, 'software') !== false) {
            $features = [
                "• User-friendly interface with intuitive navigation",
                "• Regular updates and improvements",
                "• Compatible with multiple platforms",
                "• Advanced security features",
                "• 24/7 technical support"
            ];
        } elseif (strpos($category, 'hardware') !== false || strpos($title, 'device') !== false) {
            $features = [
                "• Premium build quality with durable materials",
                "• Energy-efficient performance",
                "• Easy setup and installation",
                "• Comprehensive warranty coverage",
                "• Ergonomic design for comfort"
            ];
        } elseif (strpos($category, 'digital') !== false || strpos($title, 'digital') !== false) {
            $features = [
                "• Instant digital delivery",
                "• Lifetime access included",
                "• Regular content updates",
                "• Multi-device compatibility",
                "• Secure cloud storage"
            ];
        } else {
            $features = [
                "• High-quality construction",
                "• Innovative design features",
                "• Excellent value for money",
                "• Reliable performance",
                "• Customer satisfaction guaranteed"
            ];
        }
        
        return implode("\n", $features);
    }
    
    /**
     * Generate product benefits
     */
    private function generateProductBenefits($product, $tone) {
        $benefits = [];
        
        if ($tone === 'professional') {
            $benefits = [
                "• Enhances productivity and efficiency",
                "• Provides long-term value and reliability",
                "• Backed by comprehensive warranty and support",
                "• Trusted by professionals worldwide",
                "• Meets industry standards and certifications"
            ];
        } elseif ($tone === 'casual') {
            $benefits = [
                "• Makes your life easier and more fun",
                "• You'll wonder how you lived without it",
                "• Friends will be totally jealous",
                "• Super easy to use right out of the box",
                "• Makes a great gift too!"
            ];
        } elseif ($tone === 'technical') {
            $benefits = [
                "• Optimized for maximum performance efficiency",
                "• Utilizes advanced technology and materials",
                "• Exceeds industry benchmark standards",
                "• Designed for scalability and future-proofing",
                "• Comprehensive technical documentation included"
            ];
        } else { // persuasive
            $benefits = [
                "• Transform your experience immediately",
                "• Join thousands of satisfied customers",
                "• Limited time offer - don't miss out",
                "• Risk-free purchase with satisfaction guarantee",
                "• Upgrade your life starting today"
            ];
        }
        
        return implode("\n", $benefits);
    }
    
    /**
     * Generate product specifications
     */
    private function generateProductSpecifications($product, $tone) {
        $specs = [];
        
        // Generate realistic specifications based on product type
        $category = strtolower($product['category_name'] ?? '');
        
        if (strpos($category, 'software') !== false) {
            $specs = [
                "• Platform: Windows, Mac, Linux compatible",
                "• Memory: Minimum 4GB RAM recommended",
                "• Storage: 500MB available space",
                "• Internet: Required for activation and updates",
                "• License: Lifetime access included"
            ];
        } elseif (strpos($category, 'hardware') !== false) {
            $specs = [
                "• Dimensions: Compact and portable design",
                "• Weight: Lightweight for easy handling",
                "• Power: Energy-efficient operation",
                "• Materials: Premium quality components",
                "• Warranty: 2-year manufacturer warranty"
            ];
        } else {
            $specs = [
                "• Quality: Premium grade materials",
                "• Design: Modern and stylish appearance",
                "• Compatibility: Universal fit",
                "• Maintenance: Easy to clean and maintain",
                "• Packaging: Eco-friendly materials"
            ];
        }
        
        return implode("\n", $specs);
    }
    
    /**
     * Create category description
     */
    private function createCategoryDescription($category, $options) {
        $tone = $options['tone'] ?? 'professional';
        $length = $options['length'] ?? 'medium';
        
        $description = '';
        
        // Category introduction
        $intros = [
            'professional' => [
                "Welcome to our {$category['name']} collection, featuring premium products selected for excellence.",
                "Explore our curated {$category['name']} category, where quality meets innovation.",
                "Discover exceptional products in our {$category['name']} collection."
            ],
            'casual' => [
                "Check out our awesome {$category['name']} collection - you're going to love these!",
                "Welcome to the coolest {$category['name']} selection around!",
                "Get ready to explore our amazing {$category['name']} products!"
            ]
        ];
        
        $selectedIntros = $intros[$tone] ?? $intros['professional'];
        $description .= $selectedIntros[array_rand($selectedIntros)] . "\n\n";
        
        // Main description
        if ($length === 'short') {
            $description .= "Our {$category['name']} collection features carefully selected products that combine quality, innovation, and value. ";
            $description .= "Each item is chosen to meet your highest expectations and deliver exceptional performance.";
        } else {
            $description .= "Our {$category['name']} collection represents the finest selection of products available, each carefully chosen for its outstanding quality, innovative features, and exceptional value. ";
            $description .= "We work tirelessly to source products that not only meet but exceed your expectations, ensuring that every purchase brings satisfaction and delight. ";
            $description .= "Whether you're looking for the latest innovations or time-tested classics, you'll find exactly what you need in this comprehensive collection.";
        }
        
        // Category highlights
        $description .= "\n\n**Collection Highlights:**\n";
        $highlights = [
            "• Premium quality products from trusted brands",
            "• Latest innovations and cutting-edge technology",
            "• Competitive pricing and excellent value",
            "• Comprehensive warranty and support options",
            "• Regularly updated with new arrivals"
        ];
        
        $description .= implode("\n", $highlights);
        
        // Call to action
        $description .= "\n\nBrowse our {$category['name']} collection today and discover products that will transform your experience!";
        
        return $description;
    }
    
    /**
     * Create blog post
     */
    private function createBlogPost($topic, $options) {
        $tone = $options['tone'] ?? 'professional';
        $length = $options['length'] ?? 'medium';
        
        $blogPost = "# " . ucfirst($topic) . "\n\n";
        
        $blogPost .= "In this comprehensive guide, we'll explore everything you need to know about {$topic}. ";
        $blogPost .= "Whether you're a beginner or an experienced enthusiast, this article will provide valuable insights and practical information.\n\n";
        
        $blogPost .= "## What is {$topic}?\n\n";
        $blogPost .= "{$topic} represents an important aspect of modern digital solutions. ";
        $blogPost .= "Understanding its fundamentals can help you make informed decisions and maximize its benefits.\n\n";
        
        $blogPost .= "## Key Benefits\n\n";
        $blogPost .= "• Enhanced efficiency and productivity\n";
        $blogPost .= "• Cost-effective solutions for your needs\n";
        $blogPost .= "• Reliable performance and support\n";
        $blogPost .= "• Future-proof technology and features\n\n";
        
        $blogPost .= "## How to Get Started\n\n";
        $blogPost .= "Getting started with {$topic} is easier than you might think. ";
        $blogPost .= "Follow these simple steps to begin your journey:\n\n";
        $blogPost .= "1. Research and understand your requirements\n";
        $blogPost .= "2. Choose the right solution for your needs\n";
        $blogPost .= "3. Follow best practices for implementation\n";
        $blogPost .= "4. Monitor and optimize performance\n\n";
        
        $blogPost .= "## Conclusion\n\n";
        $blogPost .= "{$topic} offers tremendous value and opportunities for those willing to embrace it. ";
        $blogPost .= "By following the guidelines and best practices outlined in this guide, you'll be well-equipped to succeed.\n\n";
        
        $blogPost .= "Ready to explore our {$topic} solutions? Browse our collection today!";
        
        return $blogPost;
    }
    
    /**
     * Create email template
     */
    private function createEmailTemplate($templateType, $context, $options) {
        $templates = [
            'welcome' => [
                'subject' => 'Welcome to S3 Digital!',
                'body' => "Dear {customer_name},\n\nWelcome to S3 Digital! We're thrilled to have you join our community.\n\nYour account has been successfully created, and you now have access to:\n\n• Our premium product collection\n• Exclusive member benefits\n• Personalized recommendations\n• Priority customer support\n\nStart exploring our amazing products today and discover why thousands of customers trust S3 Digital for their needs.\n\nIf you have any questions, our support team is here to help 24/7.\n\nBest regards,\nThe S3 Digital Team"
            ],
            'order_confirmation' => [
                'subject' => 'Order Confirmation - #{order_id}',
                'body' => "Dear {customer_name},\n\nThank you for your order! We're excited to process your purchase.\n\nOrder Details:\nOrder ID: {order_id}\nDate: {order_date}\nTotal: {order_total}\n\nYour order is being processed and you'll receive updates as it progresses. You can track your order status in your account.\n\nIf you have any questions about your order, please don't hesitate to contact us.\n\nThank you for choosing S3 Digital!\n\nBest regards,\nThe S3 Digital Team"
            ],
            'password_reset' => [
                'subject' => 'Password Reset Request',
                'body' => "Dear {customer_name},\n\nWe received a request to reset your password for your S3 Digital account.\n\nTo reset your password, please click the link below:\n{reset_link}\n\nThis link will expire in 24 hours for security reasons.\n\nIf you didn't request this password reset, please ignore this email or contact our support team.\n\nFor your security, never share this link with anyone.\n\nBest regards,\nThe S3 Digital Team"
            ],
            'newsletter' => [
                'subject' => 'Latest Updates and Special Offers from S3 Digital',
                'body' => "Dear {customer_name},\n\nCheck out what's new at S3 Digital!\n\n🔥 **Hot Deals This Week:**\n{featured_products}\n\n📢 **Latest Arrivals:**\n{new_products}\n\n💡 **Tips & Tricks:**\nDiscover how to get the most out of your purchases with our latest blog posts and guides.\n\nDon't miss out on these amazing offers! Visit our store today.\n\nBest regards,\nThe S3 Digital Team"
            ]
        ];
        
        $template = $templates[$templateType] ?? $templates['welcome'];
        
        // Replace placeholders with context data
        $body = $template['body'];
        foreach ($context as $key => $value) {
            $body = str_replace('{' . $key . '}', $value, $body);
        }
        
        return [
            'subject' => $template['subject'],
            'body' => $body
        ];
    }
    
    /**
     * Save generated content to database
     */
    private function saveGeneratedContent($contentType, $targetId, $originalContent, $generatedContent, $options) {
        $prompt = json_encode($options);
        $modelVersion = '1.0';
        $qualityScore = $this->calculateQualityScore($generatedContent);
        
        $stmt = mysqli_prepare($this->conn, "
            INSERT INTO ai_generated_content 
            (content_type, target_id, original_content, generated_content, prompt_used, model_version, quality_score, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "sisssdsi", $contentType, $targetId, $originalContent, $generatedContent, $prompt, $modelVersion, $qualityScore, $this->adminId);
        mysqli_stmt_execute($stmt);
        
        return mysqli_insert_id($this->conn);
    }
    
    /**
     * Calculate quality score for generated content
     */
    private function calculateQualityScore($content) {
        $score = 0.5; // Base score
        
        // Length score
        $length = strlen($content);
        if ($length > 100) $score += 0.1;
        if ($length > 500) $score += 0.1;
        if ($length > 1000) $score += 0.1;
        
        // Structure score
        if (strpos($content, '•') !== false) $score += 0.1; // Has bullet points
        if (preg_match('/\*\*.*?\*\*/', $content)) $score += 0.1; // Has bold text
        
        // Content quality indicators
        if (preg_match('/\b(benefit|feature|advantage|quality|premium)\b/i', $content)) $score += 0.1;
        if (preg_match('/\b(guarantee|warranty|support|reliable)\b/i', $content)) $score += 0.1;
        
        return min(1.0, $score);
    }
    
    /**
     * Get product details
     */
    private function getProductDetails($productId) {
        $stmt = mysqli_prepare($this->conn, "
            SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $productId);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt)->fetch_assoc();
    }
    
    /**
     * Get category details
     */
    private function getCategoryDetails($categoryId) {
        $stmt = mysqli_prepare($this->conn, "
            SELECT * FROM categories WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $categoryId);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt)->fetch_assoc();
    }
    
    /**
     * Get generated content history
     */
    public function getGeneratedContent($contentType = null, $limit = 20) {
        $sql = "
            SELECT agc.*, au.username as created_by_name
            FROM ai_generated_content agc
            LEFT JOIN admin_users au ON agc.created_by = au.id
        ";
        
        $params = [];
        $types = '';
        
        if ($contentType) {
            $sql .= " WHERE agc.content_type = ?";
            $params[] = $contentType;
            $types .= 's';
        }
        
        $sql .= " ORDER BY agc.created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $content = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $content[] = $row;
        }
        
        return $content;
    }
    
    /**
     * Approve generated content
     */
    public function approveContent($contentId) {
        $stmt = mysqli_prepare($this->conn, "
            UPDATE ai_generated_content 
            SET is_approved = TRUE 
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $contentId);
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get content suggestions for improvement
     */
    public function getContentSuggestions($content) {
        $suggestions = [];
        
        // Analyze content and provide suggestions
        if (strlen($content) < 200) {
            $suggestions[] = "Consider adding more details to make the content more comprehensive";
        }
        
        if (!preg_match('/\b(benefit|advantage|feature)\b/i', $content)) {
            $suggestions[] = "Add more benefit-oriented language to highlight value";
        }
        
        if (!preg_match('/\•/', $content)) {
            $suggestions[] = "Consider using bullet points for better readability";
        }
        
        if (!preg_match('/\?\./', $content)) {
            $suggestions[] = "Add engaging questions to capture reader interest";
        }
        
        return $suggestions;
    }
}
?>
