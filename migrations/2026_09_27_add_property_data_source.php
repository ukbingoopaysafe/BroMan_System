<?php

require_once __DIR__ . '/../db.php';

$column = $conn->query("SHOW COLUMNS FROM Properties LIKE 'property_data_source'");
if ($column === false) {
    fwrite(STDERR, "Could not inspect Properties: " . $conn->error . PHP_EOL);
    exit(1);
}

if ($column->num_rows === 0 && !$conn->query("ALTER TABLE Properties ADD COLUMN property_data_source VARCHAR(255) NULL AFTER EmployeeID")) {
    fwrite(STDERR, "Could not add property_data_source: " . $conn->error . PHP_EOL);
    exit(1);
}

echo $column->num_rows === 0 ? "property_data_source added" . PHP_EOL : "property_data_source already exists" . PHP_EOL;
