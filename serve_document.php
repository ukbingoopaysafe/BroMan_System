<?php
// serve_document.php
// Securely serve documents (PDF) for inline viewing or download
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$download = isset($_GET['download']) && ($_GET['download'] == '1' || strtolower($_GET['download']) === 'true');

if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid document id';
    exit;
}

$sql = "SELECT document_id, property_id, file_path, file_name FROM property_documents WHERE document_id = ? LIMIT 1";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $doc = $res->fetch_assoc();
        $stmt->close();
    } else {
        $stmt->close();
        http_response_code(404);
        echo 'Document not found';
        exit;
    }
} else {
    http_response_code(500);
    echo 'Server error';
    exit;
}

$filePath = __DIR__ . DIRECTORY_SEPARATOR . $doc['file_path'];
// Basic safety: ensure file exists and is under the uploads directory
$real = realpath($filePath);
$uploadsBase = realpath(__DIR__ . '/uploads');
if ($real === false || strpos($real, $uploadsBase) !== 0 || !is_file($real)) {
    http_response_code(404);
    echo 'File not available';
    exit;
}

$fsize = filesize($real);
$fname = basename($real);

// Activity log for document download
try {
    $employee_id = $_SESSION['employee_id'] ?? null;
    if ($download && function_exists('activity_log_event')) {
        activity_log_event($conn, $employee_id, 'PROPERTY_MEDIA_DOWNLOAD', [
            'property_id' => (int)($doc['property_id'] ?? 0),
            'document_id' => (int)($doc['document_id'] ?? 0),
            'file_name' => $doc['file_name'] ?? $fname,
            'media_type' => 'document',
            'download' => true
        ]);
    } elseif ($download && function_exists('log_activity')) {
        log_activity($conn, $employee_id, 'property_media_download', [
            'property_id' => (int)($doc['property_id'] ?? 0),
            'document_id' => (int)($doc['document_id'] ?? 0),
            'file_name' => $doc['file_name'] ?? $fname,
            'media_type' => 'document',
            'download' => true
        ]);
    }
} catch (Exception $e) {
    error_log('serve_document log failed: ' . $e->getMessage());
}

// Force appropriate headers for PDF
header_remove();
header('Content-Type: application/pdf');
if ($download) {
    header('Content-Disposition: attachment; filename="' . $doc['file_name'] . '"');
} else {
    header('Content-Disposition: inline; filename="' . $doc['file_name'] . '"');
}
header('Content-Length: ' . $fsize);
// Prevent caching when needed, but allow browser caching for performance
header('Cache-Control: public, max-age=86400');

// Output file
readfile($real);
exit;

?>
