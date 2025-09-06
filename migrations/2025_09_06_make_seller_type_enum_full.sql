-- Migration: update seller_type enum to the canonical list
-- WARNING: Back up your database before running this migration.
-- This migration attempts to map legacy values to the new taxonomy, then
-- alters the column to use the new ENUM set.

START TRANSACTION;

-- Optional: map known legacy values to the new values so we don't lose data
UPDATE Properties SET seller_type = 'مالك' WHERE seller_type IN ('مالك مباشر', 'مالك');
UPDATE Properties SET seller_type = 'وسيط' WHERE seller_type IN ('مكتب عقاري', 'وسيط');
UPDATE Properties SET seller_type = 'وكيل' WHERE seller_type IN ('وكيل', 'مكتب وكيل');
UPDATE Properties SET seller_type = 'شركة مطورة' WHERE seller_type IN ('مطور عقاري', 'شركة مطورة');
UPDATE Properties SET seller_type = 'شركة تشطيب' WHERE seller_type IN ('مطور عقاري', 'شركة تشطيب');
-- For values that are empty string (''), set to NULL so altering enum won't fail
UPDATE Properties SET seller_type = NULL WHERE seller_type = '';

-- Now alter the column to the new ENUM set. Adjust NULL/NOT NULL as appropriate.
ALTER TABLE Properties
	MODIFY COLUMN seller_type ENUM('مالك','وسيط','وكيل','بواب','شركة مطورة','شركة تشطيب') NULL DEFAULT NULL;

COMMIT;

-- After running migration: remove or adjust the mapping helper in PHP if you want
-- to store the canonical values directly without mapping.

