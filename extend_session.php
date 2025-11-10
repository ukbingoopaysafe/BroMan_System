<?php
header('Content-Type: application/json');

// Include session check
require_once 'session_check.php';

// Update last activity to extend idle timeout (no verbose logging to avoid log growth)
$_SESSION['last_activity'] = time();

$response = array(
    'success' => true,
    'message' => 'Session extended successfully'
);

echo json_encode($response);
?>

