-- Migration: 007_create_audit_events_table
-- Description: Create audit_events table for security and compliance tracking

CREATE TABLE IF NOT EXISTS `audit_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `target_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_events_user_id` (`user_id`),
    INDEX `idx_audit_events_event_type` (`event_type`),
    CONSTRAINT `fk_audit_events_user_id`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
