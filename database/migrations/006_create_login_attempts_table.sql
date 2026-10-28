-- Migration: 006_create_login_attempts_table
-- Description: Create login_attempts table for rate limiting and brute-force protection

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip` VARCHAR(45) NOT NULL,
    `email_hash` VARCHAR(64) NOT NULL,
    `attempted_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_login_attempts_ip_time` (`ip`, `attempted_at`),
    INDEX `idx_login_attempts_email_time` (`email_hash`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
