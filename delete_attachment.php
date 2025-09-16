<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/db.php';
ensure_secure_session_started();

header('Content-Type: application/json; charset=utf-8');

// Only managers or admins can delete attachments
if (!can_manage_properties()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
$csrf = $_POST['csrf_token'] ?? null;
if (!validateCSRFToken($csrf)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if ($attachment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid attachment id']);
    exit;
}

$sel = $conn->prepare("SELECT FilePath FROM property_attachments WHERE AttachmentID = ?");
if (!$sel) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}
$sel->bind_param('i', $attachment_id);
$sel->execute();
$res = $sel->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}
$row = $res->fetch_assoc();
$sel->close();

$filePath = __DIR__ . '/' . $row['FilePath'];

// Delete DB row first
$del = $conn->prepare("DELETE FROM property_attachments WHERE AttachmentID = ?");
if ($del) {
    $del->bind_param('i', $attachment_id);
    if ($del->execute()) {
        // Attempt to delete file
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Delete failed']);
exit;

?>
