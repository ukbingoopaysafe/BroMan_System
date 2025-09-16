<?php
// Temporary helper for local testing only. Sets a manager session and prints session id.
session_start();
$_SESSION['loggedin'] = true;
$_SESSION['role'] = 'manager';
$_SESSION['employee_id'] = 1;
header('Content-Type: application/json');
echo json_encode(['session_id' => session_id(), 'php_session_name' => session_name()]);
?>