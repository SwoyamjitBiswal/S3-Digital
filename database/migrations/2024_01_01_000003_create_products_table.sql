-- Migration: Create products table
-- Created: 2024-01-01 00:00:03

-- UP
CREATE TABLE `products` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(200) NOT NULL,
    `slug` varchar(200) NOT NULL,
    `description` text DEFAULT NULL,
    `short_description` text DEFAULT NULL,
    `price` decimal(10,2) NOT NULL,
    `compare_price` decimal(10,2) DEFAULT NULL,
    `cost_price` decimal(10,2) DEFAULT NULL,
    `sku` varchar(100) DEFAULT NULL,
    `barcode` varchar(100) DEFAULT NULL,
    `track_stock` tinyint(1) DEFAULT 1,
    `stock_quantity` int(11) DEFAULT 0,
    `stock_status` enum('in_stock','out_of_stock','on_backorder') DEFAULT 'in_stock',
    `weight` decimal(8,2) DEFAULT NULL,
    `dimensions` varchar(100) DEFAULT NULL,
    `category_id` int(11) NOT NULL,
    `brand` varchar(100) DEFAULT NULL,
    `tags` varchar(255) DEFAULT NULL,
    `image` varchar(255) DEFAULT NULL,
    `gallery` text DEFAULT NULL,
    `status` enum('active','inactive','draft') DEFAULT 'active',
    `featured` tinyint(1) DEFAULT 0,
    `digital` tinyint(1) DEFAULT 0,
    `file_name` varchar(255) DEFAULT NULL,
    `file_path` varchar(255) DEFAULT NULL,
    `download_limit` int(11) DEFAULT 5,
    `meta_title` varchar(200) DEFAULT NULL,
    `meta_description` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    UNIQUE KEY `sku` (`sku`),
    KEY `category_id` (`category_id`),
    KEY `status` (`status`),
    KEY `featured` (`featured`),
    KEY `price` (`price`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `products_category_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS `products`;
