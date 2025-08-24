<?php
session_start();
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

// Redirect to the main properties page
header('Location: index_properties.php');
exit();
?>