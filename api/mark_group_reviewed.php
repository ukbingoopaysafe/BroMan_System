<?php
/**
 * API Endpoint: Mark Group as Reviewed
 * 
 * This endpoint marks all properties in a duplicate group as "Reviewed" (status = 2).
 * It requires admin/manager permissions.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../session_check.php';
require_once __DIR__ . '/../lang/translate.php';

header('Content-Type: application/json');

// Check authentication and permissions
if (!is_logged_in() || !can_manage_properties()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => t('not_authorized')
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$phone = isset($input['phone']) ? trim($input['phone']) : '';

if (empty($phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => t('phone_required')
    ]);
    exit;
}

// Normalize phone (same logic as in admin_duplicate_properties.php)
function normalize_phone_for_api($phone) {
    if ($phone === null) return null;
    $s = trim($phone);
    if ($s === '') return '';
    // unify egypt code +20/0020 to 0
    if (strpos($s, '+20') === 0) {
        $s = '0' . substr($s, 3);
    } elseif (strpos($s, '0020') === 0) {
        $s = '0' . substr($s, 4);
    }
    // remove symbols and spaces
    $s = str_replace(['+', ' ', '-', '(', ')'], '', $s);
    return $s;
}

$normalized_phone = normalize_phone_for_api($phone);

// Get all property IDs in this group using the view
$sql = "SELECT DISTINCT PropertyID 
        FROM v_property_phones_normalized 
        WHERE normalized_phone = ?";

if (!$stmt = $conn->prepare($sql)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param('s', $normalized_phone);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$property_ids = [];
while ($row = $result->fetch_assoc()) {
    $property_ids[] = (int)$row['PropertyID'];
}
$stmt->close();

if (empty($property_ids)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => t('group_not_found')
    ]);
    exit;
}

// Check current status of the group
// If all properties are reviewed (status = 2), toggle to needs review (status = 1)
// Otherwise, toggle to reviewed (status = 2)
$ids_placeholders = implode(',', array_fill(0, count($property_ids), '?'));
$ids_types = str_repeat('i', count($property_ids));

$sql_check = "SELECT duplicate_status, COUNT(*) AS cnt 
              FROM properties 
              WHERE PropertyID IN ($ids_placeholders)
              GROUP BY duplicate_status";

if (!$stmt_check = $conn->prepare($sql_check)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$stmt_check->bind_param($ids_types, ...$property_ids);
if (!$stmt_check->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $stmt_check->error
    ]);
    $stmt_check->close();
    exit;
}

$result_check = $stmt_check->get_result();
$status_counts = [];
$total_properties = 0;
while ($row = $result_check->fetch_assoc()) {
    $status = (int)$row['duplicate_status'];
    $count = (int)$row['cnt'];
    $status_counts[$status] = $count;
    $total_properties += $count;
}
$stmt_check->close();

// Determine new status: if all are reviewed (2), toggle to needs review (1), otherwise toggle to reviewed (2)
$reviewed_count = $status_counts[2] ?? 0;
$new_status = ($reviewed_count === $total_properties && $total_properties > 0) ? 1 : 2;

// Update all properties in this group to the new status
$sql_update = "UPDATE properties 
              SET duplicate_status = ? 
              WHERE PropertyID IN ($ids_placeholders)";

if (!$stmt = $conn->prepare($sql_update)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param('i' . $ids_types, $new_status, ...$property_ids);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$affected_rows = $stmt->affected_rows;
$stmt->close();

// Log the action
if (function_exists('log_activity')) {
    $employee_id = $_SESSION['employee_id'] ?? 0;
    log_activity($employee_id, 'mark_group_reviewed', [
        'phone' => $normalized_phone,
        'properties_count' => count($property_ids)
    ]);
}

$status_label = ($new_status === 2) ? t('reviewed') : t('needs_review');

echo json_encode([
    'success' => true,
    'message' => ($new_status === 2) ? t('group_marked_reviewed') : t('group_marked_needs_review'),
    'properties_updated' => $affected_rows,
    'new_status' => $new_status,
    'status_label' => $status_label
]);

