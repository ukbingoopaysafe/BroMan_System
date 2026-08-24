<?php
require_once __DIR__ . '/../db.php';

echo "Running migration: 2026_08_23_enhance_primary_features.sql...\n";

// 1. Add columns to Properties if they don't exist
$cols_to_add = [
    'company_phone_2' => "ALTER TABLE `Properties` ADD COLUMN `company_phone_2` varchar(50) DEFAULT NULL AFTER `company_phone`",
    'compound_area_unit' => "ALTER TABLE `Properties` ADD COLUMN `compound_area_unit` enum('faddan','sqm') NOT NULL DEFAULT 'faddan' AFTER `compound_area`",
    'payment_plans_notes' => "ALTER TABLE `Properties` ADD COLUMN `payment_plans_notes` text DEFAULT NULL AFTER `payment_years`"
];

foreach ($cols_to_add as $col => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM `Properties` LIKE '$col'");
    if ($check && $check->num_rows > 0) {
        echo "Column $col already exists.\n";
    } else {
        if ($conn->query($sql)) {
            echo "Added column $col successfully.\n";
        } else {
            echo "Error adding column $col: " . $conn->error . "\n";
        }
    }
}

// 2. Create PropertyPaymentPlans table
$sql_table = "CREATE TABLE IF NOT EXISTS `PropertyPaymentPlans` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql_table)) {
    echo "PropertyPaymentPlans table ready.\n";
} else {
    echo "Error creating PropertyPaymentPlans table: " . $conn->error . "\n";
}

echo "Migration finished.\n";
