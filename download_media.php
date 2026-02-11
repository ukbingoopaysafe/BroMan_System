<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/db.php';

ensure_secure_session_started();
check_permission('view_properties');

$property_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($property_id <= 0) {
    http_response_code(400);
    echo 'Invalid property id';
    exit;
}

// Validate listing context and access
$current_mode = require_listing_context();
$stmt = $conn->prepare("SELECT PropertyID, listing_type FROM Properties WHERE PropertyID = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo 'Server error';
    exit;
}
$stmt->bind_param("i", $property_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows !== 1) {
    http_response_code(404);
    echo 'Property not found';
    exit;
}
$property = $res->fetch_assoc();
$stmt->close();

if (!is_mode_allowed_for_role($property['listing_type'])) {
    http_response_code(403);
    exit;
}
if (!is_manager() && $property['listing_type'] !== $current_mode) {
    http_response_code(403);
    exit;
}

function build_absolute_url($path) {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '\\/');
    $path = ltrim($path, '/');
    return $scheme . '://' . $host . $base . '/' . $path;
}

// Collect allowed media files
$files = [];
$uploadsBase = realpath(__DIR__ . '/uploads');

$img_stmt = $conn->prepare("SELECT file_path FROM property_images WHERE property_id = ?");
if ($img_stmt) {
    $img_stmt->bind_param('i', $property_id);
    $img_stmt->execute();
    $img_res = $img_stmt->get_result();
    while ($row = $img_res->fetch_assoc()) {
        $files[] = ['path' => $row['file_path'], 'dir' => 'images'];
    }
    $img_stmt->close();
}

$vid_stmt = $conn->prepare("SELECT VideoPath FROM property_videos WHERE PropertyID = ?");
if ($vid_stmt) {
    $vid_stmt->bind_param('i', $property_id);
    $vid_stmt->execute();
    $vid_res = $vid_stmt->get_result();
    while ($row = $vid_res->fetch_assoc()) {
        $files[] = ['path' => $row['VideoPath'], 'dir' => 'videos'];
    }
    $vid_stmt->close();
}

$doc_stmt = $conn->prepare("SELECT document_id, file_path, file_name FROM property_documents WHERE property_id = ?");
if ($doc_stmt) {
    $doc_stmt->bind_param('i', $property_id);
    $doc_stmt->execute();
    $doc_res = $doc_stmt->get_result();
    while ($row = $doc_res->fetch_assoc()) {
        $files[] = [
            'path' => $row['file_path'],
            'dir' => 'documents',
            'name' => $row['file_name'] ?? '',
            'document_id' => $row['document_id'] ?? 0
        ];
    }
    $doc_stmt->close();
}

// Log download_media action for OIS monitoring
if (!empty($files)) {
    require_once __DIR__ . '/activity_log.php';
    $emp_id = $_SESSION['employee_id'] ?? null;
    log_activity($conn, $emp_id, 'download_media', json_encode([
        'property_id' => $property_id,
        'file_count' => count($files),
        'listing_type' => $property['listing_type'] ?? ''
    ]));
}

if (empty($files)) {
    http_response_code(404);
    echo 'No media files found';
    exit;
}

// Build download links (non-zip)
$download_items = [];
foreach ($files as $f) {
    $rel = trim((string)($f['path'] ?? ''));
    if ($rel === '') continue;

    $full = realpath(__DIR__ . DIRECTORY_SEPARATOR . $rel);
    if ($full === false || $uploadsBase === false) continue;
    if (strpos($full, $uploadsBase) !== 0 || !is_file($full)) continue;

    $label = '';
    if (!empty($f['name'])) {
        $label = basename($f['name']);
    }
    if ($label === '') {
        $label = basename($full);
    }

    $url = '';
    if (($f['dir'] ?? '') === 'documents' && !empty($f['document_id'])) {
        $url = build_absolute_url('serve_document.php?id=' . intval($f['document_id']) . '&download=1');
    } else {
        $url = build_absolute_url($rel);
    }

    if ($url !== '') {
        $download_items[] = [
            'url' => $url,
            'label' => $label,
            'type' => $f['dir'] ?? 'media'
        ];
    }
}

if (empty($download_items)) {
    http_response_code(404);
    echo 'No media files available';
    exit;
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحميل وسائط العقار</title>
    <link rel="stylesheet" href="modern_styles.css">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; padding: 24px; background:#f6f7fb; }
        .card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
        .btn { padding:10px 14px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
        .btn-primary { background:#25D366; color:#fff; }
        .btn-secondary { background:#eef2f7; color:#1b1f24; }
        .list { margin:0; padding:0; list-style:none; }
        .item { padding:10px 0; border-bottom:1px solid #f0f0f0; }
        .item:last-child { border-bottom:none; }
        .item a { color:#0a58ca; text-decoration:none; word-break:break-all; }
    </style>
</head>
<body>
    <div class="card">
        <div class="actions">
            <button type="button" class="btn btn-primary" id="download-all">تحميل كل الوسائط</button>
            <a class="btn btn-secondary" href="view_property.php?id=<?php echo (int)$property_id; ?>">رجوع لتفاصيل العقار</a>
        </div>
        <ul class="list" id="media-list">
            <?php foreach ($download_items as $item): ?>
                <li class="item">
                    <a href="<?php echo htmlspecialchars($item['url']); ?>" download>
                        <?php echo htmlspecialchars($item['label']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <script>
    document.getElementById('download-all').addEventListener('click', function () {
        const links = document.querySelectorAll('#media-list a');
        let i = 0;
        const downloadNext = () => {
            if (i >= links.length) return;
            const a = links[i++];
            const evt = new MouseEvent('click', { bubbles: true, cancelable: true, view: window });
            a.dispatchEvent(evt);
            setTimeout(downloadNext, 250);
        };
        downloadNext();
    });
    </script>
</body>
</html>
