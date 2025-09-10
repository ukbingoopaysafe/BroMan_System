<?php
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once 'permissions.php'; // Include permissions system

// Check permissions - only manager and admin can delete properties
check_permission('manage_properties');

require_once 'db.php'; // Database connection

// Require POST with valid CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $_SESSION["error_message"] = "Invalid request method.";
    header("Location: index_properties.php");
    exit();
}
if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    $_SESSION["error_message"] = "Invalid CSRF token.";
    header("Location: index_properties.php");
    exit();
}

$property_id_to_delete = null;

if (isset($_POST["id"]) && !empty(trim($_POST["id"]))) {
    $property_id_to_delete = (int) trim($_POST["id"]);

    // Delete all related media files before deleting the property
    // Images
    $sql_images = "SELECT file_path FROM property_images WHERE property_id = ?";
    if ($stmt_img = $conn->prepare($sql_images)) {
        $stmt_img->bind_param("i", $property_id_to_delete);
        $stmt_img->execute();
        $result_img = $stmt_img->get_result();
        while ($row = $result_img->fetch_assoc()) {
            $img_path = __DIR__ . '/' . $row['file_path'];
            if (file_exists($img_path)) { @unlink($img_path); }
        }
        $stmt_img->close();
    }
    // Audio
    $sql_audio = "SELECT file_path FROM property_audio_notes WHERE property_id = ?";
    if ($stmt_audio = $conn->prepare($sql_audio)) {
        $stmt_audio->bind_param("i", $property_id_to_delete);
        $stmt_audio->execute();
        $result_audio = $stmt_audio->get_result();
        while ($row = $result_audio->fetch_assoc()) {
            $audio_path = __DIR__ . '/' . $row['file_path'];
            if (file_exists($audio_path)) { @unlink($audio_path); }
        }
        $stmt_audio->close();
    }
    // Videos
    $sql_videos = "SELECT VideoPath FROM property_videos WHERE PropertyID = ?";
    if ($stmt_vid = $conn->prepare($sql_videos)) {
        $stmt_vid->bind_param("i", $property_id_to_delete);
        $stmt_vid->execute();
        $result_vid = $stmt_vid->get_result();
        while ($row = $result_vid->fetch_assoc()) {
            $vid_path = __DIR__ . '/' . $row['VideoPath'];
            if (file_exists($vid_path)) { @unlink($vid_path); }
        }
        $stmt_vid->close();
    }

    // In a production system, a POST request with a CSRF token is safer for delete actions.
    // For this project, we are keeping the GET request for simplicity but adding admin check.

    // The `Properties` table does not have ON DELETE CASCADE for related records like images or features if they were in other tables.
    // Ensure business logic for deleting related data is handled if necessary.

    $sql = "DELETE FROM Properties WHERE PropertyID = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_property_id);
        $param_property_id = $property_id_to_delete;

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                require_once __DIR__ . '/flash.php';
                if (function_exists('add_flash')) { add_flash("Property deleted successfully.", 6); }
            } else {
                $_SESSION["error_message"] = "Property not found or already deleted.";
            }
        } else {
            $_SESSION["error_message"] = "Error deleting property. Please try again.";
            error_log("Error executing property delete: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $_SESSION["error_message"] = "Database error (property delete). Please try again.";
        error_log("Error preparing property delete: " . $conn->error);
    }
} else {
    $_SESSION["error_message"] = "No property ID specified for deletion.";
}

// Redirect back to the property list page
header("Location: index_properties.php");
exit();
