<?php
// Ensure the script only outputs valid JSON
header('Content-Type: application/json');
// Suppress warnings/notices from leaking into output (root cause should still be fixed below)
error_reporting(0);

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// CSRF protection
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

// Check if property ID is provided
if (!isset($_POST['property_id']) || empty($_POST['property_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Property ID is required']);
    exit;
}

$property_id = intval($_POST['property_id']);
$upload_type = $_POST['upload_type'] ?? ''; // 'image' or 'audio'

// Validate upload type
if (!in_array($upload_type, ['image', 'audio'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid upload type']);
    exit;
}

// Create upload directories if they don't exist
$base_upload_dir = __DIR__ . '/uploads/';
$image_dir = $base_upload_dir . 'images/';
$audio_dir = $base_upload_dir . 'audio/';

if (!is_dir($base_upload_dir)) {
    if (!mkdir($base_upload_dir, 0755, true) && !is_dir($base_upload_dir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create base upload directory']);
        exit;
    }
}
if (!is_dir($image_dir)) {
    if (!mkdir($image_dir, 0755, true) && !is_dir($image_dir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create images directory']);
        exit;
    }
}
if (!is_dir($audio_dir)) {
    if (!mkdir($audio_dir, 0755, true) && !is_dir($audio_dir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create audio directory']);
        exit;
    }
}

// Handle file upload
if ($upload_type === 'image') {
    handleImageUpload($property_id, $image_dir, $conn);
} elseif ($upload_type === 'audio') {
    handleAudioUpload($property_id, $audio_dir, $conn);
}
// Ensure nothing else is output
exit;

function handleImageUpload($property_id, $upload_dir, $conn) {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No image file uploaded or upload error']);
        return;
    }

    $file = $_FILES['image'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $max_size = 10 * 1024 * 1024; // 10MB

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid image format. Only JPG, JPEG, PNG, and WEBP are allowed.']);
        return;
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image file size exceeds 10MB limit.']);
        return;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'property_' . $property_id . '_' . uniqid() . '.' . $extension;
    $file_path = $upload_dir . $filename;
    $relative_path = 'uploads/images/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Check if this is the first image (set as primary)
        $is_primary = false;
        $check_sql = "SELECT COUNT(*) as count FROM property_images WHERE property_id = ?";
        if ($stmt = $conn->prepare($check_sql)) {
            $stmt->bind_param("i", $property_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $is_primary = ($row['count'] == 0); // First image is primary
            $stmt->close();
        }

        // Insert into database
        $sql = "INSERT INTO property_images (property_id, file_path, is_primary) VALUES (?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isi", $property_id, $relative_path, $is_primary);
            if ($stmt->execute()) {
                $image_id = $conn->insert_id;
                echo json_encode([
                    'success' => true, 
                    'message' => 'Image uploaded successfully',
                    'image_id' => $image_id,
                    'file_path' => $relative_path,
                    'is_primary' => $is_primary
                ]);
            } else {
                // Delete uploaded file if database insert fails
                unlink($file_path);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            // Delete uploaded file if prepare fails
            unlink($file_path);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
}

function handleAudioUpload($property_id, $upload_dir, $conn) {
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No audio file uploaded or upload error']);
        return;
    }

    $file = $_FILES['audio'];
    // MODIFIED: Added M4A and MP4 MIME types
    $allowed_types = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/mp4', 'video/mp4'];
    $max_size = 10 * 1024 * 1024; // 10MB

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        http_response_code(400);
        // MODIFIED: Updated the error message
        echo json_encode(['success' => false, 'message' => 'Invalid audio format. Only MP3, WAV, OGG, and M4A are allowed.']);
        return;
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Audio file size exceeds 10MB limit.']);
        return;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'property_' . $property_id . '_audio_' . uniqid() . '.' . $extension;
    $file_path = $upload_dir . $filename;
    $relative_path = 'uploads/audio/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Get audio duration (optional, requires getID3 library or similar)
        $duration = null; // You can implement duration detection here if needed

        // Insert into database
        $sql = "INSERT INTO property_audio_notes (property_id, file_path, duration) VALUES (?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isi", $property_id, $relative_path, $duration);
            if ($stmt->execute()) {
                $note_id = $conn->insert_id;
                echo json_encode([
                    'success' => true, 
                    'message' => 'Audio uploaded successfully',
                    'note_id' => $note_id,
                    'file_path' => $relative_path,
                    'duration' => $duration
                ]);
            } else {
                // Delete uploaded file if database insert fails
                unlink($file_path);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            // Delete uploaded file if prepare fails
            unlink($file_path);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
}

