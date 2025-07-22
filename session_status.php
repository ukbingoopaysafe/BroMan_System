<?php
header('Content-Type: application/json');

// Include session check
require_once 'session_check.php';

$current_time = time();
$response = array();

// Check if session is still valid (session_check.php would have redirected if expired)
$response['expired'] = false;

// Check for warnings
$idle_time_remaining = SESSION_TIMEOUT - ($current_time - $_SESSION['last_activity']);
$absolute_time_remaining = SESSION_ABSOLUTE_TIMEOUT - ($current_time - $_SESSION['created_at']);

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

