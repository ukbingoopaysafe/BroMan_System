<?php
require_once 'session_check.php'; // Include session timeout check

// Ensure user is logged in and is an admin
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    $_SESSION["error_message"] = "You do not have permission to access this page.";
    header("Location: login.php");
    exit;
}

require_once 'db.php'; // Database connection
require_once 'navbar.php'; // Navigation bar

// Initialize variables
$employee_id = $full_name = $username = $role = "";
$full_name_err = $username_err = $password_err = $role_err = $general_err = "";
$is_editing_self = false;

// Check if ID is provided in URL
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $employee_id = trim($_GET["id"]);
    if ($_SESSION["employee_id"] == $employee_id) {
        $is_editing_self = true;
    }

    // Prepare a select statement to fetch employee details
    $sql_fetch = "SELECT FullName, Username, Role FROM Employees WHERE EmployeeID = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $param_employee_id);
        $param_employee_id = $employee_id;

        if ($stmt_fetch->execute()) {
            $stmt_fetch->store_result();
            if ($stmt_fetch->num_rows == 1) {
                $stmt_fetch->bind_result($full_name, $username, $role);
                $stmt_fetch->fetch();
            } else {
                $_SESSION["error_message"] = "Employee not found.";
                header("Location: employees_list.php");
                exit();
            }
        } else {
            $general_err = "Error fetching employee details.";
            error_log("Error executing employee fetch: " . $stmt_fetch->error);
        }
        $stmt_fetch->close();
    } else {
        $general_err = "Database error (employee fetch). Please try again.";
        error_log("Error preparing employee fetch: " . $conn->error);
    }
} else {
    $_SESSION["error_message"] = "No employee ID specified for editing.";
    header("Location: employees_list.php");
    exit();
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $employee_id_post = trim($_POST["employee_id"]);
    if (empty($employee_id_post) || $employee_id_post != $employee_id) {
        $general_err = "Invalid employee ID submitted.";
    }

    // Validate Full Name
    if (empty(trim($_POST["fullName"]))) {
        $full_name_err = "Please enter the employee's full name.";
    } else {
        $full_name_posted = trim($_POST["fullName"]);
    }

    // Validate Username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $username_err = "Username can only contain letters, numbers, and underscores.";
    } else {
        $sql_check_username = "SELECT EmployeeID FROM Employees WHERE Username = ? AND EmployeeID != ?";
        if ($stmt_check = $conn->prepare($sql_check_username)) {
            $stmt_check->bind_param("si", $param_username_check, $param_employee_id_check);
            $param_username_check = trim($_POST["username"]);
            $param_employee_id_check = $employee_id;
            if ($stmt_check->execute()) {
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $username_err = "This username is already taken.";
                }
                $username_posted = trim($_POST["username"]);
            } else {
                $general_err = "Error checking username. Please try again.";
                error_log("Error executing username check (edit): " . $stmt_check->error);
            }
            $stmt_check->close();
        } else {
            $general_err = "Database error (username check). Please try again.";
            error_log("Error preparing username check (edit): " . $conn->error);
        }
    }

    // Validate Role
    // Prevent admin from demoting themselves if they are the only admin (basic check)
    if ($is_editing_self && $role === 'admin' && $_POST["role"] === 'user') {
        $sql_count_admins = "SELECT COUNT(EmployeeID) as admin_count FROM Employees WHERE Role = 'admin'";
        $admin_count_result = $conn->query($sql_count_admins);
        $admin_count_row = $admin_count_result->fetch_assoc();
        if ($admin_count_row['admin_count'] <= 1) {
            $role_err = "Cannot demote the only administrator.";
        }
    }
    if (empty($role_err)) { // proceed if no error from self-demotion check
        if (empty($_POST["role"])) {
            $role_err = "Please select a role.";
        } elseif (!in_array($_POST["role"], ["admin", "user"])) {
            $role_err = "Invalid role selected.";
        } else {
            $role_posted = $_POST["role"];
        }
    }

    // Validate Password (only if new password is provided)
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];
    $hashed_password_to_update = null; // Will hold the new hash if password is changed

    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $password_err = "New password must have at least 6 characters.";
        } elseif ($new_password != $confirm_password) {
            $password_err = "New passwords do not match.";
        } else {
            $hashed_password_to_update = password_hash($new_password, PASSWORD_DEFAULT);
        }
    }

    // Check input errors before updating in database
    if (empty($full_name_err) && empty($username_err) && empty($password_err) && empty($role_err) && empty($general_err)) {
        if ($hashed_password_to_update !== null) {
            $sql_update = "UPDATE Employees SET FullName = ?, Username = ?, Role = ?, Password = ? WHERE EmployeeID = ?";
        } else {
            $sql_update = "UPDATE Employees SET FullName = ?, Username = ?, Role = ? WHERE EmployeeID = ?";
        }

        if ($stmt_update = $conn->prepare($sql_update)) {
            if ($hashed_password_to_update !== null) {
                $stmt_update->bind_param("ssssi", $param_full_name, $param_username, $param_role, $param_password, $param_employee_id_update);
                $param_password = $hashed_password_to_update;
            } else {
                $stmt_update->bind_param("sssi", $param_full_name, $param_username, $param_role, $param_employee_id_update);
            }
            
            $param_full_name = $full_name_posted;
            $param_username = $username_posted;
            $param_role = $role_posted;
            $param_employee_id_update = $employee_id;
            
            if ($stmt_update->execute()) {
                $_SESSION["success_message"] = "Employee details updated successfully!";
                // If admin edited their own role and demoted themselves, log them out or redirect to login
                if ($is_editing_self && $role === 'admin' && $role_posted === 'user') {
                    // Destroy session and redirect to login
                    session_destroy();
                    header("Location: login.php?message=role_changed");
                    exit();
                }
                header("Location: employees_list.php");
                exit();
            } else {
                $general_err = "Something went wrong. Please try again later.";
                error_log("Error executing employee update: " . $stmt_update->error);
            }
            $stmt_update->close();
        }
    } else {
        $full_name = isset($full_name_posted) ? $full_name_posted : $full_name;
        $username = isset($username_posted) ? $username_posted : $username;
        $role = isset($role_posted) ? $role_posted : $role;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Employee - Broman Real Estate</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="theme.css">
     <style>
        .form-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 20px auto;
        }
        .form-card label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-card .form-input, .form-card select {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-card .btn-success {
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-card .btn-success:hover {
            background-color: #218838;
        }
        .error-message {
            color: red;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        .page-title {
            text-align: center;
            margin-bottom: 20px;
        }
        .password-note {
            font-size: 0.9em;
            color: #555;
            margin-bottom: 15px;
        }
    </style>
    <script src="session_timeout.js"></script>
</head>
<body>

<h2 class="page-title">Edit Employee Details</h2>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . htmlspecialchars($employee_id); ?>" method="POST" class="form-card">
    <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>">

    <?php if (!empty($general_err)): ?>
        <div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;"><?php echo htmlspecialchars($general_err); ?></div>
    <?php endif; ?>

    <div>
        <label for="fullName">Full Name:</label>
        <input type="text" id="fullName" name="fullName" class="form-input <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name); ?>" required>
        <?php if (!empty($full_name_err)): ?><span class="error-message"><?php echo htmlspecialchars($full_name_err); ?></span><?php endif; ?>
    </div>

    <div>
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" class="form-input <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required>
        <?php if (!empty($username_err)): ?><span class="error-message"><?php echo htmlspecialchars($username_err); ?></span><?php endif; ?>
    </div>

    <div>
        <label for="role">Role:</label>
        <select id="role" name="role" class="form-input <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>" required>
            <option value="user" <?php echo ($role == "user") ? "selected" : ""; ?>>User</option>
            <option value="admin" <?php echo ($role == "admin") ? "selected" : ""; ?>>Admin</option>
        </select>
        <?php if (!empty($role_err)): ?><span class="error-message"><?php echo htmlspecialchars($role_err); ?></span><?php endif; ?>
    </div>

    <hr>
    <p class="password-note">Only fill in the password fields below if you want to change the current password.</p>

    <div>
        <label for="new_password">New Password:</label>
        <input type="password" id="new_password" name="new_password" class="form-input <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
    </div>

    <div>
        <label for="confirm_password">Confirm New Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-input <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
        <?php if (!empty($password_err)): ?><span class="error-message"><?php echo htmlspecialchars($password_err); ?></span><?php endif; ?>
    </div>

    <div>
        <input type="submit" value="Save Changes" class="btn btn-success">
        <a href="employees_list.php" style="margin-left: 10px;">Cancel</a>
    </div>
</form>

</body>
</html>
