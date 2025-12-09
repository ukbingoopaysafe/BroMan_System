<?php
// migrations/add_currency_column.php

require_once __DIR__ . '/../db.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Starting migration: Add currency column...\n";

// Check if column exists
$checkCol = $conn->query("SHOW COLUMNS FROM Properties LIKE 'currency'");
if ($checkCol->num_rows == 0) {
    echo "Adding currency column...\n";
    // Add ENUM column with default 'EGP'
    $sql = "ALTER TABLE Properties ADD COLUMN currency ENUM('EGP', 'USD') NOT NULL DEFAULT 'EGP'";
    if ($conn->query($sql)) {
        echo "Column 'currency' added successfully.\n";
    } else {
        die("Error adding column: " . $conn->error . "\n");
    }
} else {
    echo "Column 'currency' already exists.\n";
}

$conn->close();
echo "Migration finished successfully.\n";
