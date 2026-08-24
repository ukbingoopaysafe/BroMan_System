-- Migration: Enhance Egyptian Primary Market Payment Plans and Financial Structure
-- Date: 2026-08-24

-- 1. Add fields to PropertyPaymentPlans
ALTER TABLE PropertyPaymentPlans
ADD COLUMN IF NOT EXISTS plan_title VARCHAR(255) NULL AFTER PropertyID,
ADD COLUMN IF NOT EXISTS installment_type VARCHAR(50) DEFAULT 'quarterly' AFTER installment_years,
ADD COLUMN IF NOT EXISTS delivery_payment_percentage DECIMAL(5,2) NULL AFTER installment_type;

-- 2. Add market financial fields to Properties
ALTER TABLE Properties
ADD COLUMN IF NOT EXISTS clubhouse_fee VARCHAR(255) NULL AFTER maintenance_percentage,
ADD COLUMN IF NOT EXISTS garage_fee VARCHAR(255) NULL AFTER clubhouse_fee,
ADD COLUMN IF NOT EXISTS transfer_fee_percentage DECIMAL(5,2) NULL AFTER garage_fee,
ADD COLUMN IF NOT EXISTS maintenance_payment_details VARCHAR(255) NULL AFTER transfer_fee_percentage;
