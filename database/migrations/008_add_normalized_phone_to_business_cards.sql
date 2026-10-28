-- Migration: 008_add_normalized_phone_to_business_cards
-- Description: Add normalized_phone column for E.164 format tel: links

ALTER TABLE `business_cards`
    ADD COLUMN `normalized_phone` VARCHAR(50) NULL AFTER `professional_phone`;
