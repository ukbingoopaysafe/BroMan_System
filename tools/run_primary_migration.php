<?php
require_once dirname(__DIR__) . '/db.php';

$columns_to_add = [
    'company_phone' => "VARCHAR(50) DEFAULT NULL AFTER company_name",
    'sales_director_name' => "VARCHAR(255) DEFAULT NULL AFTER company_phone",
    'sales_director_phone' => "VARCHAR(50) DEFAULT NULL AFTER sales_director_name",
    'operation_manager_name' => "VARCHAR(255) DEFAULT NULL AFTER sales_director_phone",
    'operation_manager_phone' => "VARCHAR(50) DEFAULT NULL AFTER operation_manager_name",
    'min_price' => "DECIMAL(15,2) DEFAULT NULL AFTER loading_percentage",
    'max_price' => "DECIMAL(15,2) DEFAULT NULL AFTER min_price",
    'min_meter_price' => "DECIMAL(10,2) DEFAULT NULL AFTER meter_price_installments",
    'max_meter_price' => "DECIMAL(10,2) DEFAULT NULL AFTER min_meter_price",
    'cash_discount_percentage' => "DECIMAL(5,2) DEFAULT NULL AFTER down_payment_percentage",
    'maintenance_percentage' => "DECIMAL(5,2) DEFAULT NULL AFTER cash_discount_percentage",
    'phase_name' => "VARCHAR(255) DEFAULT NULL AFTER ProjectName",
    'delivery_status' => "VARCHAR(100) DEFAULT NULL AFTER delivery_date",
    'construction_status' => "VARCHAR(100) DEFAULT NULL AFTER delivery_status",
    'offer_title' => "VARCHAR(255) DEFAULT NULL AFTER elevators",
    'offer_description' => "TEXT DEFAULT NULL AFTER offer_title",
    'offer_start_date' => "DATE DEFAULT NULL AFTER offer_description",
    'offer_end_date' => "DATE DEFAULT NULL AFTER offer_start_date",
    'brochure_url' => "VARCHAR(500) DEFAULT NULL AFTER offer_end_date"
];

// Check existing columns
$existing = [];
$res = $conn->query("SHOW COLUMNS FROM properties");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $existing[] = strtolower($row['Field']);
    }
}

$added = 0;
foreach ($columns_to_add as $col => $def) {
    if (!in_array(strtolower($col), $existing, true)) {
        $sql = "ALTER TABLE properties ADD COLUMN `$col` $def";
        if ($conn->query($sql)) {
            echo "Added column: $col\n";
            $added++;
        } else {
            echo "Error adding $col: " . $conn->error . "\n";
        }
    } else {
        echo "Column already exists: $col\n";
    }
}

echo "Migration finished successfully. Added $added columns.\n";
