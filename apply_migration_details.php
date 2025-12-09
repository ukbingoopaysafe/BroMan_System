<?php
require_once 'db.php';

$sql_file = __DIR__ . '/migrations/2025_12_09_add_property_details.sql';
$sql_content = file_get_contents($sql_file);

// Split by semicolon to execute strictly one by one, although multi_query is an option
$queries = array_filter(array_map('trim', explode(';', $sql_content)));

foreach ($queries as $query) {
    if (empty($query)) continue;
    if ($conn->query($query) === TRUE) {
        echo "Successfully executed: " . substr($query, 0, 50) . "...\n";
    } else {
        echo "Error executing: " . substr($query, 0, 50) . "... -> " . $conn->error . "\n";
    }
}

$conn->close();
?>
