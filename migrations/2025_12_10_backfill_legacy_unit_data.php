<?php
// migrations/2025_12_10_backfill_legacy_unit_data.php
// Description: Backfills structured unit data (Bedrooms, Bathrooms, Balconies) from legacy 'unit_design' text.
// This is safe to run multiple times (it only checks for empty fields).

require_once __DIR__ . '/../db.php';

// Check for CLI or Admin session
session_start();
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli && !isset($_SESSION['user_id']) && !isset($_GET['force'])) {
    die("Access Denied. Run via CLI or log in as admin.");
}

echo "Starting Legacy Data Backfill Migration...\n";
if (!$is_cli) echo "<br>";

// 1. Fetch Candidates
// We only target rows where structured data is missing to avoid overwriting manual edits
$sql = "SELECT PropertyID, unit_design AS LegacyText 
        FROM Properties 
        WHERE (Bedrooms IS NULL OR Bedrooms = 0) 
        AND (unit_design IS NOT NULL AND unit_design != '')";

$result = $conn->query($sql);
if (!$result) die("DB Error: " . $conn->error);

$properties = [];
while ($row = $result->fetch_assoc()) {
    $properties[] = $row;
}

echo "Found " . count($properties) . " properties to process.\n";
if (!$is_cli) echo "<br>";

$updated_count = 0;
$stmt = $conn->prepare("UPDATE Properties SET Bedrooms = ?, Bathrooms = ?, balconies = ? WHERE PropertyID = ?");

foreach ($properties as $prop) {
    $text = $prop['LegacyText'];
    $id = $prop['PropertyID'];
    
    $rooms = 0;
    $baths = 0;
    $balconies = 0;

    // --- REUSED REGEX LOGIC FROM ANALYSIS ---
    
    // Rooms
    if (preg_match('/(\d+)\s*(?:غرف|غرفة|نوم|room|bedroom)/iu', $text, $matches)) {
        $rooms = intval($matches[1]);
    } elseif (preg_match('/غرفتين/iu', $text)) {
        $rooms = 2;
    }

    // Baths
    if (preg_match('/(\d+)\s*(?:حمام|bath|toilet)/iu', $text, $matches)) {
        $baths = intval($matches[1]);
    } elseif (preg_match('/حمامين/iu', $text)) {
        $baths = 2;
    } elseif (preg_match('/(?<!\d\s)(?<!\d)حمام/iu', $text)) {
        $baths = 1;
    }

    // Balconies
    if (preg_match('/(\d+)\s*(?:بلكونة|بلكونه|بلكونات|balcony|shorfa|balconies|تراس|terrace)/iu', $text, $matches)) {
        $balconies = intval($matches[1]);
    } elseif (preg_match('/بلكونتين/iu', $text)) {
        $balconies = 2;
    } elseif (preg_match('/(?<!\d\s)(?<!\d)(?:بلكونة|بلكونه|تراس)/iu', $text)) {
        $balconies = 1;
    }

    // Update if we found something new
    if ($rooms > 0 || $baths > 0 || $balconies > 0) {
        $stmt->bind_param("iiii", $rooms, $baths, $balconies, $id);
        if ($stmt->execute()) {
            $updated_count++;
            // echo "Updated ID $id: R=$rooms B=$baths Bal=$balconies\n";
        }
    }
}

echo "Migration Complete. Updated $updated_count properties.\n";
if (!$is_cli) echo "<br>";
?>
