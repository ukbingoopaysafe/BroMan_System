-- Migration to update existing seller_type values to the new taxonomy
-- Backup affected rows first (optional)

START TRANSACTION;

-- Add any temporary column to preserve old values
ALTER TABLE `properties` ADD COLUMN `seller_type_old` VARCHAR(255) NULL;
UPDATE `properties` SET `seller_type_old` = `seller_type`;

-- Map old values to new values:
-- 'مالك مباشر' -> 'مالك'
-- 'مطور عقاري' -> 'شركة مطورة'
-- 'مكتب عقاري' -> 'وسيط' (best-effort)
-- Keep 'وكيل' as-is
-- Any unknown values will be set to NULL to avoid enum errors

UPDATE `properties` SET `seller_type` =
    CASE
        WHEN `seller_type_old` = 'مالك مباشر' THEN 'مالك'
        WHEN `seller_type_old` = 'مطور عقاري' THEN 'شركة مطورة'
        WHEN `seller_type_old` = 'مكتب عقاري' THEN 'وسيط'
        WHEN `seller_type_old` = 'وكيل' THEN 'وكيل'
        ELSE NULL
    END
WHERE `seller_type_old` IS NOT NULL;

COMMIT;

-- Optionally drop the backup column after verification
-- ALTER TABLE `properties` DROP COLUMN `seller_type_old`;
