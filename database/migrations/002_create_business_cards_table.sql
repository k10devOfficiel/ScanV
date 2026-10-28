-- Migration: 002_create_business_cards_table
-- Description: Create business_cards table (1 card per user in V1, public_id 32 chars)

CREATE TABLE IF NOT EXISTS `business_cards` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `public_id` CHAR(32) NOT NULL,
    `display_first_name` VARCHAR(100) NOT NULL,
    `display_last_name` VARCHAR(100) NOT NULL,
    `job_title` VARCHAR(150) NULL,
    `department` VARCHAR(150) NULL,
    `company_name` VARCHAR(150) NULL,
    `company_website` VARCHAR(255) NULL,
    `company_address` TEXT NULL,
    `professional_phone` VARCHAR(50) NULL,
    `professional_email` VARCHAR(191) NULL,
    `photo_path` VARCHAR(255) NULL,
    `logo_path` VARCHAR(255) NULL,
    `theme_key` VARCHAR(50) NOT NULL DEFAULT 'default',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_business_cards_user_id` (`user_id`),
    UNIQUE KEY `uk_business_cards_public_id` (`public_id`),
    INDEX `idx_business_cards_user_id` (`user_id`),
    CONSTRAINT `fk_business_cards_user_id`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
