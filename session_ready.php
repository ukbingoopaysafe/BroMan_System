<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

// session is already started by bootstrap.php (ensure_secure_session_started())

$ready = false;
$token = null;

if (isset($_SESSION['csrf_token']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $ready = true;
    $token = $_SESSION['csrf_token'];
}

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo json_encode([
    'ready' => $ready,
    'csrf_token' => $token
]);

?>
