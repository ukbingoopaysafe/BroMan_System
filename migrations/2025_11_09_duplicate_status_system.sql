-- Phase 5: Duplicate Review Status System
-- This migration adds the duplicate_status column to track review status of duplicate properties

-- Add duplicate_status column to properties table
-- 0: Unique (no duplicates)
-- 1: Needs Review (new property in group, or group has new additions)
-- 2: Reviewed (admin has reviewed this group)

ALTER TABLE `properties`
ADD COLUMN `duplicate_status` TINYINT(1) NOT NULL DEFAULT 0 
COMMENT '0=Unique, 1=Needs Review, 2=Reviewed' 
AFTER `salesperson_phone`;

-- Add index for faster queries
ALTER TABLE `properties`
ADD INDEX `idx_properties_duplicate_status` (`duplicate_status`);

-- Initialize: Set status to 1 (Needs Review) for all properties that are part of duplicate groups
-- This ensures existing duplicates are marked for review
UPDATE `properties` p
INNER JOIN (
    SELECT v.PropertyID
    FROM v_property_phones_normalized v
    INNER JOIN (
        SELECT normalized_phone
        FROM v_duplicate_property_phone_groups
    ) g ON v.normalized_phone = g.normalized_phone
) dup ON p.PropertyID = dup.PropertyID
SET p.duplicate_status = 1;

