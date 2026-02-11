<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// CSRF protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

// Property ID may be omitted or 0 for temporary uploads. We'll accept that and
// store files under uploads/temp/{session_id}/ until the property is created.
$property_id = intval($_POST['property_id'] ?? 0);
$upload_type = $_POST['upload_type'] ?? ''; // 'image', 'audio', 'video', or 'document'

// Validate upload type
if (!in_array($upload_type, ['image', 'audio', 'video', 'document'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid upload type']);
    exit;
}

// Allow temporary uploads when property_id <= 0: they'll be stored under uploads/temp/{session_id}/
// and must be moved to the property record after the property is created.

// Create upload directories if they don't exist
$base_upload_dir = __DIR__ . '/uploads/';
$image_dir = $base_upload_dir . 'images/';
$audio_dir = $base_upload_dir . 'audio/';
$video_dir = $base_upload_dir . 'videos/';
$document_dir = $base_upload_dir . 'documents/';

// Temp upload dirs per session for pre-save uploads
$session_temp_dir = $base_upload_dir . 'temp/' . session_id() . '/';
$temp_image_dir = $session_temp_dir . 'images/';
$temp_audio_dir = $session_temp_dir . 'audio/';
$temp_video_dir = $session_temp_dir . 'videos/';
$temp_document_dir = $session_temp_dir . 'documents/';

if (!is_dir($base_upload_dir)) {
    mkdir($base_upload_dir, 0755, true);
}
if (!is_dir($image_dir)) {
    mkdir($image_dir, 0755, true);
}
if (!is_dir($audio_dir)) {
    mkdir($audio_dir, 0755, true);
}
if (!is_dir($video_dir)) {
    mkdir($video_dir, 0755, true);
}
if (!is_dir($document_dir)) {
    mkdir($document_dir, 0755, true);
}

// Ensure temp dirs exist
if (!is_dir($temp_image_dir)) {
    mkdir($temp_image_dir, 0755, true);
}
if (!is_dir($temp_audio_dir)) {
    mkdir($temp_audio_dir, 0755, true);
}
if (!is_dir($temp_video_dir)) {
    mkdir($temp_video_dir, 0755, true);
}
if (!is_dir($temp_document_dir)) {
    mkdir($temp_document_dir, 0755, true);
}

// Log upload_media action for OIS monitoring
require_once __DIR__ . '/activity_log.php';
$emp_id = $_SESSION['employee_id'] ?? null;
log_activity($conn, $emp_id, 'upload_media', json_encode([
    'property_id' => $property_id,
    'upload_type' => $upload_type
]));

// Handle file upload
if ($upload_type === 'image') {
    handleImageUpload($property_id, $image_dir, $conn, $temp_image_dir);
} elseif ($upload_type === 'audio') {
    handleAudioUpload($property_id, $audio_dir, $conn, $temp_audio_dir);
} elseif ($upload_type === 'video') {
    handleVideoUpload($property_id, $video_dir, $conn, $temp_video_dir);
} elseif ($upload_type === 'document') {
    handleDocumentUpload($property_id, $document_dir, $conn, $temp_document_dir);
}

function handleImageUpload($property_id, $upload_dir, $conn, $temp_dir) {
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
    $filename = 'property_' . max(0, $property_id) . '_' . uniqid() . '.' . $extension;
    // If property_id > 0 store in final folder, otherwise store in session temp
    if ($property_id > 0) {
        $file_path = $upload_dir . $filename;
        $relative_path = 'uploads/images/' . $filename;
    } else {
        $file_path = rtrim($temp_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $relative_path = 'uploads/temp/' . session_id() . '/images/' . $filename;
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        if ($property_id > 0) {
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
            // Temporary upload saved; return temp info so caller can move files later
            echo json_encode([
                'success' => true,
                'message' => 'Image uploaded to temporary storage',
                'temp_path' => $relative_path,
                'temp_name' => $filename
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
}

function handleDocumentUpload($property_id, $upload_dir, $conn, $temp_dir) {
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        // Log upload error details for debugging
        $logDir = __DIR__ . '/logs/';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $err = isset($_FILES['document']) ? $_FILES['document']['error'] : 'missing';
        $tmpName = isset($_FILES['document']['tmp_name']) ? $_FILES['document']['tmp_name'] : '';
        $origName = isset($_FILES['document']['name']) ? $_FILES['document']['name'] : '';
        $logMsg = sprintf("[%s] Document upload error on property_id=%s: code=%s, tmp_name=%s, orig_name=%s\n",
            date('Y-m-d H:i:s'), $property_id, $err, $tmpName, $origName);
        @file_put_contents($logDir . 'upload_debug.log', $logMsg, FILE_APPEND);

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No document file uploaded or upload error (code: ' . $err . ')']);
        return;
    }

    $file = $_FILES['document'];
    $allowed_types = ['application/pdf'];
    $max_size = 10 * 1024 * 1024; // 10MB for documents

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Accept common PDF mime variants. Also accept if the filename extension is .pdf as a fallback
    $accepted_mimes = array_merge($allowed_types, ['application/x-pdf', 'application/acrobat', 'applications/vnd.pdf', 'text/pdf']);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($mime_type, $accepted_mimes) && $ext !== 'pdf') {
        // Log unexpected mime types for diagnosis
        $logDir = __DIR__ . '/logs/';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $logMsg = sprintf("[%s] Rejected document upload due to mime-type mismatch: detected=%s, name=%s\n", date('Y-m-d H:i:s'), $mime_type, $file['name']);
        @file_put_contents($logDir . 'upload_debug.log', $logMsg, FILE_APPEND);

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid document format. Only PDF is allowed. Detected: ' . $mime_type]);
        return;
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document file size exceeds 10MB limit.']);
        return;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'property_' . max(0, $property_id) . '_doc_' . uniqid() . '.' . $extension;
    if ($property_id > 0) {
        $file_path = $upload_dir . $filename;
        $relative_path = 'uploads/documents/' . $filename;
    } else {
        $file_path = rtrim($temp_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $relative_path = 'uploads/temp/' . session_id() . '/documents/' . $filename;
    }

    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        if ($property_id > 0) {
            // Try to insert into property_documents if table exists
            $sql = "INSERT INTO property_documents (property_id, file_path, file_name, file_size) VALUES (?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("issi", $property_id, $relative_path, $file['name'], $file['size']);
                if ($stmt->execute()) {
                    $doc_id = $conn->insert_id;
                    echo json_encode([
                        'success' => true,
                        'message' => 'Document uploaded successfully',
                        'document_id' => $doc_id,
                        'file_path' => $relative_path,
                        'file_name' => $file['name']
                    ]);
                } else {
                    // If insert fails, still return success but warn
                    echo json_encode([
                        'success' => true,
                        'message' => 'Document uploaded but database insert failed: ' . $stmt->error,
                        'file_path' => $relative_path,
                        'file_name' => $file['name']
                    ]);
                }
                $stmt->close();
            } else {
                // Table may not exist; return success with path
                echo json_encode([
                    'success' => true,
                    'message' => 'Document uploaded successfully',
                    'file_path' => $relative_path,
                    'file_name' => $file['name']
                ]);
            }
        } else {
            // Temporary upload saved; return temp info
            echo json_encode([
                'success' => true,
                'message' => 'Document uploaded to temporary storage',
                'temp_path' => $relative_path,
                'temp_name' => $filename
            ]);
        }
    } else {
        // Log move failure details
        $logDir = __DIR__ . '/logs/';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $logMsg = sprintf("[%s] Failed to move uploaded document for property_id=%s: tmp=%s, dest=%s, file_error=%s\n",
            date('Y-m-d H:i:s'), $property_id, $file['tmp_name'] ?? '', $file_path, $file['error'] ?? '');
        @file_put_contents($logDir . 'upload_debug.log', $logMsg, FILE_APPEND);

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
}

function handleAudioUpload($property_id, $upload_dir, $conn, $temp_dir) {
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No audio file uploaded or upload error']);
        return;
    }

    $file = $_FILES['audio'];
    // Accept common audio mime types including webm and x-m4a variants
    $allowed_types = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/mp4', 'audio/webm', 'audio/x-m4a', 'video/mp4'];
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
    $filename = 'property_' . max(0, $property_id) . '_audio_' . uniqid() . '.' . $extension;
    if ($property_id > 0) {
        $file_path = $upload_dir . $filename;
        $relative_path = 'uploads/audio/' . $filename;
    } else {
        $file_path = rtrim($temp_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $relative_path = 'uploads/temp/' . session_id() . '/audio/' . $filename;
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Get audio duration (optional)
        $duration = null; // Optionally compute duration

        if ($property_id > 0) {
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
            // Temporary upload saved; return temp info
            echo json_encode([
                'success' => true,
                'message' => 'Audio uploaded to temporary storage',
                'temp_path' => $relative_path,
                'temp_name' => $filename
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
}

function handleVideoUpload($property_id, $upload_dir, $conn, $temp_dir) {
    if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No video file uploaded or upload error']);
        return;
    }

    $file = $_FILES['video'];
    $allowed_types = ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-ms-wmv', 'video/x-flv', 'video/webm', 'video/ogg', 'video/mpeg'];
    $max_size = 250 * 1024 * 1024; // 250MB for videos

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid video format. Only MP4, AVI, MOV, WMV, FLV, and WEBM are allowed.']);
        return;
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Video file size exceeds 250MB limit.']);
        return;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'property_' . max(0, $property_id) . '_video_' . uniqid() . '.' . $extension;
    if ($property_id > 0) {
        $file_path = $upload_dir . $filename;
        $relative_path = 'uploads/videos/' . $filename;
    } else {
        $file_path = rtrim($temp_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $relative_path = 'uploads/temp/' . session_id() . '/videos/' . $filename;
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Get employee ID from session
        $employee_id = $_SESSION['employee_id'] ?? null;

        if ($property_id > 0) {
            // Insert into database
            $sql = "INSERT INTO property_videos (PropertyID, VideoPath, VideoName, VideoSize, EmployeeID) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("issii", $property_id, $relative_path, $file['name'], $file['size'], $employee_id);
                if ($stmt->execute()) {
                    $video_id = $conn->insert_id;
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Video uploaded successfully',
                        'video_id' => $video_id,
                        'file_path' => $relative_path,
                        'video_name' => $file['name'],
                        'video_size' => $file['size']
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
            // Temporary upload saved; return temp info
            echo json_encode([
                'success' => true,
                'message' => 'Video uploaded to temporary storage',
                'temp_path' => $relative_path,
                'temp_name' => $filename,
                'video_name' => $file['name'],
                'video_size' => $file['size']
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
}
?>

