<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';

function log_property_visit($property_id) {
    global $conn;
    
    error_log("Starting log_property_visit for property_id: " . $property_id);
    error_log("Session data: " . print_r($_SESSION, true));
    
    if (!isset($_SESSION['employee_id'])) {
        error_log("No employee_id in session");
        return false;
    }
    
    $employee_id = $_SESSION['employee_id'];
    error_log("Employee ID from session: " . $employee_id);
    
    // Debounce repeated views within the same session
    if (!isset($_SESSION['last_property_view'])) {
        $_SESSION['last_property_view'] = [];
    }
    $now = time();
    $last = $_SESSION['last_property_view'][$property_id] ?? 0;
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $skip_referrers = ['download_media.php', 'serve_media.php', 'serve_document.php', 'serve_attachment.php', 'share_redirect.php'];
    foreach ($skip_referrers as $skip) {
        if ($ref && strpos($ref, $skip) !== false) {
            return true;
        }
    }
    if ($last && ($now - (int)$last) < 120) {
        return true;
    }
    $_SESSION['last_property_view'][$property_id] = $now;

    // Log the visit (use centralized logging + final saved data snapshot)
    $details = [
        'property_id' => (int)$property_id,
        'source' => 'view_property'
    ];
    error_log("Property visit details: " . print_r($details, true));

    error_log("Attempting to log activity for employee_id: " . $employee_id);
    if (function_exists('activity_log_event')) {
        $result = activity_log_event($conn, $employee_id, 'PROPERTY_VIEW', $details);
    } else {
        $result = log_activity($conn, $employee_id, 'property_visit', $details);
    }
    error_log("Log activity result: " . ($result ? "success" : "failed"));
    
    return true;
}
