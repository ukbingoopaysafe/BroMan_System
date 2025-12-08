<?php
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once __DIR__ . '/lang/translate.php';
require_once __DIR__ . '/permissions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Validate the mode parameter
$mode = isset($_GET['mode']) ? trim($_GET['mode']) : '';

// Check if mode is valid
if (!in_array($mode, VALID_LISTING_MODES, true)) {
    // Invalid mode, redirect back to dashboard
    header('Location: dashboard.php');
    exit();
}

// Ensure the current role is allowed to access this mode
if (!is_mode_allowed_for_role($mode)) {
    $_SESSION["error_message"] = t('mode_not_allowed_for_role');
    header('Location: dashboard.php');
    exit();
}

// Set the session variable
$_SESSION['listing_context'] = $mode;

// Redirect to the main properties page
header('Location: index_properties.php');
exit();
?>