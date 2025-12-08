-- Add Rental mode and new rental roles
-- Run this on your MySQL server (e.g. via phpMyAdmin or CLI)

-- 1) Expand employee roles to include rental-specific roles
ALTER TABLE employees
    MODIFY Role ENUM('manager','admin','user','rental_admin','rental_user')
    NOT NULL DEFAULT 'user';

-- 2) Expand property listing_type to include Rental
ALTER TABLE properties
    MODIFY listing_type ENUM('Primary','Resale','Rental')
    NOT NULL DEFAULT 'Primary';

-- (Optional) If you want to seed an initial rental admin, insert it manually after running this migration.

