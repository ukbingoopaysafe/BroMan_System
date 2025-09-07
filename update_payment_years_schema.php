<?php
// Script to update payment_years column from int(11) to DECIMAL(5,2)
$conn = new mysqli('localhost', 'root', '', 'broman_db');

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// SQL to update the payment_years column
$sql = 'ALTER TABLE Properties MODIFY payment_years DECIMAL(5,2) NOT NULL';

// Execute query
if ($conn->query($sql) === TRUE) {
    echo "Table Properties modified successfully\n";
} else {
    echo "Error modifying table: " . $conn->error . "\n";
}

$conn->close();
?>