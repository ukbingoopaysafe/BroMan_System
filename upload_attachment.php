<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/db.php';
ensure_secure_session_started();

header('Content-Type: application/json; charset=utf-8');

// Only managers or admins can upload attachments
if (!can_manage_properties()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Validate CSRF
$csrf = $_POST['csrf_token'] ?? null;
if (!validateCSRFToken($csrf)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
if ($property_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid property id']);
    exit;
}

// Allowed extensions and max size
$allowed_ext = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','txt','csv','odt'];
$max_size = 10 * 1024 * 1024; // 10 MB


$upload_dir = __DIR__ . '/uploads/attachments/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Do not create per-property directories for attachments here; keep files in the top-level attachments folder

$results = [];
if (!isset($_FILES['attachments'])) {
    echo json_encode(['success' => false, 'message' => 'No files uploaded']);
    exit;
}

foreach ($_FILES['attachments']['name'] as $index => $origName) {
    $tmpName = $_FILES['attachments']['tmp_name'][$index];
    $error = $_FILES['attachments']['error'][$index];
    $size = $_FILES['attachments']['size'][$index];

    if ($error !== UPLOAD_ERR_OK) {
        $results[] = ['file' => $origName, 'success' => false, 'message' => 'Upload error code ' . $error];
        continue;
    }

    if ($size > $max_size) {
        $results[] = ['file' => $origName, 'success' => false, 'message' => 'File too large'];
        continue;
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        $results[] = ['file' => $origName, 'success' => false, 'message' => 'Invalid file type'];
        continue;
    }

    // Generate filename using same pattern as add_* pages: property_<id>_attach_<random>.<ext>
    $filename = 'property_' . $property_id . '_attach_' . bin2hex(random_bytes(8)) . '.' . $ext;
    // Place file in the top-level attachments directory to match add_* behavior
    $destPath = $upload_dir . $filename;

    if (!move_uploaded_file($tmpName, $destPath)) {
        $results[] = ['file' => $origName, 'success' => false, 'message' => 'Failed to move file'];
        continue;
    }

    // Insert DB record
    $fileType = mime_content_type($destPath) ?: null;
    $fileSize = filesize($destPath);
    $employeeId = $_SESSION['employee_id'] ?? null;

    $ins = $conn->prepare("INSERT INTO property_attachments (PropertyID, FileName, FilePath, FileType, FileSize, EmployeeID) VALUES (?, ?, ?, ?, ?, ?)");
    if ($ins) {
        $relativePath = 'uploads/attachments/' . $filename;
        $ins->bind_param('isssii', $property_id, $origName, $relativePath, $fileType, $fileSize, $employeeId);
        if ($ins->execute()) {
            $results[] = ['file' => $origName, 'success' => true, 'attachment_id' => $ins->insert_id, 'path' => $relativePath];
        } else {
            // cleanup file on failure
            @unlink($destPath);
            $results[] = ['file' => $origName, 'success' => false, 'message' => 'DB insert failed'];
        }
        $ins->close();
    } else {
        @unlink($destPath);
        $results[] = ['file' => $origName, 'success' => false, 'message' => 'DB prepare failed'];
    }
}

echo json_encode(['success' => true, 'results' => $results]);
exit;

?>
