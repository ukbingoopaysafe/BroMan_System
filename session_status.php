<?php
header('Content-Type: application/json');

// Include session check
require_once 'session_check.php';

$current_time = time();
$response = array();

// Check if session has expired
$idle_time = $current_time - $_SESSION['last_activity'];
if ($idle_time > SESSION_TIMEOUT) {
    $response['expired'] = true;
    $response['timeout_type'] = 'idle';
    session_destroy();
    exit(json_encode($response));
} else {
    $response['expired'] = false;
}

// Check for warnings
$idle_time_remaining = SESSION_TIMEOUT - $idle_time;

$response['warning'] = false;
$response['timeout_type'] = '';
$response['time_remaining'] = 0;

// Check for idle timeout warning
if ($idle_time_remaining <= SESSION_WARNING_TIME && $idle_time_remaining > 0) {
    $response['warning'] = true;
    $response['timeout_type'] = 'idle';
    $response['time_remaining'] = $idle_time_remaining;
}

// Check for absolute timeout warning (takes precedence if both are active)
if ($absolute_time_remaining <= SESSION_WARNING_TIME && $absolute_time_remaining > 0) {
    $response['warning'] = true;
    $response['timeout_type'] = 'absolute';
    $response['time_remaining'] = $absolute_time_remaining;
}

echo json_encode($response);
?>

