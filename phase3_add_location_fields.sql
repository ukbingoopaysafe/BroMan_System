-- Phase 3: Add latitude and longitude fields for interactive map functionality
-- Execute this SQL statement to add location coordinates to the properties table

ALTER TABLE `properties` 
ADD COLUMN `latitude` DECIMAL(10, 8) NULL AFTER `owner_phone`,
ADD COLUMN `longitude` DECIMAL(11, 8) NULL AFTER `latitude`;