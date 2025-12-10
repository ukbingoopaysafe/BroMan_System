<?php
// migrations/add_friendly_id.php
// Run this script to add the friendly_id column and backfill existing data.

require_once __DIR__ . '/../db.php';

// Ensure DB connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Starting migration: Add friendly_id...\n";

// 1. Add Column
$checkCol = $conn->query("SHOW COLUMNS FROM Properties LIKE 'friendly_id'");
if ($checkCol->num_rows == 0) {
    echo "Adding friendly_id column...\n";
    if ($conn->query("ALTER TABLE Properties ADD COLUMN friendly_id INT DEFAULT NULL")) {
        echo "Column added successfully.\n";
    } else {
        die("Error adding column: " . $conn->error . "\n");
    }
} else {
    echo "Column friendly_id already exists.\n";
}

// 2. Add Index
$checkIndex = $conn->query("SHOW INDEX FROM Properties WHERE Key_name = 'idx_friendly_id'");
if ($checkIndex->num_rows == 0) {
    echo "Adding index idx_friendly_id...\n";
    try {
        $conn->query("CREATE INDEX idx_friendly_id ON Properties(listing_type, friendly_id)");
        echo "Index added successfully.\n";
    } catch (Exception $e) {
        echo "Index creation warning: " . $e->getMessage() . "\n";
    }
} else {
    echo "Index idx_friendly_id already exists.\n";
}

// 3. Backfill
echo "Starting backfill process...\n";

// Get all properties ordered by date to ensure sequential logical numbering
// Use DateAdded for ordering. older properties get lower IDs.
$sql = "SELECT PropertyID, listing_type, friendly_id FROM Properties ORDER BY DateAdded ASC, PropertyID ASC";
$result = $conn->query($sql);

if ($result) {
    $counters = []; // ['Resale' => 0, 'Rental' => 0, 'Primary' => 0]
    $updates = 0;

    $stmtUpdate = $conn->prepare("UPDATE Properties SET friendly_id = ? WHERE PropertyID = ?");

    while ($row = $result->fetch_assoc()) {
        $type = $row['listing_type'] ?? 'Unknown';
        if (empty($type)) $type = 'Unknown';
        
        // Initialize counter if new type encountered
        if (!isset($counters[$type])) {
            $counters[$type] = 0;
        }
        
        $counters[$type]++;
        $newId = $counters[$type];

        // Only update if null or different (though usually we want to enforce the sequence)
        // If it's already set, we might want to respect it, BUT for true backfill we might want to fill nulls.
        // The user asked for "separate sequential IDs". 
        // If we strictly follow the logic, we should set it if it's NULL.
        // If we re-run this on production, it should populate new fields.
        if (is_null($row['friendly_id'])) {
            $stmtUpdate->bind_param("ii", $newId, $row['PropertyID']);
            $stmtUpdate->execute();
            $updates++;
        }
    }
    
    echo "Backfill complete. Updated $updates records.\n";
    echo "Final Counters:\n";
    print_r($counters);

} else {
    echo "Error fetching properties: " . $conn->error . "\n";
}

$conn->close();
echo "Migration finished successfully.\n";
