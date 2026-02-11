<?php
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once 'session_check.php'; // Include session timeout check
require_once 'permissions.php'; // Include permissions system

// Standardized permission check
check_permission('manage_employees');

require_once 'db.php'; // Database connection

// Require POST with valid CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $_SESSION["error_message"] = "Invalid request method.";
    header("Location: employees_list.php");
    exit();
}
if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    $_SESSION["error_message"] = "Invalid CSRF token.";
    header("Location: employees_list.php");
    exit();
}

$employee_id_to_delete = null;

if (isset($_POST["id"]) && !empty(trim($_POST["id"]))) {
    $employee_id_to_delete = (int) trim($_POST["id"]);

    // Critical: Prevent an admin from deleting their own account, especially if they are the only admin.
    if ($_SESSION["employee_id"] == $employee_id_to_delete) {
        $_SESSION["error_message"] = "You cannot delete your own account.";
        header("Location: employees_list.php");
        exit();
    }

    // Optional: Add a check to prevent deletion of the last admin account if desired.
    // This is a more complex check, ensuring at least one admin remains.
    // For now, we rely on the self-deletion check above.

    // The FK constraint `FK_Properties_Employees` is `ON DELETE SET NULL`,
    // so properties associated with this employee will have their EmployeeID set to NULL.

    $sql = "DELETE FROM Employees WHERE EmployeeID = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_employee_id);
        $param_employee_id = $employee_id_to_delete;

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Log delete_employee action for OIS monitoring
                require_once __DIR__ . '/activity_log.php';
                log_activity($conn, $_SESSION['employee_id'] ?? null, 'delete_employee', json_encode([
                    'deleted_employee_id' => $employee_id_to_delete
                ]));
                require_once __DIR__ . '/flash.php';
                if (function_exists('add_flash')) { add_flash("Employee deleted successfully.", 6); }
            } else {
                $_SESSION["error_message"] = "Employee not found or already deleted.";
            }
        } else {
            $_SESSION["error_message"] = "Error deleting employee. Please try again.";
            error_log("Error executing employee delete: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $_SESSION["error_message"] = "Database error (employee delete). Please try again.";
        error_log("Error preparing employee delete: " . $conn->error);
    }
} else {
    $_SESSION["error_message"] = "No employee ID specified for deletion.";
}

// Redirect back to the employee list page
header("Location: employees_list.php");
exit();
