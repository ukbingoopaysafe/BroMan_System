-- Phase 2: ALTER TABLE properties to add new data fields
-- Execute this SQL statement to add all new columns to the properties table

ALTER TABLE `properties` 
ADD COLUMN `year_built` YEAR NULL AFTER `FloorsCount`,
ADD COLUMN `unit_floor` VARCHAR(50) NULL AFTER `year_built`,
ADD COLUMN `net_area` DECIMAL(10,2) NULL AFTER `unit_floor`,
ADD COLUMN `loading_percentage` DECIMAL(5,2) NULL AFTER `net_area`,
ADD COLUMN `price_cash` DECIMAL(15,2) NULL AFTER `loading_percentage`,
ADD COLUMN `price_installments` DECIMAL(15,2) NULL AFTER `price_cash`,
ADD COLUMN `meter_price_cash` DECIMAL(10,2) NULL AFTER `price_installments`,
ADD COLUMN `meter_price_installments` DECIMAL(10,2) NULL AFTER `meter_price_cash`,
ADD COLUMN `seller_type` ENUM('مالك مباشر', 'وكيل', 'مكتب عقاري', 'مطور عقاري') NULL AFTER `meter_price_installments`,
ADD COLUMN `seller_name` VARCHAR(255) NULL AFTER `seller_type`,
ADD COLUMN `seller_phone` VARCHAR(50) NULL AFTER `seller_name`;