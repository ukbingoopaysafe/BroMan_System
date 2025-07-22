<?php
// Include configuration
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

// Calculate time remaining for warnings
$idle_time_remaining = SESSION_TIMEOUT - ($current_time - $_SESSION['last_activity']);
$absolute_time_remaining = SESSION_ABSOLUTE_TIMEOUT - ($current_time - $_SESSION['created_at']);

// Set session warning flags for JavaScript
$_SESSION['idle_warning'] = ($idle_time_remaining <= SESSION_WARNING_TIME);
$_SESSION['absolute_warning'] = ($absolute_time_remaining <= SESSION_WARNING_TIME);
$_SESSION['idle_time_remaining'] = $idle_time_remaining;
$_SESSION['absolute_time_remaining'] = $absolute_time_remaining;
?>

