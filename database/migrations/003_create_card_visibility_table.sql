-- Migration: 003_create_card_visibility_table
-- Description: Create card_visibility table with defaults (show_job_title=1, show_company=1, others=0)

CREATE TABLE IF NOT EXISTS `card_visibility` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_id` BIGINT UNSIGNED NOT NULL,
    `show_photo` TINYINT(1) NOT NULL DEFAULT 0,
    `show_job_title` TINYINT(1) NOT NULL DEFAULT 1,
    `show_department` TINYINT(1) NOT NULL DEFAULT 0,
    `show_company` TINYINT(1) NOT NULL DEFAULT 1,
    `show_company_address` TINYINT(1) NOT NULL DEFAULT 0,
    `show_phone` TINYINT(1) NOT NULL DEFAULT 0,
    `show_email` TINYINT(1) NOT NULL DEFAULT 0,
    `show_website` TINYINT(1) NOT NULL DEFAULT 0,
    `show_social_links` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_card_visibility_card_id` (`card_id`),
    CONSTRAINT `fk_card_visibility_card_id`
        FOREIGN KEY (`card_id`)
        REFERENCES `business_cards` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
