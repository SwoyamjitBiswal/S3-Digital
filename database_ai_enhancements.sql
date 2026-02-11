-- AI-Powered Enhancements Database Schema

-- User Behavior Tracking Table
CREATE TABLE user_behavior (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(255),
    product_id INT,
    action_type ENUM('view', 'click', 'add_to_cart', 'purchase', 'search', 'wishlist'),
    category_id INT,
    search_query VARCHAR(500),
    time_spent INT DEFAULT 0, -- in seconds
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_user_behavior (user_id, action_type, created_at),
    INDEX idx_session (session_id, created_at),
    INDEX idx_product (product_id, action_type)
);

-- Product Recommendations Table
CREATE TABLE product_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    product_id INT,
    recommendation_type ENUM('collaborative', 'content_based', 'trending', 'similar', 'cross_sell'),
    score DECIMAL(5,4) DEFAULT 0.0000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_recommendations (user_id, score DESC, created_at),
    INDEX idx_product_recommendations (product_id, recommendation_type)
);

-- AI Chat Conversations Table
CREATE TABLE ai_chat_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(255),
    message_type ENUM('user', 'bot', 'system'),
    message TEXT,
    intent VARCHAR(100),
    confidence DECIMAL(3,2) DEFAULT 0.00,
    response_time INT DEFAULT 0, -- in milliseconds
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_conversations (user_id, session_id, created_at),
    INDEX idx_intent (intent, created_at)
);

-- Search Suggestions Table
CREATE TABLE search_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query VARCHAR(255) NOT NULL,
    frequency INT DEFAULT 1,
    category_id INT,
    product_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_suggestions (query, frequency DESC),
    INDEX idx_popular (frequency DESC, created_at)
);

-- AI Generated Content Table
CREATE TABLE ai_generated_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('product_description', 'category_description', 'blog_post', 'email_template'),
    target_id INT, -- product_id, category_id, etc.
    original_content TEXT,
    generated_content TEXT,
    prompt_used TEXT,
    model_version VARCHAR(50),
    quality_score DECIMAL(3,2) DEFAULT 0.00,
    is_approved BOOLEAN DEFAULT FALSE,
    created_by INT, -- admin user who requested/approved
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_content (content_type, target_id),
    INDEX idx_approval (is_approved, quality_score DESC)
);

-- User Preferences Table (for personalization)
CREATE TABLE user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    preferred_categories JSON,
    price_range_min DECIMAL(10,2) DEFAULT 0.00,
    price_range_max DECIMAL(10,2) DEFAULT 999999.99,
    brands JSON,
    tags JSON,
    notification_preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preferences (user_id)
);

-- AI Models Configuration Table
CREATE TABLE ai_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    model_type ENUM('recommendation', 'chatbot', 'search', 'content_generation'),
    version VARCHAR(50),
    config JSON,
    is_active BOOLEAN DEFAULT TRUE,
    performance_metrics JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_models (model_type, is_active)
);

-- Insert default AI model configurations
INSERT INTO ai_models (model_name, model_type, version, config) VALUES
('Collaborative Filtering', 'recommendation', '1.0', '{"algorithm": "user_based", "min_interactions": 5, "similarity_threshold": 0.1}'),
('Content-Based Filtering', 'recommendation', '1.0', '{"features": ["category", "tags", "price_range"], "weight_decay": 0.9}'),
('Trending Products', 'recommendation', '1.0', '{"time_window": "7_days", "min_views": 10}'),
('GPT-3.5 Chatbot', 'chatbot', '1.0', '{"max_tokens": 150, "temperature": 0.7, "context_window": 10}'),
('Smart Search', 'search', '1.0', '{"fuzzy_threshold": 0.8, "max_suggestions": 10}'),
('Content Generator', 'content_generation', '1.0', '{"max_length": 500, "tone": "professional"}');

-- CSRF Tokens Table
CREATE TABLE csrf_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) NOT NULL UNIQUE,
    user_id INT,
    session_id VARCHAR(255),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- Security Logs Table
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    action VARCHAR(100),
    status ENUM('success', 'failure', 'suspicious'),
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_security (user_id, ip_address, created_at),
    INDEX idx_status (status, created_at)
);
