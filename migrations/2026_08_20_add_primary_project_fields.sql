-- Migration: Add Primary Project Fields to properties table
-- Date: 2026-08-20

ALTER TABLE `properties`
    ADD COLUMN `company_phone` varchar(50) DEFAULT NULL AFTER `company_name`,
    ADD COLUMN `sales_director_name` varchar(255) DEFAULT NULL AFTER `company_phone`,
    ADD COLUMN `sales_director_phone` varchar(50) DEFAULT NULL AFTER `sales_director_name`,
    ADD COLUMN `operation_manager_name` varchar(255) DEFAULT NULL AFTER `sales_director_phone`,
    ADD COLUMN `operation_manager_phone` varchar(50) DEFAULT NULL AFTER `operation_manager_name`,
    ADD COLUMN `min_price` decimal(15,2) DEFAULT NULL AFTER `loading_percentage`,
    ADD COLUMN `max_price` decimal(15,2) DEFAULT NULL AFTER `min_price`,
    ADD COLUMN `min_meter_price` decimal(10,2) DEFAULT NULL AFTER `meter_price_installments`,
    ADD COLUMN `max_meter_price` decimal(10,2) DEFAULT NULL AFTER `min_meter_price`,
    ADD COLUMN `cash_discount_percentage` decimal(5,2) DEFAULT NULL AFTER `down_payment_percentage`,
    ADD COLUMN `maintenance_percentage` decimal(5,2) DEFAULT NULL AFTER `cash_discount_percentage`,
    ADD COLUMN `phase_name` varchar(255) DEFAULT NULL AFTER `ProjectName`,
    ADD COLUMN `delivery_status` varchar(100) DEFAULT NULL AFTER `delivery_date`,
    ADD COLUMN `construction_status` varchar(100) DEFAULT NULL AFTER `delivery_status`,
    ADD COLUMN `offer_title` varchar(255) DEFAULT NULL AFTER `elevators`,
    ADD COLUMN `offer_description` text DEFAULT NULL AFTER `offer_title`,
    ADD COLUMN `offer_start_date` date DEFAULT NULL AFTER `offer_description`,
    ADD COLUMN `offer_end_date` date DEFAULT NULL AFTER `offer_start_date`,
    ADD COLUMN `brochure_url` varchar(500) DEFAULT NULL AFTER `offer_end_date`;
