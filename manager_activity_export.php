<?php
// Export endpoint disabled
// Return 403 for any attempt to access this endpoint. Keep the file in place to avoid broken links.
header('HTTP/1.1 403 Forbidden');
// Optional logging for auditing
error_log('Blocked export attempt to manager_activity_export.php from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Friendly message (HTML if browser asked for it)
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if (strpos($accept, 'text/html') !== false) {
    ?><!doctype html>
    <html lang="en">
    <head><meta charset="utf-8"><title>Forbidden</title></head>
    <body style="font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:40px;color:#333;">
        <h1>403 Forbidden</h1>
        <p>Export feature is disabled.</p>
    </body>
    </html>
    <?php
} else {
    echo '403 Forbidden - Export disabled.';
}
exit;
