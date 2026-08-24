-- Migration: Add company_phone_2, compound_area_unit, payment_plans_notes, and PropertyPaymentPlans table

ALTER TABLE `Properties`
    ADD COLUMN `company_phone_2` varchar(50) DEFAULT NULL AFTER `company_phone`,
    ADD COLUMN `compound_area_unit` enum('faddan','sqm') NOT NULL DEFAULT 'faddan' AFTER `compound_area`,
    ADD COLUMN `payment_plans_notes` text DEFAULT NULL AFTER `payment_years`;

CREATE TABLE IF NOT EXISTS `PropertyPaymentPlans` (
    `plan_id` INT(11) NOT NULL AUTO_INCREMENT,
    `PropertyID` INT(11) NOT NULL,
    `down_payment_percentage` DECIMAL(5,2) DEFAULT NULL,
    `installment_years` DECIMAL(5,2) DEFAULT NULL,
    `roi_notes` VARCHAR(255) DEFAULT NULL,
    `plan_notes` TEXT DEFAULT NULL,
    `display_order` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`plan_id`),
    KEY `idx_property_payment_plans_property` (`PropertyID`),
    CONSTRAINT `fk_payment_plans_property` FOREIGN KEY (`PropertyID`) REFERENCES `Properties` (`PropertyID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
