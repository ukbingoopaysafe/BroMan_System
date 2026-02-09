<?php
/*
Manual Test Checklist (WhatsApp Sharing)
1) Share details opens WhatsApp (formatted message).
2) Share location opens WhatsApp (map link only).
3) No header warnings.
4) Logging fires once per click.
*/
ob_start();
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';

ensure_secure_session_started();
check_permission('view_properties');

$type = $_GET['type'] ?? '';
$property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
$url = trim((string)($_GET['url'] ?? ''));

if ($property_id <= 0 || $url === '') {
    http_response_code(400);
    exit;
}

// Allow only WhatsApp share URLs
$url_decoded = rawurldecode($url);
$parsed = parse_url($url_decoded);
$host = $parsed['host'] ?? '';
$scheme = $parsed['scheme'] ?? '';

$allowed_hosts = ['wa.me', 'api.whatsapp.com', 'www.whatsapp.com'];
if (!in_array(strtolower($host), $allowed_hosts, true) || !in_array(strtolower($scheme), ['https'], true)) {
    http_response_code(400);
    exit;
}
// CR/LF must be blocked ONLY in encoded input to prevent header injection
// Newlines after decode are expected because WhatsApp message uses \n
if (preg_match("/[\r\n]/", $url)) {
    http_response_code(400);
    exit;
}

function detect_device_type(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/Android|iPhone|iPad|iPod|Windows Phone|Mobile/i', $ua) ? 'mobile' : 'desktop';
}

// Log share event (no UI changes, just redirect)
try {
    $employee_id = $_SESSION['employee_id'] ?? null;
    $device_type = detect_device_type();
    if (function_exists('activity_log_event')) {
        if ($type === 'details') {
            activity_log_event($conn, $employee_id, 'PROPERTY_SHARE_DETAILS', [
                'property_id' => $property_id,
                'destination' => 'whatsapp',
                'device_type' => $device_type
            ]);
        } elseif ($type === 'location') {
            activity_log_event($conn, $employee_id, 'PROPERTY_SHARE_LOCATION', [
                'property_id' => $property_id,
                'destination' => 'whatsapp',
                'device_type' => $device_type
            ]);
        }
    } elseif (function_exists('log_activity')) {
        if ($type === 'details') {
            log_activity($conn, $employee_id, 'property_share_details', [
                'property_id' => $property_id,
                'destination' => 'whatsapp',
                'device_type' => $device_type
            ]);
        } elseif ($type === 'location') {
            log_activity($conn, $employee_id, 'property_share_location', [
                'property_id' => $property_id,
                'destination' => 'whatsapp',
                'device_type' => $device_type
            ]);
        }
    }
} catch (Exception $e) {
    error_log('share_redirect log failed: ' . $e->getMessage());
}

if (ob_get_level() > 0) {
    @ob_end_clean();
}
header('Location: ' . $url, true, 302);
exit;
