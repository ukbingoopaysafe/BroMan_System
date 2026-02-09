<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';

ensure_secure_session_started();
check_permission('view_properties');

$property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
$path = $_GET['path'] ?? '';

if ($property_id <= 0 || $path === '') {
    http_response_code(400);
    exit;
}

$path = rawurldecode($path);
$full = realpath(__DIR__ . DIRECTORY_SEPARATOR . $path);
$uploadsBase = realpath(__DIR__ . '/uploads');
if ($full === false || $uploadsBase === false || strpos($full, $uploadsBase) !== 0 || !is_file($full)) {
    http_response_code(404);
    exit;
}

// Verify file belongs to property (image or video)
$is_valid = false;
$media_type = null;

$stmt = $conn->prepare("SELECT 1 FROM property_images WHERE property_id = ? AND file_path = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('is', $property_id, $path);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $is_valid = true;
        $media_type = 'image';
    }
    $stmt->close();
}

if (!$is_valid) {
    $stmt = $conn->prepare("SELECT 1 FROM property_videos WHERE PropertyID = ? AND VideoPath = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('is', $property_id, $path);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $is_valid = true;
            $media_type = 'video';
        }
        $stmt->close();
    }
}

if (!$is_valid) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($full) ?: 'application/octet-stream';
$filename = basename($full);

// Activity log for media download
try {
    $employee_id = $_SESSION['employee_id'] ?? null;
    if (function_exists('activity_log_event')) {
        activity_log_event($conn, $employee_id, 'PROPERTY_MEDIA_DOWNLOAD', [
            'property_id' => $property_id,
            'file_name' => $filename,
            'media_type' => $media_type,
            'download' => true
        ]);
    } elseif (function_exists('log_activity')) {
        log_activity($conn, $employee_id, 'property_media_download', [
            'property_id' => $property_id,
            'file_name' => $filename,
            'media_type' => $media_type,
            'download' => true
        ]);
    }
} catch (Exception $e) {
    error_log('serve_media log failed: ' . $e->getMessage());
}

header_remove();
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . filesize($full));
header('Cache-Control: public, max-age=86400');
readfile($full);
exit;
