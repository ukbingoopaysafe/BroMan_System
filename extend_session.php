<?php
header('Content-Type: application/json');

// Include session check
require_once 'session_check.php';

// Update last activity to extend idle timeout
$_SESSION['last_activity'] = time();
// Log session extension
$log_msg = date('Y-m-d H:i:s') . " | Session extended for user: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown') . " | IP: " . $_SERVER['REMOTE_ADDR'] . " | Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";
file_put_contents(__DIR__ . '/session_activity.log', $log_msg, FILE_APPEND);

$response = array(
    'success' => true,
    'message' => 'Session extended successfully'
);

echo json_encode($response);
?>

