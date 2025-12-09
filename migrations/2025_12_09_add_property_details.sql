-- Add Property Details Fields
ALTER TABLE Properties MODIFY COLUMN unit_design VARCHAR(255) NULL;

-- Check if columns exist before adding (using a stored procedure approach isn't easy in simple script, so we just try ADD and ignore if exists or use IF NOT EXISTS if supported by this MariaDB version)
-- MariaDB 10.2+ supports ADD COLUMN IF NOT EXISTS. XAMPP usually has recent versions.

ALTER TABLE Properties ADD COLUMN IF NOT EXISTS Bathrooms INT NULL DEFAULT NULL;
ALTER TABLE Properties ADD COLUMN IF NOT EXISTS balconies INT NULL DEFAULT NULL;
ALTER TABLE Properties ADD COLUMN IF NOT EXISTS elevators INT NULL DEFAULT NULL;
