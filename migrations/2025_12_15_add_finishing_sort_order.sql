-- Migration: Add custom sort_order to propertyfinishingtypes and insert 'Normal' type
-- Created: 2025-12-15

-- 1. Add sort_order column if it doesn't exist
-- Note: 'IF NOT EXISTS' for ALTER TABLE requires MariaDB 10.0+ or MySQL 8.0+
-- If using older versions, this might error if column exists, but safe to ignore.
ALTER TABLE propertyfinishingtypes ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;

-- 2. Reset sort_order to match FinishingID (establishes a known base state)
-- This makes the script idempotent: re-running it resets and re-shifts.
UPDATE propertyfinishingtypes SET sort_order = FinishingID;

-- 3. Shift items to make space for "Normal" at position 4
-- "Red Brick"(1), "Semi Finished"(2), "Plaster..."(3) stay.
-- "Lux"(4) and above become 5+.
UPDATE propertyfinishingtypes SET sort_order = sort_order + 1 WHERE sort_order >= 4;

-- 4. Insert "Normal" type
-- If it already exists (duplicate key on FinishingName), just ensure its sort_order is 4.
INSERT INTO propertyfinishingtypes (FinishingName, FinishingName_ar, sort_order) 
VALUES ('Normal', 'عادي', 4)
ON DUPLICATE KEY UPDATE sort_order = 4;
