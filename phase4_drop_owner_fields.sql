-- Phase 4: Drop legacy owner fields no longer used
-- Safe to run multiple times (uses IF EXISTS)

ALTER TABLE `properties`
    DROP COLUMN IF EXISTS `owner_name`,
    DROP COLUMN IF EXISTS `owner_phone`;

