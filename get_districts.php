<?php
// get_districts.php - API endpoint to fetch districts for a governorate
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';

// Allow any logged-in user to access this
if (!is_logged_in()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$governorate = isset($_GET['governorate']) ? trim($_GET['governorate']) : '';

if (empty($governorate)) {
    echo json_encode([]);
    exit;
}

$districts = [];
$sql = "SELECT DISTINCT district FROM Properties WHERE governorate = ? AND district IS NOT NULL AND district != '' ORDER BY district";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $governorate);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $districts[] = $row['district'];
        }
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($districts);
?>
