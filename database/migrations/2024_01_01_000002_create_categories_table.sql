-- Migration: Create categories table
-- Created: 2024-01-01 00:00:02

-- UP
CREATE TABLE `categories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `slug` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `image` varchar(255) DEFAULT NULL,
    `parent_id` int(11) DEFAULT NULL,
    `sort_order` int(11) DEFAULT 0,
    `status` enum('active','inactive') DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    KEY `parent_id` (`parent_id`),
    KEY `status` (`status`),
    KEY `sort_order` (`sort_order`),
    CONSTRAINT `categories_parent_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS `categories`;
