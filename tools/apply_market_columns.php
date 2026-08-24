<?php
require_once __DIR__ . '/../db.php';

function addColumnIfNotExists($conn, $table, $column, $definition) {
    global $dbname;
    $dbName = $dbname ?: 'broman_db';
    $check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'");
    if ($check && $check->num_rows == 0) {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if ($conn->query($sql)) {
            echo "Added `$column` to `$table` successfully.\n";
        } else {
            echo "Failed adding `$column` to `$table`: " . $conn->error . "\n";
        }
    } else {
        echo "Column `$column` in `$table` already exists.\n";
    }
}

echo "Applying Schema Updates...\n";

// 1. PropertyPaymentPlans columns
addColumnIfNotExists($conn, 'PropertyPaymentPlans', 'plan_title', 'VARCHAR(255) NULL AFTER PropertyID');
addColumnIfNotExists($conn, 'PropertyPaymentPlans', 'installment_type', "VARCHAR(50) DEFAULT 'quarterly' AFTER installment_years");
addColumnIfNotExists($conn, 'PropertyPaymentPlans', 'delivery_payment_percentage', 'DECIMAL(5,2) NULL AFTER installment_type');

// 2. Properties columns
addColumnIfNotExists($conn, 'Properties', 'clubhouse_fee', 'VARCHAR(255) NULL AFTER maintenance_percentage');
addColumnIfNotExists($conn, 'Properties', 'garage_fee', 'VARCHAR(255) NULL AFTER clubhouse_fee');
addColumnIfNotExists($conn, 'Properties', 'transfer_fee_percentage', 'DECIMAL(5,2) NULL AFTER garage_fee');
addColumnIfNotExists($conn, 'Properties', 'maintenance_payment_details', 'VARCHAR(255) NULL AFTER transfer_fee_percentage');

echo "Schema update completed.\n";
