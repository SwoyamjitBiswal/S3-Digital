-- Migration: Create users table
-- Created: 2024-01-01 00:00:01

-- UP
CREATE TABLE `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `password` varchar(255) NOT NULL,
    `remember_token` varchar(255) DEFAULT NULL,
    `token_expiry` timestamp NULL DEFAULT NULL,
    `email_verified_at` timestamp NULL DEFAULT NULL,
    `status` enum('active','inactive','suspended') DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `status` (`status`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS `users`;
