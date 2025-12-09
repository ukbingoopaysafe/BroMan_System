-- Add Daily Rent Field
ALTER TABLE Properties
ADD COLUMN daily_rent DECIMAL(15,2) NULL AFTER monthly_rent;
