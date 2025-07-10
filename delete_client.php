<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

// Ensure user is an admin to delete client details
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    $_SESSION["error_message"] = "You do not have permission to perform this action.";
    header("Location: index.php"); // Redirect to client list or dashboard
    exit;
}

require_once 'db.php'; // Database connection

$client_id = null;

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $client_id = trim($_GET["id"]);

    // In a production system, a POST request with a CSRF token is safer for delete actions.
    // For this project, we are keeping the GET request for simplicity but adding admin check.

    // The FK constraint `FK_Properties_Clients` is `ON DELETE SET NULL`,
    // so properties associated with this client will have their ClientID set to NULL.

    $sql = "DELETE FROM Clients WHERE ClientID = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_client_id);
        $param_client_id = $client_id;

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION["success_message"] = "Client deleted successfully.";
            } else {
                $_SESSION["error_message"] = "Client not found or already deleted.";
            }
        } else {
            $_SESSION["error_message"] = "Error deleting client. Please try again.";
            error_log("Error executing client delete: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $_SESSION["error_message"] = "Database error (client delete). Please try again.";
        error_log("Error preparing client delete: " . $conn->error);
    }
} else {
    $_SESSION["error_message"] = "No client ID specified for deletion.";
}

// Redirect back to the client list page
header("Location: index.php");
exit();
