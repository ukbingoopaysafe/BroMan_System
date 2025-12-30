-- Add flexible AllowedModes column to employees table
-- This allows managers to assign any combination of listing modes to users
-- Run this on your MySQL server (e.g. via phpMyAdmin or CLI)

-- Add AllowedModes column
ALTER TABLE employees
    ADD COLUMN AllowedModes VARCHAR(50) DEFAULT 'Primary,Resale' 
    AFTER Role;

-- Update existing employees based on their current roles:
-- Managers get all modes
UPDATE employees SET AllowedModes = 'Primary,Resale,Rental' WHERE Role = 'manager';

-- Admin and User roles get Primary/Resale (default already set)
UPDATE employees SET AllowedModes = 'Primary,Resale' WHERE Role IN ('admin', 'user');

-- Rental roles get only Rental
UPDATE employees SET AllowedModes = 'Rental' WHERE Role IN ('rental_admin', 'rental_user');

-- Convert rental_admin to admin (they are admins for rentals)
UPDATE employees SET Role = 'admin' WHERE Role = 'rental_admin';

-- Convert rental_user to user (they are users for rentals)
UPDATE employees SET Role = 'user' WHERE Role = 'rental_user';

-- Simplify the Role ENUM to just 3 values
ALTER TABLE employees
    MODIFY Role ENUM('manager','admin','user')
    NOT NULL DEFAULT 'user';
