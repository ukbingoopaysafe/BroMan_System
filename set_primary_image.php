<?php
session_start();
require_once 'db.php';
require_once 'permissions.php';
require_once 'media_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!can_manage_properties()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Accept either form-encoded POST (FormData) or JSON body
$image_id = 0;
$property_id = 0;
$csrf = '';

if (!empty($_POST)) {
    $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
    $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
    $csrf = $_POST['csrf_token'] ?? '';
} else {
    $payload = json_decode(file_get_contents('php://input'), true);
    if ($payload && is_array($payload)) {
        $image_id = isset($payload['image_id']) ? intval($payload['image_id']) : 0;
        $property_id = isset($payload['property_id']) ? intval($payload['property_id']) : 0;
        $csrf = $payload['csrf_token'] ?? '';
    }
}

if (empty($image_id) || empty($property_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// CSRF check
if (empty($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

$ok = setImageAsPrimary($image_id, $property_id, $conn);
if ($ok) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to set primary image']);
}

?>
