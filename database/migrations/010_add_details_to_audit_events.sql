-- Migration: 010_add_details_to_audit_events
-- Description: Add optional details column to audit_events for context logging (e.g. masked email)

ALTER TABLE `audit_events` ADD COLUMN `details` VARCHAR(255) NULL AFTER `target_id`;
