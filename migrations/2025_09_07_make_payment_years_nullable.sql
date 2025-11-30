-- Migration: make payment_years nullable
-- Run this against the broman_db database.

ALTER TABLE `properties`
  MODIFY COLUMN `payment_years` decimal(5,2) DEFAULT NULL;

-- Optional: if you want to update existing rows where payment_years = 0.00 to NULL, uncomment:
-- UPDATE `properties` SET `payment_years` = NULL WHERE `payment_years` = 0.00;
