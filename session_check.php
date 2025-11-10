<?php
// Include configuration
require_once 'config.php';
require_once __DIR__ . '/security.php';
ensure_secure_session_started();

// Start session if not already started
// Session is already started securely above

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

// Initialize session timestamps if not set
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

$current_time = time();

// Define session timeout (30 minutes)
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
}

// Helper: basic log rotation to prevent unlimited growth
function rotate_session_log_if_needed() {
    $logFile = __DIR__ . '/session_activity.log';
    $maxBytes = 1024 * 1024; // 1 MB
    if (file_exists($logFile) && filesize($logFile) > $maxBytes) {
        $suffix = date('Ymd_His');
        @rename($logFile, __DIR__ . "/session_activity_$suffix.log");
    }
}

// Check if session has timed out
if (($current_time - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    // Log session expiration
    rotate_session_log_if_needed();
    $log_msg = date('Y-m-d H:i:s') . " | Session expired for user: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown') . " | IP: " . $_SERVER['REMOTE_ADDR'] . " | Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";
    file_put_contents(__DIR__ . '/session_activity.log', $log_msg, FILE_APPEND);
    // Session has expired - destroy it and redirect to login
    session_destroy();
    header("Location: login.php?timeout=idle");
    exit;
}

// Check for inactivity timeout (30 minutes)
if (($current_time - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}

// Check for absolute timeout
if (($current_time - $_SESSION['created_at']) > SESSION_ABSOLUTE_TIMEOUT) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=absolute");
    exit;
}

// Check for idle timeout
if (($current_time - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=idle");
    exit;
}

// Update last activity timestamp
$_SESSION['last_activity'] = $current_time;

// Update session activity in database
// Ensure DB connection is available
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_activity.php';
update_session_activity($conn);

// Calculate time remaining for warnings
$idle_time_remaining = SESSION_TIMEOUT - ($current_time - $_SESSION['last_activity']);
$absolute_time_remaining = SESSION_ABSOLUTE_TIMEOUT - ($current_time - $_SESSION['created_at']);

// Set session warning flags for JavaScript
$_SESSION['idle_warning'] = ($idle_time_remaining <= SESSION_WARNING_TIME);
$_SESSION['absolute_warning'] = ($absolute_time_remaining <= SESSION_WARNING_TIME);
$_SESSION['idle_time_remaining'] = $idle_time_remaining;
$_SESSION['absolute_time_remaining'] = $absolute_time_remaining;
?>

