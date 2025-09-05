-- SQL script to update the PropertyFinishingTypes table

-- Step 1: Disable foreign key checks to allow truncation.
SET FOREIGN_KEY_CHECKS=0;

-- Step 2: Add the FinishingName_ar column if it doesn't exist.
-- Suppress errors if the column already exists.
ALTER TABLE `PropertyFinishingTypes` ADD COLUMN `FinishingName_ar` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;

-- Step 3: Clear the existing data from the table.
TRUNCATE TABLE `PropertyFinishingTypes`;

-- Step 4: Insert the new finishing types with both English and Arabic names.
INSERT INTO `PropertyFinishingTypes` (`FinishingID`, `FinishingName`, `FinishingName_ar`) VALUES
(1, 'Red Brick', 'طوب أحمر'),
(2, 'Semi Finished', 'نصف تشطيب'),
(3, 'Plastered & Framed', 'محارة وحلوق'),
(4, 'Lux', 'لوكس'),
(5, 'Super Lux', 'سوبر لوكس'),
(6, 'Ultra Super Lux', 'الترا سوبر لوكس'),
(7, 'Smart', 'سمارت'),
(8, 'Hotel Finish', 'فندقي');

-- Step 5: Reset the auto-incrementing ID to start from 9 for the next entry.
ALTER TABLE `PropertyFinishingTypes` AUTO_INCREMENT = 9;

-- Step 6: Re-enable foreign key checks.
SET FOREIGN_KEY_CHECKS=1;
