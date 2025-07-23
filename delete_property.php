<?php
session_start();
require_once 'permissions.php'; // Include permissions system

// Check permissions - only manager and admin can delete properties
check_permission('manage_properties');

require_once 'db.php'; // Database connection

$property_id_to_delete = null;

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $property_id_to_delete = trim($_GET["id"]);

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
                $_SESSION["success_message"] = "Property deleted successfully.";
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
