<?php
session_start();

// Ensure user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    $_SESSION["error_message"] = "You do not have permission to access this page.";
    header("Location: login.php");
    exit;
}

require_once 'db.php'; // Database connection

$employee_id_to_delete = null;

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $employee_id_to_delete = trim($_GET["id"]);

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
                $_SESSION["success_message"] = "Employee deleted successfully.";
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
