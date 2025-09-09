<?php
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once __DIR__ . '/lang/translate.php';

// Validate the mode parameter
$mode = isset($_GET['mode']) ? trim($_GET['mode']) : '';

// Check if mode is valid
if ($mode !== 'Primary' && $mode !== 'Resale') {
    // Invalid mode, redirect back to dashboard
    header('Location: dashboard.php');
    exit();
}

// Set the session variable
$_SESSION['listing_context'] = $mode;

// Also set a cookie fallback in case session storage isn't persisting correctly
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
setcookie('listing_context', $mode, [
    'expires' => time() + 3600,
    'path' => '/',
    'secure' => $is_https,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Debug: log session id and saved mode for troubleshooting (will appear in PHP error log)
error_log("set_mode: session_id=" . session_id() . " listing_context=" . var_export($_SESSION['listing_context'], true));

// Redirect to the main properties page
header('Location: index_properties.php');
exit();
?>