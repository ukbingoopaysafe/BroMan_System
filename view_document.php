<?php
// Server-side embedded PDF viewer: read the PDF file and embed as data: URI
session_start();
require_once __DIR__ . '/lang/translate.php';
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo 'Invalid document id';
    exit;
}

$sql = "SELECT document_id, property_id, file_path, file_name, file_size FROM property_documents WHERE document_id = ? LIMIT 1";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $doc = $res->fetch_assoc();
        $stmt->close();
    } else {
        $stmt->close();
        echo 'Document not found';
        exit;
    }
} else {
    echo 'Server error';
    exit;
}

$filePath = __DIR__ . DIRECTORY_SEPARATOR . $doc['file_path'];
$real = realpath($filePath);
$uploadsBase = realpath(__DIR__ . '/uploads');
if ($real === false || strpos($real, $uploadsBase) !== 0 || !is_file($real)) {
    echo 'File not available';
    exit;
}

$filesize = filesize($real);
$filename = $doc['file_name'] ?: basename($real);

// Read file and base64 encode (OK for modest file sizes). If file too big, you may opt for streaming.
$content = file_get_contents($real);
if ($content === false) {
    echo 'Failed to read file';
    exit;
}
$b64 = base64_encode($content);
$dataUri = 'data:application/pdf;base64,' . $b64;

$pageTitle = t('view_document') ?? 'View Document';
?>
<!doctype html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="/images/Logo%20Broman.svg">
    <link rel="apple-touch-icon" href="/images/Logo%20Broman.svg">
    <style>
        body, html { height: 100%; margin: 0; }
        .viewer { height: 100%; display: flex; flex-direction: column; }
        .viewer-body { flex: 1 1 auto; }
        iframe { width: 100%; height: 100vh; border: none; }
    </style>
</head>
<body>
    <div class="viewer">
        <div class="viewer-body" style="flex:1 1 auto;">
            <iframe id="pdfFrame"></iframe>
        </div>
    </div>
    <script>
    // Create blob from server-provided base64 string to avoid data: URI length limits
    (function(){
        try {
            var b64 = "<?php echo $b64; ?>";
            // Decode base64 to binary
            var binary = atob(b64);
            var len = binary.length;
            var bytes = new Uint8Array(len);
            for (var i = 0; i < len; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            var blob = new Blob([bytes], {type: 'application/pdf'});
            var url = URL.createObjectURL(blob);
            document.getElementById('pdfFrame').src = url;
        } catch (e) {
            console.error('Failed to prepare PDF blob', e);
            document.getElementById('pdfFrame').remove();
            var err = document.createElement('div');
            err.style.padding = '20px';
            err.textContent = 'Failed to load document';
            document.querySelector('.viewer-body').appendChild(err);
        }
    })();
    </script>
</body>
</html>
