-- Migration: 004_create_card_links_table
-- Description: Create card_links table for social and custom links

CREATE TABLE IF NOT EXISTS `card_links` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_id` BIGINT UNSIGNED NOT NULL,
    `label` VARCHAR(100) NOT NULL,
    `url` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_public` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    INDEX `idx_card_links_card_id` (`card_id`),
    CONSTRAINT `fk_card_links_card_id`
        FOREIGN KEY (`card_id`)
        REFERENCES `business_cards` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
