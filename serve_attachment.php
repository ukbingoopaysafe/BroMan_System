<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/db.php';
ensure_secure_session_started();

// Only managers/admins can access attachments
if (!can_manage_properties()) {
    // Log debug info to error log to help identify why a request was denied
    error_log(sprintf("serve_attachment: denied attachment id=%s session_id=%s remote=%s user_agent=%s", 
        isset($_GET['id']) ? $_GET['id'] : 'none', session_id(), $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ));
    http_response_code(403);
    // No body text to avoid breaking binary responses
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    exit;
}

$stmt = $conn->prepare('SELECT AttachmentID, PropertyID, FilePath, FileName, FileType FROM property_attachments WHERE AttachmentID = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    http_response_code(404);
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();

$path = __DIR__ . '/' . $row['FilePath'];
if (!file_exists($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$filename = $row['FileName'] ?: basename($path);
$mime = $row['FileType'] ?: mime_content_type($path) ?: 'application/octet-stream';
        $disposition = (strpos($mime, 'image/') === 0) ? 'inline' : 'attachment';

// Activity log for attachment download
try {
    require_once __DIR__ . '/activity_log.php';
    $employee_id = $_SESSION['employee_id'] ?? null;
    if (function_exists('activity_log_event')) {
        activity_log_event($conn, $employee_id, 'PROPERTY_MEDIA_DOWNLOAD', [
            'property_id' => (int)($row['PropertyID'] ?? 0),
            'attachment_id' => (int)($row['AttachmentID'] ?? $id),
            'file_name' => $filename,
            'media_type' => 'attachment',
            'download' => ($disposition === 'attachment')
        ]);
    } elseif (function_exists('log_activity')) {
        log_activity($conn, $employee_id, 'property_media_download', [
            'property_id' => (int)($row['PropertyID'] ?? 0),
            'attachment_id' => (int)($row['AttachmentID'] ?? $id),
            'file_name' => $filename,
            'media_type' => 'attachment',
            'download' => ($disposition === 'attachment')
        ]);
    }
} catch (Exception $e) {
    error_log('serve_attachment log failed: ' . $e->getMessage());
}

// Clear any accidental output buffering that might have started
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Decide disposition: inline for images, attachment otherwise
header_remove();
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
$safeName = str_replace('"', '', basename($filename));
if (strpos($mime, 'image/') === 0) {
    header('Content-Disposition: inline; filename="' . $safeName . '"');
} else {
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
}
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, public');
header('Pragma: public');
header('Content-Length: ' . filesize($path));

// Stream the file in chunks to be robust with large files
$fp = fopen($path, 'rb');
if ($fp === false) {
    error_log("serve_attachment: failed to open file: " . $path);
    http_response_code(500);
    exit;
}
error_log(sprintf("serve_attachment: serving attachment id=%d path=%s session=%s", $id, $path, session_id()));
while (!feof($fp)) {
    echo fread($fp, 8192);
    flush();
}
fclose($fp);
exit;

?>
