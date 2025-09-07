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
