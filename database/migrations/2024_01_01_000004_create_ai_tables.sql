-- Migration: Create AI enhancement tables
-- Created: 2024-01-01 00:00:04

-- UP
CREATE TABLE `user_behavior` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `session_id` varchar(255) DEFAULT NULL,
    `product_id` int(11) DEFAULT NULL,
    `action_type` enum('view','click','add_to_cart','purchase','search','wishlist') NOT NULL,
    `category_id` int(11) DEFAULT NULL,
    `search_query` varchar(500) DEFAULT NULL,
    `time_spent` int(11) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_behavior_user_action_created` (`user_id`,`action_type`,`created_at`),
    KEY `user_behavior_session_created` (`session_id`,`created_at`),
    KEY `user_behavior_product_action` (`product_id`,`action_type`),
    CONSTRAINT `user_behavior_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `user_behavior_product_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
    CONSTRAINT `user_behavior_category_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_recommendations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `product_id` int(11) NOT NULL,
    `recommendation_type` enum('collaborative','content_based','trending','similar','cross_sell') NOT NULL,
    `score` decimal(5,4) DEFAULT 0.0000,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `product_recommendations_user_score_created` (`user_id`,`score` DESC,`created_at`),
    KEY `product_recommendations_product_type` (`product_id`,`recommendation_type`),
    CONSTRAINT `product_recommendations_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `product_recommendations_product_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_chat_conversations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `session_id` varchar(255) DEFAULT NULL,
    `message_type` enum('user','bot','system') NOT NULL,
    `message` text NOT NULL,
    `intent` varchar(100) DEFAULT NULL,
    `confidence` decimal(3,2) DEFAULT 0.00,
    `response_time` int(11) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ai_chat_conversations_user_session_created` (`user_id`,`session_id`,`created_at`),
    KEY `ai_chat_conversations_intent_created` (`intent`,`created_at`),
    CONSTRAINT `ai_chat_conversations_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `search_suggestions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `query` varchar(255) NOT NULL,
    `frequency` int(11) DEFAULT 1,
    `category_id` int(11) DEFAULT NULL,
    `product_id` int(11) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `search_suggestions_query_frequency` (`query`,`frequency` DESC),
    KEY `search_suggestions_frequency_created` (`frequency` DESC,`created_at`),
    CONSTRAINT `search_suggestions_category_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `search_suggestions_product_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_generated_content` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `content_type` enum('product_description','category_description','blog_post','email_template') NOT NULL,
    `target_id` int(11) DEFAULT NULL,
    `original_content` text DEFAULT NULL,
    `generated_content` text NOT NULL,
    `prompt_used` text DEFAULT NULL,
    `model_version` varchar(50) DEFAULT NULL,
    `quality_score` decimal(3,2) DEFAULT 0.00,
    `is_approved` tinyint(1) DEFAULT 0,
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ai_generated_content_type_target` (`content_type`,`target_id`),
    KEY `ai_generated_content_approval_quality` (`is_approved`,`quality_score` DESC),
    CONSTRAINT `ai_generated_content_admin_foreign` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_preferences` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `preferred_categories` json DEFAULT NULL,
    `price_range_min` decimal(10,2) DEFAULT 0.00,
    `price_range_max` decimal(10,2) DEFAULT 999999.99,
    `brands` json DEFAULT NULL,
    `tags` json DEFAULT NULL,
    `notification_preferences` json DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_preferences_user_unique` (`user_id`),
    CONSTRAINT `user_preferences_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS `user_behavior`;
DROP TABLE IF EXISTS `product_recommendations`;
DROP TABLE IF EXISTS `ai_chat_conversations`;
DROP TABLE IF EXISTS `search_suggestions`;
DROP TABLE IF EXISTS `ai_generated_content`;
DROP TABLE IF EXISTS `user_preferences`;
